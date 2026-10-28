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

uses(RefreshDatabase::class);

it('offers active unclassified banks and posts transfers with a private proof', function () {
    Illuminate\Support\Facades\Storage::fake('local');
    [$school, $student, $invoice] = arrearsFixture($this, 'teller');
    $bank = SchoolAccount::create(['school_id' => $school, 'name' => 'Bank Umum Legacy', 'type' => 'Bank', 'is_active' => true, 'current_balance' => 0]);
    $this->get('/finance/arrears/'.$student->id)->assertOk()->assertViewHas('bankAccounts', fn ($accounts) => $accounts->contains('id', $bank->id));
    $this->post('/finance/arrears/'.$student->id.'/pay', ['invoice_id' => $invoice->id, 'amount_paid' => 100000, 'payment_method' => 'Transfer', 'account_id' => $bank->id, 'payment_date' => '2026-10-11', 'proof' => Illuminate\Http\UploadedFile::fake()->image('bukti.png')])->assertSessionHasNoErrors();
    $transaction = App\Models\Transaction::where('invoice_id', $invoice->id)->sole();
    expect($bank->fresh()->current_balance)->toBe('100000.00')->and($transaction->proof_path)->toStartWith('payment-proofs/'.$school.'/');
    Illuminate\Support\Facades\Storage::disk('local')->assertExists($transaction->proof_path);
    $this->get(route('finance.arrears.proof', [$student, $transaction]))->assertOk();
    $this->get('/finance/arrears/'.$student->id)->assertSee('Bukti transfer');
    $otherStudent = Student::create(['school_id' => $school, 'nis' => 'OTHER-PROOF', 'name' => 'Other Student', 'gender' => 'L']);
    $this->get(route('finance.arrears.proof', [$otherStudent, $transaction]))->assertNotFound();
    $otherSchool = DB::table('schools')->insertGetId(['name' => 'Other Proof School', 'level' => 'sma', 'ownership' => 'swasta']);
    $student->update(['school_id' => $otherSchool]);
    $this->get(route('finance.arrears.proof', [$student, $transaction]))->assertNotFound();
});

it('rejects invalid transfer proofs without recording payment or storing files', function () {
    Illuminate\Support\Facades\Storage::fake('local');
    [$school, $student, $invoice] = arrearsFixture($this);
    $bank = SchoolAccount::create(['school_id' => $school, 'name' => 'Bank Komite', 'type' => 'Bank', 'source_funding' => 'komite', 'is_active' => true]);
    foreach ([Illuminate\Http\UploadedFile::fake()->create('script.txt', 1, 'text/plain'), Illuminate\Http\UploadedFile::fake()->create('large.pdf', 5121, 'application/pdf')] as $proof) {
        $this->post('/finance/arrears/'.$student->id.'/pay', ['invoice_id' => $invoice->id, 'amount_paid' => 100000, 'payment_method' => 'Transfer', 'account_id' => $bank->id, 'payment_date' => '2026-10-11', 'proof' => $proof])->assertSessionHasErrors('proof');
    }
    expect(App\Models\Transaction::count())->toBe(0)->and(Illuminate\Support\Facades\Storage::disk('local')->allFiles())->toBe([]);
});

it('does not leave transfer proofs behind when payment exceeds the outstanding balance', function () {
    Illuminate\Support\Facades\Storage::fake('local');
    [$school, $student, $invoice] = arrearsFixture($this);
    $bank = SchoolAccount::create(['school_id' => $school, 'name' => 'Bank Komite', 'type' => 'Bank', 'source_funding' => 'komite', 'is_active' => true]);
    $this->post('/finance/arrears/'.$student->id.'/pay', ['invoice_id' => $invoice->id, 'amount_paid' => 400000, 'payment_method' => 'Transfer', 'account_id' => $bank->id, 'payment_date' => '2026-10-11', 'proof' => Illuminate\Http\UploadedFile::fake()->image('bukti.png')])->assertSessionHasErrors('amount_paid');
    expect(App\Models\Transaction::count())->toBe(0)->and(Illuminate\Support\Facades\Storage::disk('local')->allFiles())->toBe([]);
});

