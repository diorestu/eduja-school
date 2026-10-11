<?php

use App\Models\AcademicYear;
use App\Models\BillingItem;
use App\Models\Invoice;
use App\Models\Student;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function committeeFixture(): array
{
    $school = DB::table('schools')->insertGetId(['name' => 'SMK Komite', 'level' => 'smk', 'ownership' => 'swasta', 'is_active' => true, 'status' => 'active']);
    $year = AcademicYear::create(['school_id' => $school, 'year' => '2026/2027', 'semester' => 'Ganjil', 'is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']);
    $income = DB::table('income_types')->insertGetId(['school_id' => $school, 'name' => 'Komite', 'category' => 'komite', 'code' => 'KOM']);
    $student = Student::create(['school_id' => $school, 'nis' => 'K001', 'name' => 'Siswa A', 'gender' => 'L', 'is_active' => true, 'status' => 'active', 'parent_phone' => '081234567899']);
    foreach (['Tunai', 'Bank'] as $type) {
        App\Models\SchoolAccount::create(['school_id' => $school, 'name' => $type.' Komite', 'type' => $type, 'is_active' => true, 'opening_balance' => 0, 'current_balance' => 0]);
    }

    return [$school, $year, $income, $student];
}

function committeeAttributes(AcademicYear $year, int $income): array
{
    return ['academic_year_id' => $year->id, 'income_type_id' => $income, 'name' => 'Komite 2026', 'amount' => 300000, 'allow_installment' => true, 'minimum_installment' => 100000, 'has_late_fee' => true, 'late_fee_per_day' => 5000, 'late_fee_maximum' => 20000, 'billing_frequency' => 'Bulanan', 'billing_day' => 5, 'due_day' => 25, 'target_type' => 'school'];
}

it('generates monthly invoices once and expires periods without losing arrears', function () {
    [$school, $year, $income, $student] = committeeFixture();
    Carbon::setTestNow('2026-10-11 09:00:00');
    $service = app(BillingService::class);
    $bill = $service->create($school, committeeAttributes($year, $income));
    expect(Invoice::where('billing_item_id', $bill->id)->count())->toBe(1);
    $service->process(Carbon::parse('2026-12-26'));
    $service->process(Carbon::parse('2026-12-26'));
    expect(Invoice::where('billing_item_id', $bill->id)->count())->toBe(3)
        ->and($bill->fresh()->status)->toBe('active')
        ->and(DB::table('billing_periods')->where('billing_item_id', $bill->id)->where('status', 'deactive')->count())->toBe(3)
        ->and(Invoice::where('student_id', $student->id)->where('status', 'Belum Lunas')->count())->toBe(3);
});

it('clamps day 31 and waits until the first publication date', function () {
    [$school, $year, $income] = committeeFixture();
    Carbon::setTestNow('2027-02-01');
    $service = app(BillingService::class);
    $bill = $service->create($school, [...committeeAttributes($year, $income), 'billing_day' => 31, 'due_day' => 31]);
    expect(Invoice::count())->toBe(0);
    $service->process(Carbon::parse('2027-02-28'));
    expect(Invoice::first()->due_date->toDateString())->toBe('2027-02-28');
    $service->process(Carbon::parse('2027-07-01'));
    expect(DB::table('billing_periods')->where('billing_item_id', $bill->id)->count())->toBe(5);
});

it('intersects cohort class department and student within the chosen academic year', function () {
    [$school, $year, $income, $student] = committeeFixture();
    $department = DB::table('departments')->insertGetId(['school_id' => $school, 'name' => 'TKJ', 'code' => 'TKJ']);
    $class = DB::table('school_classes')->insertGetId(['school_id' => $school, 'academic_year_id' => $year->id, 'department_id' => $department, 'name' => 'X TKJ', 'grade' => 'X']);
    DB::table('class_students')->insert(['school_class_id' => $class, 'student_id' => $student->id]);
    $bill = new BillingItem([...committeeAttributes($year, $income), 'school_id' => $school, 'target_type' => 'pilihan', 'target_generation' => 2026, 'target_department_id' => $department, 'target_class_id' => $class, 'target_student_id' => $student->id]);
    $student->update(['generation' => 2026]);
    expect(app(BillingService::class)->targetStudents($bill)->pluck('id')->all())->toBe([$student->id]);
    $bill->target_generation = 2025;
    expect(app(BillingService::class)->targetStudents($bill)->count())->toBe(0);
});

