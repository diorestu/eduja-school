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
