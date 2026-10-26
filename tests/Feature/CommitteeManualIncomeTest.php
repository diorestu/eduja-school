<?php

use App\Models\AcademicYear;
use App\Models\BillingItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\SchoolAccount;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function manualCommitteeFixture($test): array
{
    $school = DB::table('schools')->insertGetId(['name' => 'Manual Committee School', 'level' => 'sma', 'ownership' => 'swasta', 'is_active' => true, 'status' => 'active']);
    $user = User::factory()->create(['role' => 'bendahara']);
    DB::table('school_user_roles')->insert(['school_id' => $school, 'user_id' => $user->id, 'role' => 'bendahara', 'is_active' => true, 'membership_status' => 'active']);
    $test->actingAs($user)->withSession(['active_school_id' => $school]);
    $year = AcademicYear::create(['school_id' => $school, 'year' => '2026/2027', 'semester' => 'Ganjil', 'is_active' => true]);
    $class = SchoolClass::create(['school_id' => $school, 'academic_year_id' => $year->id, 'name' => 'X Manual', 'grade' => 'X']);
    $student = Student::create(['school_id' => $school, 'nis' => 'MAN01', 'nisn' => '11220033', 'name' => 'Siswa Manual', 'gender' => 'L', 'is_active' => true, 'status' => 'active']);
    $class->students()->attach($student->id);
    $invoices = collect();
    $wallets = collect();
    foreach ([300000, 200000] as $index => $amount) {
        $type = App\Models\IncomeType::create(['school_id' => $school, 'code' => 'MAN'.$index, 'name' => 'Komite '.$index, 'category' => 'komite']);
        $bill = BillingItem::create(['school_id' => $school, 'academic_year_id' => $year->id, 'income_type_id' => $type->id, 'name' => 'Manual '.$index, 'amount' => $amount, 'status' => 'active']);
        $invoice = Invoice::create(['school_id' => $school, 'student_id' => $student->id, 'academic_year_id' => $year->id, 'billing_item_id' => $bill->id, 'invoice_number' => 'MAN-INV-'.$index, 'due_date' => '2026-10-25', 'total_amount' => $amount, 'status' => 'Belum Lunas']);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'name' => $bill->name, 'amount' => $amount]);
        $wallet = App\Models\VirtualWallet::create(['school_id' => $school, 'name' => 'Wallet '.$index, 'source' => 'komite', 'status' => 'active', 'nominal' => 0]);
        App\Models\FundAllocation::create(['school_id' => $school, 'income_type_id' => $type->id, 'virtual_wallet_id' => $wallet->id, 'name' => '20%', 'method' => 'percentage', 'amount' => 20, 'status' => 'active']);
        $invoices->push($invoice);
        $wallets->push($wallet);
    }
    $cash = SchoolAccount::create(['school_id' => $school, 'name' => 'Kas Manual', 'type' => 'Tunai', 'is_active' => true, 'current_balance' => 0]);

    return [$school, $student, $invoices, $wallets, $cash];
}

function manualCommitteePayload($invoices): array
{
    return ['payments' => $invoices->map(fn ($invoice) => ['invoice_id' => $invoice->id, 'amount_paid' => $invoice->total_amount])->all(),
        'payment_method' => 'Tunai', 'payment_date' => '2026-10-11', 'submission_key' => (string) Str::uuid()];
}

it('opens the committee category with arrears filters and multi invoice selection', function () {
    [$school, $student] = manualCommitteeFixture($this);
    $this->get('/spp/transaksi?category=komite')->assertOk()->assertSee('Pemasukan Komite')->assertSee('Siswa Manual')->assertSee('Nama, NIS, atau NISN');
    $this->get('/finance/incomes/committee/'.$student->id)->assertOk()->assertSee('Pilih tagihan')->assertSee('Manual 0')->assertSee('Manual 1');
});

it('records one receipt for multiple invoices with matching wallet allocations and one account total', function () {
    [$school, $student, $invoices, $wallets, $cash] = manualCommitteeFixture($this);
    $payload = manualCommitteePayload($invoices);
    $this->post('/finance/incomes/committee/'.$student->id, $payload)->assertSessionHasNoErrors()->assertSessionHas('success', 'Transaksi berhasil disimpan')->assertSessionHas('committee_receipt_number');
    expect(DB::table('committee_receipts')->count())->toBe(1)->and(DB::table('transactions')->count())->toBe(2)
        ->and($cash->fresh()->current_balance)->toBe('500000.00')->and($invoices[0]->fresh()->status)->toBe('Lunas')->and($invoices[1]->fresh()->status)->toBe('Lunas')
        ->and($wallets[0]->fresh()->nominal)->toBe('60000.00')->and($wallets[1]->fresh()->nominal)->toBe('40000.00');
    $this->post('/finance/incomes/committee/'.$student->id, $payload)->assertSessionHasNoErrors();
    expect(DB::table('transactions')->count())->toBe(2)->and($cash->fresh()->current_balance)->toBe('500000.00');
});