afterEach(fn () => Carbon::setTestNow());

function committeeLogin($test, int $school): void
{
    $user = App\Models\User::factory()->create(['role' => 'bendahara']);
    DB::table('school_user_roles')->insert(['school_id' => $school, 'user_id' => $user->id, 'role' => 'bendahara', 'is_active' => true, 'membership_status' => 'active']);
    $test->actingAs($user)->withSession(['active_school_id' => $school]);
}

it('creates one off committee bills without an additional name input and reports the actual count', function () {
    [$school, $year, $income] = committeeFixture();
    committeeLogin($this, $school);
    $attributes = committeeAttributes($year, $income);
    unset($attributes['name']);
    $this->post(route('finance.billing.store'), [...$attributes, 'billing_frequency' => 'Sekali', 'start_date' => '2026-10-11', 'due_date' => '2026-10-25'])
        ->assertSessionHasNoErrors()->assertSessionHas('target_student_count', 1);
    expect(Invoice::count())->toBe(1)->and(Invoice::first()->billing_period_id)->not->toBeNull();
});

it('rejects non committee types cross school targets and invalid installment amounts', function () {
    [$school, $year, $income] = committeeFixture();
    committeeLogin($this, $school);
    $attributes = committeeAttributes($year, $income);
    DB::table('income_types')->where('id', $income)->update(['category' => 'bos']);
    $this->post(route('finance.billing.store'), $attributes)->assertSessionHasErrors('income_type_id');
    DB::table('income_types')->where('id', $income)->update(['category' => 'komite']);
    $this->post(route('finance.billing.store'), [...$attributes, 'minimum_installment' => 400000])->assertSessionHasErrors('minimum_installment');
    $otherSchool = DB::table('schools')->insertGetId(['name' => 'Other', 'level' => 'sma', 'ownership' => 'swasta']);
    $other = Student::create(['school_id' => $otherSchool, 'nis' => 'OTHER', 'name' => 'Other', 'gender' => 'L', 'is_active' => true]);
    $this->post(route('finance.billing.store'), [...$attributes, 'target_type' => 'pilihan', 'target_student_id' => $other->id])->assertSessionHasErrors('target_student_id');
    expect(BillingItem::count())->toBe(0);
});

it('caps daily fees and permits payment on an expired period', function () {
    [$school, $year, $income] = committeeFixture();
    Carbon::setTestNow('2026-10-11');
    $bill = app(BillingService::class)->create($school, committeeAttributes($year, $income));
    $invoice = Invoice::first();
    $payments = app(App\Services\BillingPaymentService::class);
    $payments->refreshCharges($invoice, Carbon::parse('2026-10-28'));
    expect($invoice->fresh()->late_fee_amount)->toBe('15000.00')->and($invoice->fresh()->total_amount)->toBe('315000.00');
    $payments->refreshCharges($invoice, Carbon::parse('2026-11-20'));
    expect($invoice->fresh()->late_fee_amount)->toBe('20000.00');
    $payments->validatePayment($invoice->fresh(), 100000);
    DB::table('transactions')->insert(['invoice_id' => $invoice->id, 'school_id' => $school, 'amount_paid' => 320000, 'payment_date' => '2026-11-20', 'payment_method' => 'Tunai', 'receipt_number' => 'KOM-PAID', 'status' => 'confirmed']);
    $payments->syncStatus($invoice->fresh());
    $payments->refreshCharges($invoice->fresh(), Carbon::parse('2026-12-20'));
    expect($invoice->fresh()->status)->toBe('Lunas')->and($invoice->fresh()->late_fee_amount)->toBe('20000.00');
});

