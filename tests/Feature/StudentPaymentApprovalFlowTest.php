<?php

use App\Models\ApprovalRequest;
use App\Models\Invoice;
use App\Models\PaymentSubmission;
use App\Models\SchoolAccount;
use App\Models\Student;
use App\Models\User;
use App\Services\SchoolNotificationFeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function paymentReviewFixture($test, string $role = 'teller'): array
{
    $school = DB::table('schools')->insertGetId(['name' => 'Review School', 'level' => 'sma', 'ownership' => 'swasta', 'status' => 'active', 'is_active' => true]);
    $users = collect([$role, 'siswa', 'orang_tua', 'siswa'])->map(function ($role) use ($school) {
        $user = User::factory()->create(['role' => $role]);
        DB::table('school_user_roles')->insert(['school_id' => $school, 'user_id' => $user->id, 'role' => $role, 'is_active' => true, 'membership_status' => 'active']);

        return $user;
    });
    [$reviewer, $child, $parent, $stranger] = $users->all();
    $student = Student::create(['school_id' => $school, 'user_id' => $child->id, 'guardian_user_id' => $parent->id, 'nis' => 'REVIEW01', 'name' => 'Siswa Review', 'gender' => 'L']);
    $account = SchoolAccount::create(['school_id' => $school, 'name' => 'Bank Komite Review', 'type' => 'Bank', 'source_funding' => 'komite', 'is_active' => true, 'current_balance' => 0]);
    $year = App\Models\AcademicYear::create(['school_id' => $school, 'year' => '2026/2027', 'semester' => 'Ganjil', 'is_active' => true]);
    $invoice = Invoice::create(['school_id' => $school, 'academic_year_id' => $year->id, 'student_id' => $student->id, 'invoice_number' => 'REVIEW-001', 'total_amount' => 125000, 'due_date' => '2026-10-20', 'status' => 'Belum Lunas']);
    $payment = PaymentSubmission::create(['school_id' => $school, 'student_id' => $student->id, 'invoice_id' => $invoice->id, 'submitted_by' => $child->id, 'account_id' => $account->id, 'amount' => 125000, 'payment_date' => '2026-10-15', 'status' => 'pending', 'payment_method' => 'Transfer Bank']);
    $approval = ApprovalRequest::create(['school_id' => $school, 'requested_by' => $child->id, 'type' => 'payment', 'approvable_type' => PaymentSubmission::class, 'approvable_id' => $payment->id, 'status' => 'pending']);
    $test->actingAs($reviewer)->withSession(['active_school_id' => $school]);

    return compact('school', 'reviewer', 'child', 'parent', 'stranger', 'student', 'account', 'invoice', 'payment', 'approval');
}

it('lets teller review payment details and approve only once with recipient notifications', function () {
    extract(paymentReviewFixture($this));
    $this->get('/finance/approvals?type=payment')->assertOk()->assertSee('Approval Tunggakan');
    $this->get('/payment-approvals/'.$approval->id)->assertOk()->assertSee('Siswa Review')->assertSee('REVIEW-001')->assertSee('Bank Komite Review');
    expect(app(SchoolNotificationFeed::class)->forUser($reviewer)->pluck('title')->implode(' '))->toContain('menunggu verifikasi');
    $this->post('/approvals/'.$approval->id.'/approve')->assertSessionHasNoErrors()->assertRedirect();
    expect($payment->fresh()->status)->toBe('approved')->and($invoice->fresh()->status)->toBe('Lunas')->and($account->fresh()->current_balance)->toBe('125000.00');
    $this->post('/approvals/'.$approval->id.'/approve')->assertStatus(422);
    expect(DB::table('transactions')->count())->toBe(1);
    foreach ([$child, $parent] as $recipient) {
        $this->actingAs($recipient);
        expect(app(SchoolNotificationFeed::class)->forUser($recipient)->pluck('title')->implode(' '))->toContain('sudah diterima');
        $this->get('/payment-approvals/'.$approval->id)->assertOk();
    }
    $this->actingAs($stranger)->get('/payment-approvals/'.$approval->id)->assertForbidden();
    expect(app(SchoolNotificationFeed::class)->forUser($stranger))->toHaveCount(0);
});