function arrearsFixture($test, string $role = 'bendahara'): array
{
    $school = DB::table('schools')->insertGetId(['name' => 'Sekolah Tunggakan', 'level' => 'sma', 'ownership' => 'swasta', 'is_active' => true, 'status' => 'active']);
    $user = User::factory()->create(['role' => $role]);
    DB::table('school_user_roles')->insert(['school_id' => $school, 'user_id' => $user->id, 'role' => $role, 'is_active' => true, 'membership_status' => 'active']);
    $test->actingAs($user)->withSession(['active_school_id' => $school]);
    $year = AcademicYear::create(['school_id' => $school, 'year' => '2026/2027', 'semester' => 'Ganjil', 'is_active' => true]);
    $class = SchoolClass::create(['school_id' => $school, 'academic_year_id' => $year->id, 'name' => 'X A', 'grade' => 'X']);
    $student = Student::create(['school_id' => $school, 'nis' => 'AR001', 'nisn' => '00112233', 'name' => 'Alya Arrears', 'gender' => 'P', 'status' => 'active', 'is_active' => true]);
    $class->students()->attach($student->id);
    $income = DB::table('income_types')->insertGetId(['school_id' => $school, 'code' => 'ARK', 'name' => 'Komite', 'category' => 'komite']);
    $bill = BillingItem::create(['school_id' => $school, 'income_type_id' => $income, 'academic_year_id' => $year->id, 'name' => 'Komite Alya', 'amount' => 300000, 'status' => 'active']);
    $invoice = Invoice::create(['school_id' => $school, 'student_id' => $student->id, 'academic_year_id' => $year->id, 'billing_item_id' => $bill->id, 'invoice_number' => 'AR-001', 'due_date' => '2026-10-25', 'total_amount' => 300000, 'status' => 'Belum Lunas']);
    InvoiceItem::create(['invoice_id' => $invoice->id, 'name' => $bill->name, 'amount' => 300000]);
    $cash = SchoolAccount::create(['school_id' => $school, 'name' => 'Kas Sekolah', 'type' => 'Tunai', 'is_active' => true, 'current_balance' => 0]);

    return [$school, $student, $invoice, $income, $cash, $class];
}

it('allows teller arrears access without granting master finance or billing configuration access', function () {
    arrearsFixture($this, 'teller');
    $this->get('/finance/arrears')->assertOk()->assertSee('Tunggakan Siswa')->assertSee('Alya Arrears');
    $this->get('/finance/accounts')->assertForbidden();
    $this->get('/finance/billing')->assertForbidden();
    expect(collect(App\Helpers\MenuHelper::getMainNavItems())->firstWhere('name', 'Tunggakan Siswa')['path'])->toBe('/finance/arrears');
});

it('filters real outstanding amounts by nisn class month and year', function () {
    [$school, $student, $invoice, $income, $cash, $class] = arrearsFixture($this);
    $this->get('/finance/arrears?search=00112233&month=10&year=2026&class_id='.$class->id)->assertOk()->assertSee('Alya Arrears')->assertSee('300.000');
    $this->get('/finance/arrears?month=11&year=2026')->assertOk()->assertDontSee('Alya Arrears')->assertSee('Tidak ada tunggakan');
    $this->get('/finance/arrears?search=not-a-student')->assertOk()->assertDontSee('Alya Arrears');
});

it('loads empty details and rejects students from another school', function () {
    [$school, $student] = arrearsFixture($this);
    $empty = Student::create(['school_id' => $school, 'nis' => 'AR-EMPTY', 'name' => 'Tanpa Tagihan', 'gender' => 'L']);
    $this->get('/finance/arrears/'.$empty->id)->assertOk()->assertSee('Belum ada tagihan');
    $other = DB::table('schools')->insertGetId(['name' => 'Other', 'level' => 'sma', 'ownership' => 'swasta']);
    $outsider = Student::create(['school_id' => $other, 'nis' => 'AR-OTHER', 'name' => 'Other Secret', 'gender' => 'L']);
    $this->get('/finance/arrears/'.$outsider->id)->assertNotFound();
});

