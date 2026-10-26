<?php

use App\Models\ApprovalRequest;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\SchoolAccount;
use App\Models\User;
use App\Models\VirtualWallet;
use App\Services\SchoolNotificationFeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function committeeExpenseFixture($test): array
{
    $school = DB::table('schools')->insertGetId(['name' => 'Sekolah Komite', 'level' => 'sma', 'ownership' => 'swasta', 'status' => 'active', 'is_active' => true]);
    $treasurer = User::factory()->create(['role' => 'bendahara']);
    $head = User::factory()->create(['role' => 'kepsek']);
    foreach ([$treasurer, $head] as $user) {
        DB::table('school_user_roles')->insert(['school_id' => $school, 'user_id' => $user->id, 'role' => $user->role, 'is_active' => true, 'membership_status' => 'active']);
    }
    App\Models\AcademicYear::create(['school_id' => $school, 'year' => '2026/2027', 'semester' => 'Ganjil', 'is_active' => true]);
    $type = ExpenseType::create(['school_id' => $school, 'code' => 'KMT-TEST', 'name' => 'Operasional Komite', 'source_funding' => 'komite', 'requires_approval' => true]);
    $cash = SchoolAccount::create(['school_id' => $school, 'name' => 'Kas Sekolah', 'type' => 'Tunai', 'source_funding' => 'komite', 'is_active' => true, 'current_balance' => 1000000]);
    $bank = SchoolAccount::create(['school_id' => $school, 'name' => 'Bank Komite', 'type' => 'Bank', 'source_funding' => 'komite', 'is_active' => true, 'current_balance' => 2000000]);
    $wallet = VirtualWallet::create(['school_id' => $school, 'name' => 'Dompet Operasional', 'source' => 'komite', 'status' => 'active', 'nominal' => 500000]);
    $payload = ['category' => 'Operasional', 'source_funding' => 'Komite', 'expense_type_id' => $type->id, 'virtual_wallet_id' => $wallet->id,
        'expense_name' => 'Konsumsi rapat', 'amount' => 125000, 'transaction_date' => '2026-10-23', 'recipient_name' => 'Katering Sekolah', 'payment_method' => 'Tunai', 'account_id' => $cash->id];
    $test->actingAs($treasurer)->withSession(['active_school_id' => $school]);

    return compact('school', 'treasurer', 'head', 'type', 'cash', 'bank', 'wallet', 'payload');
}

it('creates numbered pending committee expenses and posts both balances only after principal approval', function () {
    extract(committeeExpenseFixture($this));
    $this->get('/bos/belanja')->assertOk()->assertSee('Metode Pembayaran')->assertSee('Kas / Rekening Pembayaran');
    $this->post('/bos/belanja', $payload)->assertSessionHasNoErrors()->assertSessionHas('success');
    $expense = Expense::firstOrFail();
    expect($expense->transaction_number)->toStartWith('EXP20261023')->and($expense->status)->toBe('pending')
        ->and($cash->fresh()->current_balance)->toBe('1000000.00')->and($wallet->fresh()->nominal)->toBe('500000.00');
    $approval = ApprovalRequest::firstOrFail();
    $this->post(route('approvals.approve', $approval))->assertForbidden();
    $this->actingAs($head);
    expect(app(SchoolNotificationFeed::class)->forUser($head)->pluck('title')->implode(' '))->toContain($expense->transaction_number);
    $this->get('/finance/approvals')->assertOk()->assertSee($expense->transaction_number)->assertSee('Katering Sekolah');
    $this->post(route('approvals.approve', $approval))->assertSessionHasNoErrors();
    expect($cash->fresh()->current_balance)->toBe('875000.00')->and($wallet->fresh()->nominal)->toBe('375000.00')->and($expense->fresh()->posted_at)->not->toBeNull();
    $this->post(route('approvals.approve', $approval))->assertStatus(422);
    expect($cash->fresh()->current_balance)->toBe('875000.00')->and($wallet->fresh()->nominal)->toBe('375000.00');
    expect(app(SchoolNotificationFeed::class)->forUser($head))->toHaveCount(0);
    $duplicate = $approval->replicate();
    $duplicate->fill(['status' => 'pending', 'reviewed_at' => null, 'reviewed_by' => null])->save();
    $this->post(route('approvals.approve', $duplicate))->assertStatus(422);
    expect($cash->fresh()->current_balance)->toBe('875000.00')->and($wallet->fresh()->nominal)->toBe('375000.00');
    $this->actingAs($treasurer)->put('/bos/belanja/'.$expense->id, $payload)->assertStatus(422);
});

it('keeps pending edits unposted and approves the latest validated values', function () {
    extract(committeeExpenseFixture($this));
    $this->post('/bos/belanja', $payload)->assertSessionHasNoErrors();
    $expense = Expense::firstOrFail();
    $this->put('/bos/belanja/'.$expense->id, [...$payload, 'amount' => 150000, 'payment_method' => 'Transfer', 'account_id' => $bank->id])->assertSessionHasNoErrors();
    expect($expense->fresh()->amount)->toBe('150000.00')->and($expense->fresh()->status)->toBe('pending')->and($wallet->fresh()->nominal)->toBe('500000.00');
    $this->actingAs($head)->post(route('approvals.approve', ApprovalRequest::firstOrFail()))->assertSessionHasNoErrors();
    expect($cash->fresh()->current_balance)->toBe('1000000.00')->and($bank->fresh()->current_balance)->toBe('1850000.00')->and($wallet->fresh()->nominal)->toBe('350000.00');
});