it('rolls back every selected invoice and balance when one amount is invalid', function () {
    [$school, $student, $invoices, $wallets, $cash] = manualCommitteeFixture($this);
    $payload = manualCommitteePayload($invoices);
    $payload['payments'][1]['amount_paid'] = 250000;
    $this->post('/finance/incomes/committee/'.$student->id, $payload)->assertSessionHasErrors();
    expect(DB::table('transactions')->count())->toBe(0)->and($cash->fresh()->current_balance)->toBe('0.00')->and($wallets[0]->fresh()->nominal)->toBe('0.00')->and($invoices[0]->fresh()->status)->toBe('Belum Lunas');
});

it('rolls back earlier invoice postings when a later wallet allocation fails', function () {
    [$school, $student, $invoices, $wallets, $cash] = manualCommitteeFixture($this);
    App\Models\FundAllocation::where('virtual_wallet_id', $wallets[1]->id)->update(['amount' => 200]);
    $this->post('/finance/incomes/committee/'.$student->id, manualCommitteePayload($invoices))->assertSessionHasErrors();
    expect(DB::table('committee_receipts')->count())->toBe(0)->and(DB::table('transactions')->count())->toBe(0)
        ->and($cash->fresh()->current_balance)->toBe('0.00')->and($wallets[0]->fresh()->nominal)->toBe('0.00')->and($invoices[0]->fresh()->status)->toBe('Belum Lunas');
});

it('excludes bos obligations and rejects invoices for another student or repeated ids', function () {
    [$school, $student, $invoices] = manualCommitteeFixture($this);
    $otherStudent = Student::create(['school_id' => $school, 'nis' => 'MAN02', 'name' => 'Other Student', 'gender' => 'L']);
    $invoices[1]->update(['student_id' => $otherStudent->id]);
    $this->post('/finance/incomes/committee/'.$student->id, manualCommitteePayload($invoices))->assertSessionHasErrors('payments');
    $payload = manualCommitteePayload(collect([$invoices[0], $invoices[0]]));
    $this->post('/finance/incomes/committee/'.$student->id, $payload)->assertSessionHasErrors('payments.0.invoice_id');
    App\Models\IncomeType::whereKey($invoices[0]->billingItem->income_type_id)->update(['category' => 'bos']);
    $this->get('/spp/transaksi?category=komite')->assertOk()->assertDontSee('Siswa Manual');
    $this->post('/finance/incomes/committee/'.$student->id, manualCommitteePayload(collect([$invoices[0]])))->assertSessionHasErrors('payments');
    expect(DB::table('transactions')->count())->toBe(0);
});

it('requires committee bank transfer and rejects mismatched idempotency payloads', function () {
    [$school, $student, $invoices, $wallets, $cash] = manualCommitteeFixture($this);
    $bank = SchoolAccount::create(['school_id' => $school, 'name' => 'Bank Komite', 'type' => 'Bank', 'source_funding' => 'komite', 'is_active' => true, 'current_balance' => 0]);
    $payload = [...manualCommitteePayload($invoices), 'payment_method' => 'Transfer'];
    $this->post('/finance/incomes/committee/'.$student->id, $payload)->assertSessionHasErrors('account_id');
    $payload['account_id'] = $bank->id;
    $this->post('/finance/incomes/committee/'.$student->id, $payload)->assertSessionHasNoErrors();
    expect($bank->fresh()->current_balance)->toBe('500000.00')->and($cash->fresh()->current_balance)->toBe('0.00');
    $payload['payments'][0]['amount_paid'] = 100000;
    $this->post('/finance/incomes/committee/'.$student->id, $payload)->assertSessionHasErrors('submission_key');
    expect(DB::table('transactions')->count())->toBe(2);
});

it('recognizes an unambiguous legacy committee bill but excludes ambiguous names', function () {
    [$school, $student, $invoices] = manualCommitteeFixture($this);
    $invoices[0]->update(['billing_item_id' => null]);
    $this->get('/finance/incomes/committee/'.$student->id)->assertOk()->assertSee('Manual 0');
    $duplicate = $invoices[1]->billingItem->replicate();
    $duplicate->name = 'Manual 0';
    $duplicate->save();
    $this->get('/finance/incomes/committee/'.$student->id)->assertOk()->assertDontSee('Manual 0');
});

it('preserves existing school pic access to the committee category', function () {
    [$school] = manualCommitteeFixture($this);
    $user = User::factory()->create(['role' => 'pic_sekolah']);
    DB::table('school_user_roles')->insert(['school_id' => $school, 'user_id' => $user->id, 'role' => 'pic_sekolah', 'is_active' => true, 'membership_status' => 'active']);
    $this->actingAs($user)->get('/spp/transaksi?category=komite')->assertOk();
});

it('accepts formatted decimal payments without losing the fractional balance', function () {
    [$school, $student, $invoices, $wallets, $cash] = manualCommitteeFixture($this);
    $invoices[0]->update(['total_amount' => 100.50]);
    $payload = manualCommitteePayload(collect([$invoices[0]->fresh()]));
    $payload['payments'][0]['amount_paid'] = '100,50';
    $this->post('/finance/incomes/committee/'.$student->id, $payload)->assertSessionHasNoErrors();
    expect($cash->fresh()->current_balance)->toBe('100.50')->and($invoices[0]->fresh()->status)->toBe('Lunas');
});

it('does not expose the income screen without an authorized active school', function () {
    $user = User::factory()->create(['role' => 'bendahara']);
    $this->actingAs($user)->get('/spp/transaksi')->assertRedirect('/school/select');
});
