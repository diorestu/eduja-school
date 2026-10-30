<?php

use App\Models\FinanceIncome;
use App\Models\SchoolAccount;
use App\Models\User;
use App\Services\FinanceDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('scopes monthly income yearly bos and account balances to the active school', function () {
    $this->travelTo(now()->setDate(2026, 10, 28));
    $school = DB::table('schools')->insertGetId(['name' => 'Dashboard Finance', 'level' => 'sma', 'ownership' => 'swasta']);
    $other = DB::table('schools')->insertGetId(['name' => 'Other Finance', 'level' => 'sma', 'ownership' => 'swasta']);
    foreach ([[$school, '2026-10-01', 'approved', 100], [$school, '2026-09-01', 'approved', 200], [$school, '2026-10-01', 'pending', 400], [$other, '2026-10-01', 'approved', 800]] as [$id, $date, $status, $amount]) {
        FinanceIncome::create(['school_id' => $id, 'source_funding' => 'BOS Reguler', 'bos_year' => 2026, 'amount' => $amount, 'received_date' => $date, 'status' => $status]);
    }
    foreach ([[$school, 'Tunai', 50], [$school, 'Bank', 75], [$other, 'Bank', 1000]] as [$id, $type, $balance]) {
        SchoolAccount::create(['school_id' => $id, 'name' => $type, 'type' => $type, 'current_balance' => $balance, 'is_active' => true]);
    }
    $summary = app(FinanceDashboardService::class)->forSchool($school);
    expect($summary['incomeMonth'])->toBe(100.0)->and($summary['bosYear'])->toBe(300.0)
        ->and($summary['cashBalance'])->toBe(50.0)->and($summary['bankBalance'])->toBe(75.0);
    $user = User::factory()->create(['role' => 'bendahara']);
    DB::table('school_user_roles')->insert(['school_id' => $school, 'user_id' => $user->id, 'role' => 'bendahara', 'is_active' => true, 'membership_status' => 'active']);
    $response = $this->actingAs($user)->withSession(['active_school_id' => $school])->get('/dashboard')->assertOk();
    $response->assertSeeInOrder(['Total Pemasukan Bulan Ini', 'Saldo BOS', 'Collection Rate per Kelas', 'Kesehatan Keuangan Sekolah']);
    $response->assertSee('Dana BOS Diterima Tahun Ini')->assertSee('Saldo Kas Tunai')->assertSee('Saldo Rekening Sekolah');
    $response->assertDontSee('Ringkasan Keuangan Sekolah')->assertDontSee('Pantau penerimaan, saldo, anggaran, dan pembayaran siswa.');
});

it('calculates collection from accepted receipts and budget realization from linked approved expenses', function () {
    $this->travelTo(now()->setDate(2026, 10, 28));
    $school = DB::table('schools')->insertGetId(['name' => 'Collection School', 'level' => 'sma', 'ownership' => 'swasta']);
    $year = App\Models\AcademicYear::create(['school_id' => $school, 'year' => '2026/2027', 'semester' => 'Ganjil', 'is_active' => true]);
    $class = App\Models\SchoolClass::create(['school_id' => $school, 'academic_year_id' => $year->id, 'name' => 'VII A', 'grade' => 'VII']);
    $student = App\Models\Student::create(['school_id' => $school, 'nis' => 'DASH01', 'name' => 'Dashboard Student', 'gender' => 'P', 'is_active' => true]);
    $class->students()->attach($student);
    $invoice = App\Models\Invoice::create(['school_id' => $school, 'student_id' => $student->id, 'academic_year_id' => $year->id, 'invoice_number' => 'DASH-01', 'due_date' => '2026-10-15', 'total_amount' => 1000, 'status' => 'Cicilan']);
    App\Models\InvoiceItem::create(['invoice_id' => $invoice->id, 'name' => 'SPP Oktober', 'amount' => 1000]);
    foreach (['approved' => 500, 'pending' => 400, 'rejected' => 100] as $status => $amount) {
        App\Models\Transaction::create(['invoice_id' => $invoice->id, 'school_id' => $school, 'amount_paid' => $amount, 'status' => $status, 'receipt_number' => 'DASH-'.$status, 'payment_date' => '2026-10-20', 'payment_method' => 'Tunai']);
    }
    $budgetYear = App\Models\BudgetYear::create(['school_id' => $school, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'active']);
    $plan = App\Models\BudgetPlan::create(['school_id' => $school, 'budget_year_id' => $budgetYear->id, 'source_funding' => 'BOS Reguler', 'program_name' => 'Operasional', 'activity_name' => 'Buku', 'amount' => 1000, 'status' => 'approved']);
    foreach (['approved' => 250, 'pending' => 100] as $status => $amount) {
        App\Models\Expense::create(['school_id' => $school, 'academic_year_id' => $year->id, 'budget_plan_id' => $plan->id, 'source_funding' => 'BOS Reguler', 'expense_name' => 'Buku', 'amount' => $amount, 'transaction_date' => '2026-10-21', 'status' => $status]);
    }
    $summary = app(FinanceDashboardService::class)->forSchool($school);
    expect($summary['incomeMonth'])->toBe(500.0)->and($summary['expenseMonth'])->toBe(250.0)
        ->and($summary['byClass'][0]['percentage'])->toBe(50.0)->and($summary['byBill'][0]['percentage'])->toBe(50.0)
        ->and($summary['trend']['current'][9])->toBe(50.0)->and($summary['trend']['previous'][9])->toBeNull()
        ->and($summary['budgets']['bos']['percentage'])->toBe(25.0)->and($summary['budgets']['bos']['remaining'])->toBe(750.0);
    expect(app(App\Services\FinanceHealthService::class)->forSchool($school)['collection']['percentage'])->toBe(50.0);
});