it('requires rejection reason and leaves balances and invoices unchanged', function () {
    extract(paymentReviewFixture($this, 'bendahara'));
    $this->post('/approvals/'.$approval->id.'/reject')->assertSessionHasErrors('note');
    $this->post('/approvals/'.$approval->id.'/reject', ['note' => 'Bukti transfer tidak sesuai'])->assertSessionHasNoErrors();
    expect($payment->fresh()->status)->toBe('rejected')->and($payment->fresh()->review_note)->toBe('Bukti transfer tidak sesuai')
        ->and($account->fresh()->current_balance)->toBe('0.00')->and($invoice->fresh()->status)->toBe('Belum Lunas')->and(DB::table('transactions')->count())->toBe(0);
    $this->actingAs($parent);
    expect(app(SchoolNotificationFeed::class)->forUser($parent)->pluck('title')->implode(' '))->toContain('belum diterima');
    $this->get('/payment-approvals/'.$approval->id)->assertSee('Bukti transfer tidak sesuai');
});

it('protects payment proof and school scope and excludes nonpayment requests from teller queue', function () {
    extract(paymentReviewFixture($this));
    Storage::fake('local');
    $path = 'payment-proofs/'.$school.'/transfer.pdf';
    Storage::disk('local')->put($path, '%PDF-test');
    $payment->update(['proof_path' => $path]);
    $this->get('/payment-approvals/'.$approval->id.'/proof')->assertOk();
    $this->actingAs($stranger)->get('/payment-approvals/'.$approval->id.'/proof')->assertForbidden();
    $this->post('/approvals/'.$approval->id.'/approve')->assertForbidden();
    $this->actingAs($reviewer);
    $other = ApprovalRequest::create(['school_id' => $school, 'requested_by' => $reviewer->id, 'type' => 'transfer', 'approvable_type' => App\Models\SchoolAccount::class, 'approvable_id' => $account->id, 'status' => 'pending', 'note' => 'Secret other request']);
    $this->get('/finance/approvals')->assertOk()->assertDontSee('Secret other request');
    $this->post('/approvals/'.$other->id.'/approve')->assertForbidden();
    $payment->update(['school_id' => DB::table('schools')->insertGetId(['name' => 'Other', 'level' => 'sma', 'ownership' => 'swasta', 'status' => 'active', 'is_active' => true])]);
    $this->get('/payment-approvals/'.$approval->id)->assertForbidden();
    $this->post('/approvals/'.$approval->id.'/approve')->assertForbidden();
});

it('rolls back approval when a payment exceeds the remaining invoice balance', function () {
    extract(paymentReviewFixture($this));
    $payment->update(['amount' => 150000]);
    $this->post('/approvals/'.$approval->id.'/approve')->assertSessionHasErrors();
    expect($approval->fresh()->status)->toBe('pending')->and($payment->fresh()->status)->toBe('pending')
        ->and($account->fresh()->current_balance)->toBe('0.00')->and(DB::table('transactions')->count())->toBe(0);
    $this->actingAs($child);
    expect(app(SchoolNotificationFeed::class)->forUser($child))->toHaveCount(0);
});

it('blocks foreign school details decisions proofs and notification items', function () {
    extract(paymentReviewFixture($this));
    $foreignSchool = DB::table('schools')->insertGetId(['name' => 'Foreign', 'level' => 'sma', 'ownership' => 'swasta', 'status' => 'active', 'is_active' => true]);
    DB::table('school_user_roles')->insert(['school_id' => $foreignSchool, 'user_id' => $reviewer->id, 'role' => 'teller', 'is_active' => true, 'membership_status' => 'active']);
    $this->withSession(['active_school_id' => $foreignSchool]);
    $this->get('/payment-approvals/'.$approval->id)->assertForbidden();
    $this->get('/payment-approvals/'.$approval->id.'/proof')->assertForbidden();
    $this->post('/approvals/'.$approval->id.'/approve')->assertForbidden();
    $this->get('/finance/approvals?type=payment')->assertOk()->assertDontSee('REVIEW-001');
    expect(app(SchoolNotificationFeed::class)->forUser($reviewer))->toHaveCount(0);
});