it('enforces installment minimum except when settling the final remaining amount', function () {
    [$school, $year, $income] = committeeFixture();
    Carbon::setTestNow('2026-10-11');
    app(BillingService::class)->create($school, committeeAttributes($year, $income));
    $invoice = Invoice::first();
    $payments = app(App\Services\BillingPaymentService::class);
    expect(fn () => $payments->validatePayment($invoice, 50000))->toThrow(Illuminate\Validation\ValidationException::class);
    DB::table('transactions')->insert(['invoice_id' => $invoice->id, 'school_id' => $school, 'amount_paid' => 250000, 'payment_date' => '2026-10-11', 'payment_method' => 'Tunai', 'receipt_number' => 'KOM-PART', 'status' => 'confirmed']);
    $payments->validatePayment($invoice->fresh(), 50000);
    $invoice->update(['allow_installment' => false]);
    expect(fn () => $payments->validatePayment($invoice->fresh(), 25000))->toThrow(Illuminate\Validation\ValidationException::class);
    $payments->validatePayment($invoice->fresh(), 50000);
    expect($invoice->fresh()->remaining_amount)->toEqual(50000);
});

it('prepares deduplicated h7 deadline and recurring arrears reminders without fabricated delivery', function () {
    [$school, $year, $income] = committeeFixture();
    Carbon::setTestNow('2026-10-11');
    app(BillingService::class)->create($school, committeeAttributes($year, $income));
    $reminders = app(App\Services\BillingReminderService::class);
    $reminders->prepare(Carbon::parse('2026-10-18'));
    $reminders->prepare(Carbon::parse('2026-10-18'));
    expect(DB::table('billing_reminders')->count())->toBe(1);
    $reminders->prepare(Carbon::parse('2026-10-25'));
    $reminders->prepare(Carbon::parse('2026-11-25'));
    expect(DB::table('billing_reminders')->count())->toBe(3);
    config(['services.billing_whatsapp.endpoint' => null, 'services.billing_whatsapp.token' => null]);
    Carbon::setTestNow('2026-11-25');
    $reminders->deliverPending();
    expect(DB::table('billing_reminders')->where('status', 'unconfigured')->count())->toBe(3)
        ->and(DB::table('billing_reminders')->whereNotNull('sent_at')->count())->toBe(0);
});

it('skips settled invoices and refuses student phone as a guardian fallback', function () {
    [$school, $year, $income, $student] = committeeFixture();
    Carbon::setTestNow('2026-10-11');
    app(BillingService::class)->create($school, committeeAttributes($year, $income));
    $student->update(['parent_phone' => null, 'phone' => '081111111111']);
    $reminders = app(App\Services\BillingReminderService::class);
    $reminders->prepare(Carbon::parse('2026-10-18'));
    Carbon::setTestNow('2026-10-18');
    $reminders->deliverPending();
    expect(DB::table('billing_reminders')->first()->status)->toBe('missing_recipient');
    Invoice::first()->update(['status' => 'Lunas']);
    $reminders->deliverPending();
    expect(DB::table('billing_reminders')->first()->status)->toBe('cancelled');
});

it('does not regenerate legacy one off obligations when the committee scheduler runs', function () {
    [$school, $year, $income, $student] = committeeFixture();
    BillingItem::create([...committeeAttributes($year, $income), 'school_id' => $school, 'billing_frequency' => 'Sekali', 'start_date' => '2026-10-01', 'due_date' => '2026-10-25', 'status' => 'active']);
    Invoice::create(['school_id' => $school, 'student_id' => $student->id, 'academic_year_id' => $year->id, 'invoice_number' => 'LEGACY', 'due_date' => '2026-10-25', 'total_amount' => 300000, 'status' => 'Belum Lunas']);
    app(BillingService::class)->process(Carbon::parse('2026-10-26'));
    expect(Invoice::count())->toBe(1);
});

