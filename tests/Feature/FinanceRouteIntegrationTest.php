<?php

use App\Helpers\MenuHelper;
use App\Models\FinanceIncome;
use App\Models\User;
use App\Services\FinanceHealthService;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function makeFinanceRouteSchool(string $name): int
{
    return DB::table('schools')->insertGetId([
        'name' => $name,
        'level' => 'sma',
        'ownership' => 'swasta',
        'status' => 'active',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function makeFinanceRouteUser(int $schoolId, string $role = 'bendahara'): User
{
    $user = User::factory()->create(['role' => $role]);

    DB::table('school_user_roles')->insert([
        'user_id' => $user->id,
        'school_id' => $schoolId,
        'role' => $role,
        'is_active' => true,
        'membership_status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $user;
}

it('exposes the complete finance workflow through protected routes and menu links', function () {
    $schoolId = makeFinanceRouteSchool('Sekolah Finance Routes');
    $user = makeFinanceRouteUser($schoolId);
    $this->actingAs($user)->withSession(['active_school_id' => $schoolId]);

    $paths = [
        '/finance/accounts',
        '/finance/income-types',
        '/finance/expense-types',
        '/finance/allocations',
        '/finance/budget-years',
        '/finance/budgets',
        '/finance/approvals',
        '/finance/billing',
        '/finance/closing',
        '/finance/ledger',
        '/finance/reports',
    ];

    foreach ($paths as $path) {
        $this->get($path)->assertOk();
    }

});

it('organizes the bendahara navigation around the requested finance workflow', function () {
    $schoolId = makeFinanceRouteSchool('Sekolah Menu Bendahara');
    $user = makeFinanceRouteUser($schoolId);
    $this->actingAs($user)->withSession(['active_school_id' => $schoolId]);

    $items = collect(MenuHelper::getMainNavItems());

    expect($items->pluck('name')->all())->toContain(
        'Master Data Keuangan',
        'Perencanaan Anggaran',
        'Tagihan',
        'Pemasukan',
        'Pengeluaran',
        'Approval',
        'Dompet Virtual',
        'Laporan',
        'Tutup Buku',
    );

    expect(collect($items->firstWhere('name', 'Master Data Keuangan')['subItems'])->pluck('name')->all())
        ->toEqual(['Rekening Sekolah', 'Jenis Pemasukan', 'BOS', 'Jenis Pengeluaran']);
    expect(collect($items->firstWhere('name', 'Perencanaan Anggaran')['subItems'])->pluck('name')->all())
        ->toEqual(['Tahun Anggaran', 'Susun Anggaran', 'Revisi Anggaran']);
});

it('runs finance master and budget workflows with active-school foreign keys', function () {
    $schoolId = makeFinanceRouteSchool('Sekolah Finance A');
    $otherSchoolId = makeFinanceRouteSchool('Sekolah Finance B');
    $user = makeFinanceRouteUser($schoolId);
    $otherAccount = app(FinanceService::class)->createAccount($otherSchoolId, ['name' => 'Kas Sekolah B']);

    $this->actingAs($user)->withSession(['active_school_id' => $schoolId]);

    $this->post(route('finance.accounts.store'), [
        'name' => 'Kas Operasional',
        'type' => 'Tunai',
        'opening_balance' => 500000,
    ])->assertRedirect();
    $accountId = DB::table('school_accounts')->where('school_id', $schoolId)->value('id');

    $this->post(route('finance.income-types.store'), [
        'name' => 'BOS Reguler',
        'category' => 'BOS',
        'uses_allocation' => '1',
    ])->assertRedirect();
    $incomeTypeId = DB::table('income_types')->where('school_id', $schoolId)->value('id');

    $this->post(route('finance.expense-types.store'), [
        'name' => 'Belanja ATK',
        'source_funding' => 'BOS',
        'bos_component' => 'Barang',
        'requires_approval' => '1',
    ])->assertRedirect();

    $this->post(route('finance.allocations.store'), [
        'income_type_id' => $incomeTypeId,
        'account_id' => $accountId,
        'name' => 'Operasional Sekolah',
        'method' => 'nominal',
        'amount' => 250000,
    ])->assertRedirect();

    $this->post(route('finance.allocations.store'), [
        'income_type_id' => $incomeTypeId,
        'account_id' => $otherAccount->id,
        'name' => 'Lintas sekolah harus ditolak',
        'method' => 'nominal',
        'amount' => 100000,
    ])->assertSessionHasErrors('account_id');

    $this->post(route('finance.budget-years.store'), [
        'name' => 'Tahun Anggaran 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ])->assertRedirect();
    $budgetYearId = DB::table('budget_years')->where('school_id', $schoolId)->value('id');

    $this->post(route('finance.budgets.store'), [
        'budget_year_id' => $budgetYearId,
        'source_funding' => 'BOS',
        'program_name' => 'Operasional Sekolah',
        'activity_name' => 'Belanja ATK',
        'amount' => 1000000,
    ])->assertRedirect();
    $budgetPlanId = DB::table('budget_plans')->where('school_id', $schoolId)->value('id');

    $this->post(route('finance.budgets.revisions.store', $budgetPlanId), [
        'new_amount' => 1250000,
        'reason' => 'Penyesuaian kebutuhan semester berjalan',
    ])->assertRedirect();

    expect(DB::table('fund_allocations')->where('school_id', $schoolId)->count())->toBe(1)
        ->and(DB::table('budget_plan_revisions')->where('school_id', $schoolId)->count())->toBe(1)
        ->and(DB::table('budget_plans')->where('school_id', $schoolId)->value('status'))->toBe('pending');
});

it('renders approved ledger and report data only for the active school', function () {
    $schoolId = makeFinanceRouteSchool('Sekolah Ledger A');
    $otherSchoolId = makeFinanceRouteSchool('Sekolah Ledger B');
    $user = makeFinanceRouteUser($schoolId);
    $service = app(FinanceService::class);
    $account = $service->createAccount($schoolId, ['name' => 'Kas Sekolah A']);
    $otherAccount = $service->createAccount($otherSchoolId, ['name' => 'Kas Sekolah B']);
    $income = $service->recordBosIncome($schoolId, [
        'source_funding' => 'BOS Sekolah A',
        'amount' => 500000,
        'received_date' => '2026-07-10',
        'account_id' => $account->id,
    ]);
    $service->recordBosIncome($otherSchoolId, [
        'source_funding' => 'BOS Sekolah B',
        'amount' => 900000,
        'received_date' => '2026-07-10',
        'account_id' => $otherAccount->id,
    ]);
    FinanceIncome::query()->create([
        'school_id' => $schoolId,
        'source_funding' => 'Belum disetujui',
        'amount' => 700000,
        'received_date' => '2026-07-11',
        'status' => 'pending',
    ]);

    $this->actingAs($user)->withSession(['active_school_id' => $schoolId]);

    $this->get('/finance/ledger')
        ->assertOk()
        ->assertViewHas('ledger', function ($ledger) use ($income) {
            return collect($ledger)->pluck('id')->contains($income->id)
                && ! collect($ledger)->pluck('description')->contains('Penerimaan BOS Sekolah B')
                && ! collect($ledger)->pluck('description')->contains('Penerimaan Belum disetujui');
        });

    $this->get('/finance/reports')
        ->assertOk()
        ->assertViewHas('summary', fn (array $summary) => $summary['collected'] === 500000.0);
});

it('closes the active school period through the finance controller', function () {
    $schoolId = makeFinanceRouteSchool('Sekolah Closing');
    $user = makeFinanceRouteUser($schoolId);

    $this->actingAs($user)
        ->withSession(['active_school_id' => $schoolId])
        ->post(route('finance.closing.store'), [
            'type' => 'monthly',
            'period' => '2026-07',
        ])
        ->assertRedirect();

    expect(DB::table('book_closings')->where('school_id', $schoolId)->where('period', '2026-07')->value('status'))->toBe('closed');
});

it('classifies finance collection and arrears health at the specified boundaries', function () {
    $schoolId = makeFinanceRouteSchool('Sekolah Kesehatan Keuangan');
    $user = makeFinanceRouteUser($schoolId);
    $studentA = DB::table('students')->insertGetId([
        'school_id' => $schoolId,
        'nis' => 'FIN-001',
        'name' => 'Siswa Lunas',
        'gender' => 'L',
        'is_active' => true,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $studentB = DB::table('students')->insertGetId([
        'school_id' => $schoolId,
        'nis' => 'FIN-002',
        'name' => 'Siswa Menunggak',
        'gender' => 'P',
        'is_active' => true,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $academicYearId = DB::table('academic_years')->insertGetId([
        'year' => '2026/2027',
        'semester' => 'Ganjil',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $invoiceA = DB::table('invoices')->insertGetId([
        'school_id' => $schoolId,
        'student_id' => $studentA,
        'academic_year_id' => $academicYearId,
        'invoice_number' => 'INV-FIN-001',
        'due_date' => now()->toDateString(),
        'total_amount' => 100000,
        'status' => 'Lunas',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $invoiceB = DB::table('invoices')->insertGetId([
        'school_id' => $schoolId,
        'student_id' => $studentB,
        'academic_year_id' => $academicYearId,
        'invoice_number' => 'INV-FIN-002',
        'due_date' => now()->toDateString(),
        'total_amount' => 100000,
        'status' => 'Cicilan',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    foreach ([[$invoiceA, 100000], [$invoiceB, 50000]] as [$invoiceId, $amount]) {
        DB::table('transactions')->insert([
            'school_id' => $schoolId,
            'invoice_id' => $invoiceId,
            'amount_paid' => $amount,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Tunai',
            'receipt_number' => 'RCP-FIN-'.$invoiceId,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $health = app(FinanceHealthService::class)->forSchool($schoolId);

    expect($health['collection']['percentage'])->toBe(75.0)
        ->and($health['collection']['label'])->toBe('Lancar')
        ->and($health['arrears']['percentage'])->toBe(25.0)
        ->and($health['arrears']['label'])->toBe('Perlu Ditangani')
        ->and($health['delinquentStudents']['percentage'])->toBe(50.0)
        ->and($health['delinquentStudents']['count'])->toBe(1);

    $this->actingAs($user)->withSession(['active_school_id' => $schoolId]);

    $this->get('/dashboard')->assertOk()
        ->assertViewHas('financeHealth', fn (array $data) => $data['collection']['label'] === 'Lancar');
});