it('requires a committee bank for transfer and preserves rejected payment input', function () {
    [$school, $student, $invoice, $income, $cash] = arrearsFixture($this, 'teller');
    $bank = SchoolAccount::create(['school_id' => $school, 'name' => 'Bank BOS', 'type' => 'Bank', 'source_funding' => 'bos', 'is_active' => true]);
    $this->from('/finance/arrears/'.$student->id)->post('/finance/arrears/'.$student->id.'/pay', ['invoice_id' => $invoice->id, 'amount_paid' => 100000, 'payment_method' => 'Transfer', 'account_id' => $bank->id, 'payment_date' => '2026-10-11'])
        ->assertSessionHasErrors('account_id')->assertSessionHasInput('amount_paid', 100000);
    $this->post('/finance/arrears/'.$student->id.'/pay', ['invoice_id' => $invoice->id, 'amount_paid' => 100000, 'payment_method' => 'Transfer', 'payment_date' => '2026-10-11'])->assertSessionHasErrors('account_id');
    expect(DB::table('transactions')->count())->toBe(0)->and($invoice->fresh()->remaining_amount)->toEqual(300000);
});

it('posts receipt balances and allocates only matching school income wallets', function () {
    [$school, $student, $invoice, $income, $cash] = arrearsFixture($this, 'teller');
    $wallet = App\Models\VirtualWallet::create(['school_id' => $school, 'name' => 'Sarpras', 'nominal' => 0, 'status' => 'active', 'source' => 'komite']);
    $wrong = App\Models\VirtualWallet::create(['school_id' => $school, 'name' => 'Other income', 'nominal' => 0, 'status' => 'active', 'source' => 'komite']);
    $otherType = DB::table('income_types')->insertGetId(['school_id' => $school, 'code' => 'OTHER', 'name' => 'Other', 'category' => 'komite']);
    foreach ([[$income, $wallet->id], [$otherType, $wrong->id]] as [$type, $walletId]) {
        App\Models\FundAllocation::create(['school_id' => $school, 'income_type_id' => $type, 'virtual_wallet_id' => $walletId, 'name' => '20%', 'method' => 'percentage', 'amount' => 20, 'status' => 'active']);
    }
    $this->post('/finance/arrears/'.$student->id.'/pay', ['invoice_id' => $invoice->id, 'amount_paid' => 100000, 'payment_method' => 'Tunai', 'payment_date' => '2026-10-11'])->assertSessionHasNoErrors()->assertSessionHas('success', 'Transaksi berhasil disimpan');
    expect($cash->fresh()->current_balance)->toBe('100000.00')->and($wallet->fresh()->nominal)->toBe('20000.00')->and($wrong->fresh()->nominal)->toBe('0.00')->and($invoice->fresh()->status)->toBe('Cicilan');
    $this->get('/finance/arrears/'.$student->id)->assertOk()->assertSee('Cicil')->assertSee('200.000');
});

it('allows administrators to classify bank accounts explicitly as committee accounts', function () {
    [$school] = arrearsFixture($this);
    $this->post(route('finance.accounts.store'), ['name' => 'Bank Komite', 'type' => 'Bank', 'bank_name' => 'Bank Sekolah', 'account_number' => 'AR-BANK', 'opening_balance' => 0, 'source_funding' => 'komite'])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('school_accounts', ['school_id' => $school, 'account_number' => 'AR-BANK', 'source_funding' => 'komite']);
});

it('serves student photos only through school scoped authenticated access', function () {
    Illuminate\Support\Facades\Storage::fake('local');
    [$school, $student] = arrearsFixture($this, 'staf_tu');
    $file = Illuminate\Http\UploadedFile::fake()->image('student.png', 40, 40);
    $this->post(route('siswa.store'), ['action' => 'photo', 'student_id' => $student->id, 'photo' => $file])->assertSessionHasNoErrors();
    expect($student->fresh()->photo_path)->toStartWith('student-photos/'.$school.'/');
    $user = User::factory()->create(['role' => 'teller']);
    DB::table('school_user_roles')->insert(['school_id' => $school, 'user_id' => $user->id, 'role' => 'teller', 'is_active' => true, 'membership_status' => 'active']);
    $this->actingAs($user)->get('/finance/arrears/'.$student->id.'/photo')->assertOk();
    $other = DB::table('schools')->insertGetId(['name' => 'Other Photo School', 'level' => 'sma', 'ownership' => 'swasta']);
    $outsider = Student::create(['school_id' => $other, 'nis' => 'PHOTO-OTHER', 'name' => 'Private Photo', 'gender' => 'L', 'photo_path' => $student->fresh()->photo_path]);
    $this->get('/finance/arrears/'.$outsider->id.'/photo')->assertNotFound();
});