it('accurately displays student arrears data and status on treasurer and kepsek dashboards', function () {
    $this->travelTo(now()->setDate(2026, 10, 28));
    $school = DB::table('schools')->insertGetId(['name' => 'Arrears Dashboard School', 'level' => 'sma', 'ownership' => 'swasta', 'is_active' => true, 'status' => 'active']);
    $year = App\Models\AcademicYear::create(['school_id' => $school, 'year' => '2026/2027', 'semester' => 'Ganjil', 'is_active' => true]);
    $class = App\Models\SchoolClass::create(['school_id' => $school, 'academic_year_id' => $year->id, 'name' => 'X-1', 'grade' => 'X']);
    $student1 = App\Models\Student::create(['school_id' => $school, 'nis' => 'ARR01', 'name' => 'Student Arrears 1', 'gender' => 'L', 'is_active' => true, 'status' => 'active']);
    $student2 = App\Models\Student::create(['school_id' => $school, 'nis' => 'ARR02', 'name' => 'Student Arrears 2', 'gender' => 'P', 'is_active' => true, 'status' => 'active']);
    $class->students()->attach([$student1->id, $student2->id]);

    // Student 1: invoice 200.000, paid 120.000 (remaining 80.000)
    $inv1 = App\Models\Invoice::create(['school_id' => $school, 'student_id' => $student1->id, 'academic_year_id' => $year->id, 'invoice_number' => 'INV-01', 'due_date' => '2026-10-15', 'total_amount' => 200000, 'status' => 'Cicilan']);
    App\Models\Transaction::create(['invoice_id' => $inv1->id, 'school_id' => $school, 'amount_paid' => 120000, 'status' => 'approved', 'receipt_number' => 'TX-01', 'payment_date' => '2026-10-20', 'payment_method' => 'Tunai']);

    // Student 2: invoice 250.000, paid 0 (remaining 250.000)
    App\Models\Invoice::create(['school_id' => $school, 'student_id' => $student2->id, 'academic_year_id' => $year->id, 'invoice_number' => 'INV-02', 'due_date' => '2026-10-15', 'total_amount' => 250000, 'status' => 'Belum Lunas']);

    $health = app(App\Services\FinanceHealthService::class)->forSchool($school);
    expect($health['totalOutstanding'])->toBe(330000.0)
        ->and($health['delinquentStudents']['count'])->toBe(2)
        ->and($health['arrears']['label'])->toBe('Sangat Tinggi')
        ->and($health['arrears']['label'])->not->toBe('Tidak Ada Tunggakan');

    // 1. Treasurer view
    $treasurer = User::factory()->create(['role' => 'bendahara', 'onboarding_status' => 'active']);
    DB::table('school_user_roles')->insert(['school_id' => $school, 'user_id' => $treasurer->id, 'role' => 'bendahara', 'is_active' => true, 'membership_status' => 'active']);

    $responseTreasurer = $this->actingAs($treasurer)->withSession(['active_school_id' => $school])->get('/dashboard')->assertOk();
    $responseTreasurer->assertSee('Total Tunggakan Siswa')
        ->assertSee('Rp 330.000')
        ->assertSee('Sangat Tinggi')
        ->assertSee('2')
        ->assertDontSee('Tidak Ada Tunggakan');

    // 2. Kepsek / Principal view
    $kepsek = User::factory()->create(['role' => 'kepsek', 'onboarding_status' => 'active']);
    DB::table('school_user_roles')->insert(['school_id' => $school, 'user_id' => $kepsek->id, 'role' => 'kepsek', 'is_active' => true, 'membership_status' => 'active']);

    $responseKepsek = $this->actingAs($kepsek)->withSession(['active_school_id' => $school])->get('/dashboard')->assertOk();
    $responseKepsek->assertSee('Sisa Tunggakan SPP')
        ->assertSee('Rp 330.000')
        ->assertSee('Sangat Tinggi')
        ->assertSee('2 dari 2 murid aktif');

    // 3. When remaining bills are fully paid (no arrears)
    App\Models\Transaction::create(['invoice_id' => $inv1->id, 'school_id' => $school, 'amount_paid' => 80000, 'status' => 'approved', 'receipt_number' => 'TX-02', 'payment_date' => '2026-10-21', 'payment_method' => 'Tunai']);
    $inv2 = App\Models\Invoice::where('invoice_number', 'INV-02')->first();
    App\Models\Transaction::create(['invoice_id' => $inv2->id, 'school_id' => $school, 'amount_paid' => 250000, 'status' => 'approved', 'receipt_number' => 'TX-03', 'payment_date' => '2026-10-21', 'payment_method' => 'Tunai']);

    $healthClean = app(App\Services\FinanceHealthService::class)->forSchool($school);
    expect($healthClean['totalOutstanding'])->toBe(0.0)
        ->and($healthClean['delinquentStudents']['count'])->toBe(0)
        ->and($healthClean['arrears']['label'])->toBe('Tidak Ada Tunggakan');

    $responseClean = $this->actingAs($treasurer)->withSession(['active_school_id' => $school])->get('/dashboard')->assertOk();
    $responseClean->assertSee('Total Tunggakan Siswa')
        ->assertSee('Rp 0')
        ->assertSee('Tidak Ada Tunggakan');
});