it('permits existing unscoped invoices only when their student belongs to the active school', function () {
    [$school, $year, $income, $student] = committeeFixture();
    committeeLogin($this, $school);
    $invoice = Invoice::create(['student_id' => $student->id, 'academic_year_id' => $year->id, 'invoice_number' => 'LEGACY-NULL-SCHOOL', 'due_date' => '2026-10-25', 'total_amount' => 300000, 'status' => 'Belum Lunas']);
    $this->post(route('spp.transaksi.pay', $invoice->id), ['amount_paid' => 300000, 'payment_date' => '2026-10-11', 'payment_method' => 'Tunai'])->assertSessionHasNoErrors()->assertSessionHas('payment_success_modal', true);
    expect($invoice->fresh()->status)->toBe('Lunas')->and((int) $invoice->fresh()->school_id)->toBe($school);
});

it('preserves payment errors in the payment context without recording an invalid installment', function () {
    [$school, $year, $income] = committeeFixture();
    Carbon::setTestNow('2026-10-11');
    app(BillingService::class)->create($school, committeeAttributes($year, $income));
    committeeLogin($this, $school);
    $invoice = Invoice::first();
    $this->from(route('finance.billing'))->post(route('spp.transaksi.pay', $invoice->id), ['amount_paid' => 50000, 'payment_date' => '2026-10-11', 'payment_method' => 'Tunai'])
        ->assertSessionHasErrors('amount_paid')->assertSessionHasInput('_billing_action', 'payment')->assertSessionHasInput('invoice_id', $invoice->id)->assertSessionHasInput('amount_paid', 50000);
    $this->get(route('finance.billing'))->assertOk()->assertSee('Pembayaran kurang dari minimal cicilan.');
    expect(DB::table('transactions')->count())->toBe(0);
});

it('posts approved submissions once and updates the same invoice balance', function () {
    [$school, $year, $income, $student] = committeeFixture();
    Carbon::setTestNow('2026-10-11');
    app(BillingService::class)->create($school, committeeAttributes($year, $income));
    $invoice = Invoice::first();
    $user = App\Models\User::factory()->create();
    $payment = App\Models\PaymentSubmission::create(['school_id' => $school, 'student_id' => $student->id, 'invoice_id' => $invoice->id, 'amount' => 100000, 'payment_date' => '2026-10-11', 'payment_method' => 'Transfer Bank', 'status' => 'approved']);
    $service = app(App\Services\FinanceService::class);
    $approval = new App\Models\ApprovalRequest;
    $service->applyApproval($approval, $payment, 'approved', $user->id);
    $service->applyApproval($approval, $payment, 'approved', $user->id);
    expect(DB::table('transactions')->count())->toBe(1)->and($invoice->fresh()->remaining_amount)->toEqual(200000)->and($invoice->fresh()->status)->toBe('Cicilan');
});

it('posts default cash receipts to the canonical account and includes them in its ledger', function () {
    [$school, $year, $income] = committeeFixture();
    Carbon::setTestNow('2026-10-11');
    app(BillingService::class)->create($school, committeeAttributes($year, $income));
    committeeLogin($this, $school);
    $invoice = Invoice::first();
    $account = App\Models\SchoolAccount::where('type', 'Tunai')->first();
    $this->post(route('spp.transaksi.pay', $invoice->id), ['amount_paid' => 100000, 'payment_method' => 'Tunai', 'payment_date' => '2026-10-11'])->assertSessionHasNoErrors();
    expect($account->fresh()->current_balance)->toBe('100000.00')
        ->and(DB::table('transactions')->first()->account_id)->toBe($account->id)
        ->and(app(App\Services\FinanceLedgerService::class)->entries($school, ['account_id' => $account->id])->sum('amount'))->toEqual(100000);
});

it('refuses a committee receipt without an active destination account', function () {
    [$school, $year, $income] = committeeFixture();
    Carbon::setTestNow('2026-10-11');
    app(BillingService::class)->create($school, committeeAttributes($year, $income));
    committeeLogin($this, $school);
    App\Models\SchoolAccount::where('school_id', $school)->update(['is_active' => false]);
    $invoice = Invoice::first();
    $this->post(route('spp.transaksi.pay', $invoice->id), ['amount_paid' => 100000, 'payment_method' => 'Tunai', 'payment_date' => '2026-10-11'])->assertSessionHasErrors('account_id');
    expect(DB::table('transactions')->count())->toBe(0)->and($invoice->fresh()->remaining_amount)->toEqual(300000);
});