it('does not debit a stale committee wallet after a pending expense changes to BOS', function () {
    extract(committeeExpenseFixture($this));
    $this->post('/bos/belanja', $payload)->assertSessionHasNoErrors();
    $expense = Expense::firstOrFail();
    $bank->update(['source_funding' => 'bos']);
    $budgetYear = \App\Models\BudgetYear::create(['school_id' => $school, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'active']);
    $budget = \App\Models\BudgetPlan::create(['school_id' => $school, 'budget_year_id' => $budgetYear->id, 'source_funding' => 'BOS', 'program_name' => 'Operasional', 'activity_name' => 'Belanja', 'amount' => 500000, 'status' => 'approved']);
    $this->put('/bos/belanja/'.$expense->id, [...$payload, 'source_funding' => 'BOS', 'budget_plan_id' => $budget->id, 'payment_method' => 'Transfer', 'account_id' => $bank->id])->assertSessionHasNoErrors();
    expect($expense->fresh()->virtual_wallet_id)->toBeNull();
    $this->actingAs($head)->post(route('approvals.approve', ApprovalRequest::firstOrFail()))->assertSessionHasNoErrors();
    expect($wallet->fresh()->nominal)->toBe('500000.00')->and($bank->fresh()->current_balance)->toBe('1875000.00');
});

it('posts transfer expenses to their bank and leaves rejection balances unchanged', function () {
    extract(committeeExpenseFixture($this));
    $payload = [...$payload, 'payment_method' => 'Transfer', 'account_id' => $bank->id];
    $this->post('/bos/belanja', $payload)->assertSessionHasNoErrors();
    $this->actingAs($head)->post(route('approvals.reject', ApprovalRequest::firstOrFail()))->assertSessionHasNoErrors();
    expect($bank->fresh()->current_balance)->toBe('2000000.00')->and($wallet->fresh()->nominal)->toBe('500000.00');
    $this->actingAs($treasurer)->post('/bos/belanja', $payload)->assertSessionHasNoErrors();
    $this->actingAs($head)->post(route('approvals.approve', ApprovalRequest::latest('id')->firstOrFail()))->assertSessionHasNoErrors();
    expect($bank->fresh()->current_balance)->toBe('1875000.00')->and($cash->fresh()->current_balance)->toBe('1000000.00')->and($wallet->fresh()->nominal)->toBe('375000.00');
});

it('rolls back the decision and both balances if account or wallet funds were spent while pending', function () {
    extract(committeeExpenseFixture($this));
    $this->post('/bos/belanja', $payload)->assertSessionHasNoErrors();
    $approval = ApprovalRequest::firstOrFail();
    $this->actingAs($head);
    foreach ([$wallet, $cash] as $source) {
        $column = $source instanceof VirtualWallet ? 'nominal' : 'current_balance';
        $balance = $source->$column;
        $source->update([$column => 100]);
        $this->post(route('approvals.approve', $approval))->assertSessionHasErrors('amount');
        expect($approval->fresh()->status)->toBe('pending')->and(Expense::first()->status)->toBe('pending')->and(Expense::first()->posted_at)->toBeNull();
        $source->update([$column => $balance]);
    }
    expect($cash->fresh()->current_balance)->toBe('1000000.00')->and($wallet->fresh()->nominal)->toBe('500000.00');
});

it('validates required fields school scope method and private proof access', function () {
    extract(committeeExpenseFixture($this));
    $this->post('/bos/belanja', ['category' => 'Operasional', 'source_funding' => 'Komite'])->assertSessionHasErrors(['expense_type_id', 'virtual_wallet_id', 'recipient_name', 'amount', 'transaction_date']);
    $this->post('/bos/belanja', [...$payload, 'payment_method' => 'Transfer', 'account_id' => $cash->id])->assertSessionHasErrors('account_id');
    $otherSchool = DB::table('schools')->insertGetId(['name' => 'Sekolah Lain', 'level' => 'sma', 'ownership' => 'swasta', 'status' => 'active', 'is_active' => true]);
    $foreignWallet = VirtualWallet::create(['school_id' => $otherSchool, 'name' => 'Other', 'status' => 'active', 'nominal' => 1000000]);
    $this->post('/bos/belanja', [...$payload, 'virtual_wallet_id' => $foreignWallet->id])->assertSessionHasErrors('virtual_wallet_id');
    Storage::fake('local');
    $this->post('/bos/belanja', [...$payload, 'proof' => UploadedFile::fake()->create('nota.pdf', 10, 'application/pdf')])->assertSessionHasNoErrors();
    $expense = Expense::firstOrFail();
    expect($expense->proof_path)->toStartWith('finance/expenses/'.$school.'/');
    Storage::disk('local')->assertExists($expense->proof_path);
    $this->get('/bos/belanja/'.$expense->id.'/proof')->assertOk();
    $outsider = User::factory()->create(['role' => 'bendahara']);
    DB::table('school_user_roles')->insert(['school_id' => $otherSchool, 'user_id' => $outsider->id, 'role' => 'bendahara', 'is_active' => true, 'membership_status' => 'active']);
    $this->actingAs($outsider)->withSession(['active_school_id' => $otherSchool])->get('/bos/belanja/'.$expense->id.'/proof')->assertNotFound();
});