it('does not apply unrelated allocations when the paid income has no allocation rules', function () {
    [$school, $student, $invoice, $income] = arrearsFixture($this);
    $wrongType = DB::table('income_types')->insertGetId(['school_id' => $school, 'code' => 'NO-MATCH', 'name' => 'Other Income', 'category' => 'komite']);
    $wallet = App\Models\VirtualWallet::create(['school_id' => $school, 'name' => 'Other Wallet', 'nominal' => 0, 'status' => 'active', 'source' => 'komite']);
    App\Models\FundAllocation::create(['school_id' => $school, 'income_type_id' => $wrongType, 'virtual_wallet_id' => $wallet->id, 'name' => 'Other 20%', 'method' => 'percentage', 'amount' => 20, 'status' => 'active']);
    $this->post('/finance/arrears/'.$student->id.'/pay', ['invoice_id' => $invoice->id, 'amount_paid' => 100000, 'payment_method' => 'Tunai', 'payment_date' => '2026-10-11'])->assertSessionHasNoErrors();
    expect($wallet->fresh()->nominal)->toBe('0.00');
});

it('previews current fees on listing without changing stored invoice charges', function () {
    [$school, $student, $invoice] = arrearsFixture($this);
    $this->travelTo(Illuminate\Support\Carbon::parse('2026-10-11'));
    $period = App\Models\BillingPeriod::create(['school_id' => $school, 'billing_item_id' => $invoice->billing_item_id, 'period_key' => '2026-10', 'start_date' => '2026-10-01', 'due_date' => '2026-10-05', 'status' => 'deactive']);
    $invoice->update(['billing_period_id' => $period->id, 'principal_amount' => 300000, 'due_date' => '2026-10-05', 'late_fee_per_day' => 5000, 'late_fee_maximum' => 20000]);
    $this->get('/finance/arrears?search=AR001')->assertOk()->assertSee('320.000')->assertSee('Menunggak');
    expect($invoice->fresh()->total_amount)->toBe('300000.00')->and($invoice->fresh()->late_fee_amount)->toBe('0.00');
});

it('posts committee bank transfer balances and displays fully paid invoice history', function () {
    [$school, $student, $invoice, $income, $cash] = arrearsFixture($this, 'teller');
    $bank = SchoolAccount::create(['school_id' => $school, 'name' => 'Bank Komite', 'type' => 'Bank', 'source_funding' => 'komite', 'is_active' => true, 'current_balance' => 0]);
    $this->post('/finance/arrears/'.$student->id.'/pay', ['invoice_id' => $invoice->id, 'amount_paid' => 300000, 'payment_method' => 'Transfer', 'account_id' => $bank->id, 'payment_date' => '2026-10-11'])->assertSessionHasNoErrors()->assertSessionHas('trx_number');
    expect($bank->fresh()->current_balance)->toBe('300000.00')->and($cash->fresh()->current_balance)->toBe('0.00')->and($invoice->fresh()->status)->toBe('Lunas');
    $this->get('/finance/arrears')->assertOk()->assertDontSee('Alya Arrears');
    $this->get('/finance/arrears/'.$student->id)->assertOk()->assertSee('Lunas')->assertSee('AR-001');
});

it('does not post committee cash receipts into a cash account reserved for bos', function () {
    [$school, $student, $invoice, $income, $bosCash] = arrearsFixture($this, 'teller');
    $bosCash->update(['source_funding' => 'bos']);
    $cash = SchoolAccount::create(['school_id' => $school, 'name' => 'Kas Komite', 'type' => 'Tunai', 'source_funding' => 'komite', 'is_active' => true, 'current_balance' => 0]);
    $this->post('/finance/arrears/'.$student->id.'/pay', ['invoice_id' => $invoice->id, 'amount_paid' => 100000, 'payment_method' => 'Tunai', 'payment_date' => '2026-10-11'])->assertSessionHasNoErrors();
    expect($bosCash->fresh()->current_balance)->toBe('0.00')->and($cash->fresh()->current_balance)->toBe('100000.00');
});
