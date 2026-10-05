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
        'Laporan',
        'Tutup Buku',
    );

    expect(collect($items->firstWhere('name', 'Master Data Keuangan')['subItems'])->pluck('name')->all())
        ->toEqual(['Rekening Sekolah', 'Dompet Virtual', 'Jenis Pemasukan', 'BOS', 'Jenis Pengeluaran']);
    expect(collect($items->firstWhere('name', 'Perencanaan Anggaran')['subItems'])->pluck('name')->all())
        ->toEqual(['Tahun Anggaran', 'Susun Anggaran', 'Revisi Anggaran']);

    foreach ([
        'Tagihan' => '/finance/billing',
        'Pemasukan' => '/spp/transaksi',
        'Pengeluaran' => '/bos/belanja',
        'Approval' => '/finance/approvals',
        'Laporan' => '/finance/reports',
        'Tutup Buku' => '/finance/closing',
    ] as $name => $path) {
        $item = $items->firstWhere('name', $name);

        expect($item)->toHaveKey('path', $path)
            ->and($item)->not->toHaveKey('subItems');
    }
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

    $response = $this->get('/dashboard')->assertOk()
        ->assertDontSee('Ringkasan Operasional Sekolah')
        ->assertSee('Kesehatan Penagihan SPP')
        ->assertSee('id="tour-financial-metrics"', false)
        ->assertSee('id="treasurer-finance-chart"', false)
        ->assertSee('id="treasurer-netflow-chart"', false)
        ->assertViewHas('financeHealth', fn (array $data) => $data['collection']['label'] === 'Lancar');

    $html = $response->getContent();
    $counterPos = strpos($html, 'id="tour-financial-metrics"');
    $healthPos = strpos($html, 'Kesehatan Penagihan SPP');
    $chartPos = strpos($html, 'id="treasurer-finance-chart"');
    expect($counterPos)->not->toBeFalse()
        ->and($healthPos)->not->toBeFalse()
        ->and($chartPos)->not->toBeFalse()
        ->and($counterPos)->toBeLessThan($healthPos)
        ->and($healthPos)->toBeLessThan($chartPos);
});

it('implements the complete Master Jenis Pemasukan workflow matching requirements', function () {
    $schoolId = makeFinanceRouteSchool('Sekolah Jenis Pemasukan');
    $otherSchoolId = makeFinanceRouteSchool('Sekolah Lain');
    $user = makeFinanceRouteUser($schoolId);

    $this->actingAs($user)->withSession(['active_school_id' => $schoolId]);

    // 1. Initial page view
    $response = $this->get(route('finance.income-types'))->assertOk();
    $response->assertSee('Master Data Keuangan');
    $response->assertSee('Jenis Pemasukan');
    $response->assertDontSee('+ Jenis Pemasukan');
    $response->assertSee('Tambah Jenis Pemasukan');
    $response->assertSee('JP0001');
    $response->assertSee('Menggunakan Alokasi Dana');
    $response->assertSee('Perlu Approval Kepala Sekolah');

    // 2. Validation: required fields
    $this->post(route('finance.income-types.store'), [
        'name' => '',
        'category' => '',
    ])->assertSessionHasErrors([
        'name' => 'Nama jenis pemasukan wajib diisi.',
        'category' => 'Kategori wajib dipilih.',
    ]);

    // 3. Successful store with auto-generated code and boolean options
    $this->post(route('finance.income-types.store'), [
        'name' => 'SPP',
        'category' => 'Komite',
        'uses_allocation' => '0',
        'requires_approval' => '0',
    ])->assertRedirect(route('finance.income-types'))
      ->assertSessionHas('success_modal', true)
      ->assertSessionHas('success', 'Data jenis pemasukan berhasil disimpan ke dalam sistem.');

    $created = DB::table('income_types')->where('school_id', $schoolId)->where('name', 'SPP')->first();
    expect($created)->not->toBeNull()
        ->and($created->code)->toBe('JP0001')
        ->and($created->category)->toBe('Komite')
        ->and((bool) $created->uses_allocation)->toBeFalse()
        ->and((bool) $created->requires_approval)->toBeFalse();

    // 4. Duplicate name validation
    $this->post(route('finance.income-types.store'), [
        'name' => 'SPP',
        'category' => 'Komite',
    ])->assertSessionHasErrors([
        'name' => 'Jenis pemasukan dengan nama "SPP" sudah ada.',
    ]);

    // 5. Next code advances to JP0002
    $response = $this->get(route('finance.income-types'))->assertOk();
    $response->assertSee('JP0002');
    $response->assertSee('Ubah Jenis Pemasukan');
    $response->assertDontSee('method="POST" class="inline"'); // no delete form in table

    // 6. Update income type via PUT
    $this->put(route('finance.income-types.update', $created->id), [
        'name' => 'SPP Bulanan',
        'category' => 'Komite',
        'uses_allocation' => '1',
        'requires_approval' => '1',
    ])->assertRedirect(route('finance.income-types'))
      ->assertSessionHas('success', 'Data jenis pemasukan berhasil diperbarui.');

    $updated = DB::table('income_types')->where('id', $created->id)->first();
    expect($updated->name)->toBe('SPP Bulanan')
        ->and((bool) $updated->uses_allocation)->toBeTrue()
        ->and((bool) $updated->requires_approval)->toBeTrue();

    // 7. Security: cannot update income type of other school
    $otherType = DB::table('income_types')->insertGetId([
        'school_id' => $otherSchoolId,
        'code' => 'JP0001',
        'name' => 'SPP Lain',
        'category' => 'Komite',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->put(route('finance.income-types.update', $otherType), [
        'name' => 'SPP Hack',
        'category' => 'Komite',
    ])->assertForbidden();

    // 8. Account Datatable has edit action and update works
    $account = app(FinanceService::class)->createAccount($schoolId, [
        'name' => 'Kas Bendahara Utama',
        'type' => 'Tunai',
        'opening_balance' => 1000000,
    ]);

    $accountResponse = $this->get(route('finance.accounts'))->assertOk();
    $accountResponse->assertSee('Ubah Rekening Sekolah');
    $accountResponse->assertDontSee('data-table-action-icon text-red-500');

    $this->put(route('finance.accounts.update', $account->id), [
        'name' => 'Kas Bendahara Revisi',
        'type' => 'Bank',
        'bank_name' => 'Bank Central Asia (BCA)',
        'account_number' => '987654321',
        'is_active' => '1',
    ])->assertRedirect(route('finance.accounts'))
      ->assertSessionHas('success', 'Rekening Sekolah berhasil diperbarui.');

    $updatedAccount = DB::table('school_accounts')->where('id', $account->id)->first();
    expect($updatedAccount->name)->toBe('Kas Bendahara Revisi')
        ->and($updatedAccount->type)->toBe('Bank')
        ->and($updatedAccount->bank_name)->toBe('Bank Central Asia (BCA)')
        ->and($updatedAccount->account_number)->toBe('987654321');
});

it('implements datatable ui for perencanaan anggaran with clean antislop standards', function () {
    $schoolId = makeFinanceRouteSchool('Sekolah Perencanaan Anggaran');
    $otherSchoolId = makeFinanceRouteSchool('Sekolah Lain');
    $user = makeFinanceRouteUser($schoolId);
    $this->actingAs($user)->withSession(['active_school_id' => $schoolId]);

    // 1. Budget Years Page renders modern data-table UI and breadcrumbs
    $responseYears = $this->get(route('finance.budget-years'))->assertOk();
    $responseYears->assertSee('Tahun Anggaran');
    $responseYears->assertSee('Perencanaan Anggaran');
    $responseYears->assertSee('data-table');
    $responseYears->assertDontSee('+ Tahun Anggaran');

    // 2. Create budget year
    $this->post(route('finance.budget-years.store'), [
        'name' => 'Tahun Anggaran 2026/2027',
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
    ])->assertRedirect()->assertSessionHas('success');

    $year = DB::table('budget_years')->where('school_id', $schoolId)->first();
    expect($year)->not->toBeNull()
        ->and($year->name)->toBe('Tahun Anggaran 2026/2027')
        ->and($year->status)->toBe('active');

    // 3. Update budget year
    $this->put(route('finance.budget-years.update', $year->id), [
        'name' => 'Tahun Anggaran 2026/2027 Revisi',
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
        'status' => 'closed',
    ])->assertRedirect()->assertSessionHas('success');

    $updatedYear = DB::table('budget_years')->where('id', $year->id)->first();
    expect($updatedYear->name)->toBe('Tahun Anggaran 2026/2027 Revisi')
        ->and($updatedYear->status)->toBe('closed');

    // 4. Budgets Page (Susun & Revisi Anggaran) renders modern data-table UI
    $responseBudgets = $this->get(route('finance.budgets'))->assertOk();
    $responseBudgets->assertSee('Susun & Revisi Anggaran');
    $responseBudgets->assertSee('Perencanaan Anggaran');
    $responseBudgets->assertSee('data-table');
    $responseBudgets->assertDontSee('+ Rencana Anggaran');

    // 5. Store budget plan
    $this->post(route('finance.budgets.store'), [
        'budget_year_id' => $year->id,
        'source_funding' => 'BOS',
        'program_name' => 'Program Pengembangan Kurikulum',
        'activity_name' => 'Pengadaan Modul Ajar',
        'amount' => 5000000,
    ])->assertRedirect()->assertSessionHas('success');

    $plan = DB::table('budget_plans')->where('school_id', $schoolId)->first();
    expect($plan)->not->toBeNull()
        ->and($plan->program_name)->toBe('Program Pengembangan Kurikulum')
        ->and((float) $plan->amount)->toBe(5000000.0);

    // 6. Update budget plan details
    $this->put(route('finance.budgets.update', $plan->id), [
        'budget_year_id' => $year->id,
        'source_funding' => 'BOS',
        'program_name' => 'Program Pengembangan Kurikulum & Asesmen',
        'activity_name' => 'Pengadaan Modul Ajar & Ujian',
        'amount' => 6000000,
    ])->assertRedirect()->assertSessionHas('success');

    $updatedPlan = DB::table('budget_plans')->where('id', $plan->id)->first();
    expect($updatedPlan->program_name)->toBe('Program Pengembangan Kurikulum & Asesmen')
        ->and((float) $updatedPlan->amount)->toBe(6000000.0);

    // 7. Store revision
    $this->post(route('finance.budgets.revisions.store', $plan->id), [
        'new_amount' => 7500000,
        'reason' => 'Penambahan kuota modul kelas baru',
    ])->assertRedirect()->assertSessionHas('success');

    expect(DB::table('budget_plan_revisions')->where('budget_plan_id', $plan->id)->count())->toBe(1);

    // 8. Cross-school protection
    $otherYear = DB::table('budget_years')->insertGetId([
        'school_id' => $otherSchoolId,
        'name' => 'TA Sekolah Lain',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->put(route('finance.budget-years.update', $otherYear), [
        'name' => 'Hacked TA',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'status' => 'active',
    ])->assertNotFound();
});

it('creates and updates income types with dynamic fund allocations including persentase and nominal methods', function () {
    $schoolId = makeFinanceRouteSchool('Sekolah Alokasi Pemasukan');
    $user = makeFinanceRouteUser($schoolId);
    $this->actingAs($user)->withSession(['active_school_id' => $schoolId]);

    $service = app(FinanceService::class);
    $accKas = $service->createAccount($schoolId, ['name' => 'Kas Operasional', 'type' => 'Tunai']);
    $accBank = $service->createAccount($schoolId, ['name' => 'Bank Mandiri SPP', 'type' => 'Bank', 'bank_name' => 'Bank Mandiri', 'account_number' => '12345']);

    // 1. Check page contains allocation form fields and total row elements
    $response = $this->get(route('finance.income-types'))->assertOk();
    $response->assertSee('Form Alokasi Dana');
    $response->assertSee('Total Alokasi:');
    $response->assertSee('Dompet Penyimpanan');
    $response->assertSee('Kas Operasional');
    $response->assertSee('Bank Mandiri SPP');
    $response->assertDontSee('+ Tambah Alokasi');

    // 2. Create income type with percentage allocations
    $this->post(route('finance.income-types.store'), [
        'name' => 'SPP Bulanan 2026',
        'category' => 'Komite',
        'uses_allocation' => '1',
        'requires_approval' => '0',
        'allocations' => [
            [
                'name' => 'Alokasi Kas Harian',
                'method' => 'persentase',
                'amount' => 40,
                'account_id' => $accKas->id,
            ],
            [
                'name' => 'Alokasi Bank Mandiri',
                'method' => 'persentase',
                'amount' => 60,
                'account_id' => $accBank->id,
            ],
        ],
    ])->assertRedirect(route('finance.income-types'));

    $incomeType = DB::table('income_types')->where('name', 'SPP Bulanan 2026')->first();
    expect($incomeType)->not->toBeNull()
        ->and((bool) $incomeType->uses_allocation)->toBeTrue();

    $allocations = DB::table('fund_allocations')->where('income_type_id', $incomeType->id)->get();
    expect($allocations)->toHaveCount(2)
        ->and($allocations->firstWhere('name', 'Alokasi Kas Harian')->method)->toBe('persentase')
        ->and((float) $allocations->firstWhere('name', 'Alokasi Kas Harian')->amount)->toBe(40.0)
        ->and((int) $allocations->firstWhere('name', 'Alokasi Kas Harian')->account_id)->toBe($accKas->id)
        ->and((float) $allocations->firstWhere('name', 'Alokasi Bank Mandiri')->amount)->toBe(60.0);

    // 3. Update income type with nominal allocation
    $this->put(route('finance.income-types.update', $incomeType->id), [
        'name' => 'SPP Bulanan 2026 Revisi',
        'category' => 'Komite',
        'uses_allocation' => '1',
        'requires_approval' => '1',
        'allocations' => [
            [
                'name' => 'Porsi Tetap Operasional',
                'method' => 'nominal',
                'amount' => 500000,
                'account_id' => $accKas->id,
            ],
        ],
    ])->assertRedirect(route('finance.income-types'));

    $updatedAllocations = DB::table('fund_allocations')->where('income_type_id', $incomeType->id)->get();
    expect($updatedAllocations)->toHaveCount(1)
        ->and($updatedAllocations->first()->name)->toBe('Porsi Tetap Operasional')
        ->and($updatedAllocations->first()->method)->toBe('nominal')
        ->and((float) $updatedAllocations->first()->amount)->toBe(500000.0);

    // 4. Update income type to disable allocation -> removes allocations
    $this->put(route('finance.income-types.update', $incomeType->id), [
        'name' => 'SPP Bulanan 2026 Non Alokasi',
        'category' => 'Komite',
        'uses_allocation' => '0',
    ])->assertRedirect(route('finance.income-types'));

    expect(DB::table('fund_allocations')->where('income_type_id', $incomeType->id)->count())->toBe(0);
});

it('separates Susun Anggaran and Revisi Anggaran routes and navigates independently', function () {
    $schoolId = makeFinanceRouteSchool('SMK Bina Karya');
    $user = makeFinanceRouteUser($schoolId);

    $this->actingAs($user)->withSession(['active_school_id' => $schoolId]);

    // Check menu helper distinct paths
    $items = collect(MenuHelper::getMainNavItems());
    $perencanaan = collect($items->firstWhere('name', 'Perencanaan Anggaran')['subItems'] ?? []);

    $susun = $perencanaan->firstWhere('name', 'Susun Anggaran');
    $revisi = $perencanaan->firstWhere('name', 'Revisi Anggaran');

    expect($susun['path'])->toBe('/finance/budgets')
        ->and($revisi['path'])->toBe('/finance/budgets/revisions');

    // Both endpoints respond OK and with distinct page titles
    $respSusun = $this->get('/finance/budgets')->assertOk();
    $respSusun->assertSee('Perencanaan Anggaran');
    $respSusun->assertSee('data-table');

    $respRevisi = $this->get('/finance/budgets/revisions')->assertOk();
    $respRevisi->assertSee('Revisi Anggaran');
    $respRevisi->assertSee('data-table');
});

it('renders modern data-table on tagihan, pemasukan, pengeluaran, and approvals with antislop standards and no literal plus buttons', function () {
    $schoolId = makeFinanceRouteSchool('SMA Horizon');
    $user = makeFinanceRouteUser($schoolId);

    $activeYearId = DB::table('academic_years')->insertGetId([
        'year' => '2026/2027',
        'semester' => 'Ganjil',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)->withSession(['active_school_id' => $schoolId]);

    // 1. Tagihan (/finance/billing) with actual incomeType relationship
    $incomeType = \App\Models\IncomeType::create([
        'school_id' => $schoolId,
        'name' => 'SPP Reguler',
        'category' => 'Komite',
        'code' => 'JP0001',
    ]);

    \App\Models\BillingItem::create([
        'school_id' => $schoolId,
        'income_type_id' => $incomeType->id,
        'name' => 'SPP Kelas X Oktober',
        'amount' => 350000,
        'status' => 'active',
        'billing_frequency' => 'Bulanan',
    ]);

    $respBilling = $this->get('/finance/billing')->assertOk();
    $respBilling->assertSee('Tagihan Siswa &amp; Komite', false);
    $respBilling->assertSee('SPP Kelas X Oktober', false);
    $respBilling->assertSee('SPP Reguler', false);
    $respBilling->assertSee('data-table', false);
    expect(preg_match('/<button[^>]*>\s*\+/', $respBilling->getContent()))->toBe(0);

    // 2. Pemasukan (/spp/transaksi)
    $respPemasukan = $this->get('/spp/transaksi')->assertOk();
    $respPemasukan->assertSee('Transaksi Keuangan SPP &amp; Pemasukan', false);
    $respPemasukan->assertSee('data-table', false);
    expect(preg_match('/<button[^>]*>\s*\+/', $respPemasukan->getContent()))->toBe(0);

    // 3. Pengeluaran (/bos/belanja)
    $respBelanja = $this->get('/bos/belanja')->assertOk();
    $respBelanja->assertSee('Pencatatan Belanja &amp; Operasional Sekolah', false);
    $respBelanja->assertSee('data-table', false);
    expect(preg_match('/<button[^>]*>\s*\+/', $respBelanja->getContent()))->toBe(0);

    // 4. Approval (/finance/approvals)
    $respApprovals = $this->get('/finance/approvals')->assertOk();
    $respApprovals->assertSee('Antrian Approval Keuangan', false);
    $respApprovals->assertSee('data-table', false);
    expect(preg_match('/<button[^>]*>\s*\+/', $respApprovals->getContent()))->toBe(0);

    // 5. Test update belanja
    $expenseId = DB::table('expenses')->insertGetId([
        'school_id' => $schoolId,
        'academic_year_id' => $activeYearId,
        'expense_name' => 'Pembelian Buku Perpustakaan',
        'amount' => 2000000,
        'transaction_date' => '2026-10-01',
        'source_funding' => 'BOS',
        'payment_method' => 'Transfer',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->put(route('bos.belanja.update', $expenseId), [
        'expense_name' => 'Pembelian Buku Perpustakaan Revisi',
        'amount' => 2500000,
        'transaction_date' => '2026-10-02',
        'source_funding' => 'BOS',
        'payment_method' => 'Transfer',
        'recipient_name' => 'Toko Gramedia',
    ])->assertRedirect()->assertSessionHas('success', 'Transaksi pengeluaran berhasil diperbarui.');

    expect(DB::table('expenses')->where('id', $expenseId)->value('expense_name'))->toBe('Pembelian Buku Perpustakaan Revisi')
        ->and((float) DB::table('expenses')->where('id', $expenseId)->value('amount'))->toBe(2500000.0);

    // 6. Tutup Buku (/finance/closing) dedicated view
    $respClosing = $this->get('/finance/closing')->assertOk();
    $respClosing->assertSee('Tutup Buku &amp; Penguncian Periode', false);
    $respClosing->assertSee('Pemeriksaan Kesiapan Tutup Buku', false);
    $respClosing->assertSee('data-table', false);
    expect(preg_match('/<button[^>]*>\s*\+/', $respClosing->getContent()))->toBe(0);

    // 7. Laporan Keuangan (/finance/reports) dedicated view
    $respReports = $this->get('/finance/reports')->assertOk();
    $respReports->assertSee('Laporan Keuangan', false);
    $respReports->assertSee('Rekapitulasi Arus Kas per Sumber Dana', false);
    $respReports->assertSee('Posisi Saldo Kas', false);
    $respReports->assertSee('data-table', false);
    expect(preg_match('/<button[^>]*>\s*\+/', $respReports->getContent()))->toBe(0);
});

it('implements CRUD for virtual wallets as special school fund buckets under master data keuangan', function () {
    $schoolId = makeFinanceRouteSchool('Sekolah Dompet Virtual');
    $user = makeFinanceRouteUser($schoolId);
    $this->actingAs($user)->withSession(['active_school_id' => $schoolId]);

    // 1. Visit virtual wallets page
    $response = $this->get(route('finance.virtual-wallets'))->assertOk();
    $response->assertSee('Dompet Virtual');
    $response->assertSee('Master Data Keuangan');
    $response->assertSee('Kantong-kantong dompet virtual');
    $response->assertSee('data-table', false);

    // 2. Store virtual wallet
    $this->post(route('finance.virtual-wallets.store'), [
        'name' => 'Kantong Perawatan Gedung',
        'nominal' => 3500000,
        'status' => 'active',
        'source' => 'Dana Komite',
        'notes' => 'Keperluan perbaikan fasilitas ruang kelas',
    ])->assertRedirect(route('finance.virtual-wallets'))->assertSessionHas('success');

    $wallet = DB::table('virtual_wallets')->where('school_id', $schoolId)->first();
    expect($wallet)->not->toBeNull()
        ->and($wallet->name)->toBe('Kantong Perawatan Gedung')
        ->and((float) $wallet->nominal)->toBe(3500000.0)
        ->and($wallet->status)->toBe('active')
        ->and($wallet->source)->toBe('Dana Komite');

    // 3. Update virtual wallet
    $this->put(route('finance.virtual-wallets.update', $wallet->id), [
        'name' => 'Kantong Perawatan Gedung & Fasilitas',
        'nominal' => 4000000,
        'status' => 'active',
        'source' => 'Dana Komite & Hibah',
        'notes' => 'Catatan revisi',
    ])->assertRedirect(route('finance.virtual-wallets'))->assertSessionHas('success');

    $updated = DB::table('virtual_wallets')->where('id', $wallet->id)->first();
    expect($updated->name)->toBe('Kantong Perawatan Gedung & Fasilitas')
        ->and((float) $updated->nominal)->toBe(4000000.0)
        ->and($updated->source)->toBe('Dana Komite & Hibah');

    // 4. Delete virtual wallet
    $this->delete(route('finance.virtual-wallets.destroy', $wallet->id))
        ->assertRedirect(route('finance.virtual-wallets'))->assertSessionHas('success');
    expect(DB::table('virtual_wallets')->where('id', $wallet->id)->count())->toBe(0);
});

it('links virtual wallet as storage destination for income type fund allocations', function () {
    $schoolId = makeFinanceRouteSchool('Sekolah Kantong Alokasi');
    $user = makeFinanceRouteUser($schoolId);
    $this->actingAs($user)->withSession(['active_school_id' => $schoolId]);

    // Create 2 virtual wallets
    $wallet1Id = DB::table('virtual_wallets')->insertGetId([
        'school_id' => $schoolId,
        'name' => 'Kantong Ekstrakurikuler',
        'nominal' => 1000000,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $wallet2Id = DB::table('virtual_wallets')->insertGetId([
        'school_id' => $schoolId,
        'name' => 'Kantong Kesejahteraan Guru',
        'nominal' => 2000000,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Check income types page lists virtual wallets in allocation form
    $resp = $this->get(route('finance.income-types'))->assertOk();
    $resp->assertSee('Kantong Ekstrakurikuler');
    $resp->assertSee('Kantong Kesejahteraan Guru');

    // Store income type using virtual wallets
    $this->post(route('finance.income-types.store'), [
        'name' => 'Uang Praktikum Lab',
        'category' => 'Komite',
        'uses_allocation' => '1',
        'allocations' => [
            [
                'name' => 'Porsi Kegiatan',
                'method' => 'persentase',
                'amount' => 50,
                'virtual_wallet_id' => $wallet1Id,
            ],
            [
                'name' => 'Porsi Kesejahteraan',
                'method' => 'persentase',
                'amount' => 50,
                'virtual_wallet_id' => $wallet2Id,
            ],
        ],
    ])->assertRedirect(route('finance.income-types'));

    $incomeType = DB::table('income_types')->where('name', 'Uang Praktikum Lab')->first();
    expect($incomeType)->not->toBeNull();

    $allocations = DB::table('fund_allocations')->where('income_type_id', $incomeType->id)->get();
    expect($allocations)->toHaveCount(2);

    $alloc1 = $allocations->firstWhere('name', 'Porsi Kegiatan');
    expect($alloc1->virtual_wallet_id)->toBe($wallet1Id);

    // Verify virtual wallet source was updated with the income type name
    $wallet1 = DB::table('virtual_wallets')->where('id', $wallet1Id)->first();
    expect($wallet1->source)->toBe('Uang Praktikum Lab');
});

it('places login assistance link below password input on signin page', function () {
    $response = $this->get('/signin')->assertOk();
    $content = $response->getContent();

    $passwordPos = strpos($content, 'id="password"');
    $helpPos = strpos($content, 'Butuh bantuan masuk?');

    expect($passwordPos)->not->toBeFalse();
    expect($helpPos)->not->toBeFalse();
    expect($helpPos)->toBeGreaterThan($passwordPos);
});

it('implements Flow Master BOS to manage fund sources and components with modal and validation', function () {
    $schoolId = makeFinanceRouteSchool('Sekolah Master BOS');
    $user = makeFinanceRouteUser($schoolId);
    $this->actingAs($user)->withSession(['active_school_id' => $schoolId]);

    // 1. Visit BOS page from Master Data Keuangan
    $response = $this->get(route('bos.anggaran.index'))->assertOk();
    $response->assertSee('Master Data Keuangan');
    $response->assertSee('Data Sumber Dana BOS');
    $response->assertSee('Tambah BOS');
    $response->assertSee('BOS Reguler');
    $response->assertSee('Belanja pegawai');
    $response->assertSee('Sumber dana dan komponen BOS dapat ditambahkan oleh admin jika diperlukan.');

    // 2. Validate empty submission fails
    $this->post(route('bos.anggaran.store'), [
        'source_funding' => '',
        'name' => '',
    ])->assertSessionHasErrors(['source_funding', 'name']);

    // 3. Store new BOS entry with predefined options
    $this->post(route('bos.anggaran.store'), [
        'source_funding' => 'BOS Reguler',
        'name' => 'Belanja Pegawai',
    ])->assertRedirect(route('bos.anggaran.index'))
      ->assertSessionHas('success_modal', true)
      ->assertSessionHas('success', 'Data jenis pemasukan BOS berhasil disimpan ke dalam sistem.');

    $cat = DB::table('budget_categories')->where('school_id', $schoolId)->where('source_funding', 'BOS Reguler')->first();
    expect($cat)->not->toBeNull()
        ->and($cat->name)->toBe('Belanja Pegawai')
        ->and($cat->code)->toStartWith('BOS-');

    // 4. Store custom "Lainnya" source and component
    $this->post(route('bos.anggaran.store'), [
        'source_funding' => 'Lainnya',
        'custom_source' => 'BOS Kinerja Afirmasi',
        'name' => 'Lainnya',
        'custom_component' => 'Digitalisasi Pembelajaran',
    ])->assertRedirect(route('bos.anggaran.index'))
      ->assertSessionHas('success_modal', true);

    $customCat = DB::table('budget_categories')->where('school_id', $schoolId)->where('source_funding', 'BOS Kinerja Afirmasi')->first();
    expect($customCat)->not->toBeNull()
        ->and($customCat->name)->toBe('Digitalisasi Pembelajaran');

    // 5. Update BOS entry
    $this->put(route('bos.anggaran.update', $cat->id), [
        'source_funding' => 'BOS Reguler',
        'name' => 'Belanja Pegawai & Honorarium',
    ])->assertRedirect(route('bos.anggaran.index'))->assertSessionHas('success');

    $updatedCat = DB::table('budget_categories')->where('id', $cat->id)->first();
    expect($updatedCat->name)->toBe('Belanja Pegawai & Honorarium');

    // 6. Delete BOS entry
    $this->delete(route('bos.anggaran.destroy', $cat->id))
        ->assertRedirect(route('bos.anggaran.index'))->assertSessionHas('success');
    expect(DB::table('budget_categories')->where('id', $cat->id)->count())->toBe(0);
});

it('implements Flow Master Jenis Pengeluaran according to Menu Bendahara page 10-11', function () {
    $schoolId = makeFinanceRouteSchool('Sekolah Jenis Pengeluaran');
    $user = makeFinanceRouteUser($schoolId);
    $this->actingAs($user)->withSession(['active_school_id' => $schoolId]);

    // 1. Visit Jenis Pengeluaran page
    $response = $this->get(route('finance.expense-types'))->assertOk();
    $response->assertSee('Jenis Pengeluaran');
    $response->assertSee('Master Data Keuangan');
    $response->assertSee('data-table');

    // 2. Validation fails if required fields are missing
    $this->post(route('finance.expense-types.store'), [
        'name' => '',
        'source_funding' => '',
    ])->assertSessionHasErrors(['name', 'source_funding']);

    // 3. Successfully create expense type
    $this->post(route('finance.expense-types.store'), [
        'name' => 'Honorarium Guru Tidak Tetap',
        'source_funding' => 'BOS Reguler',
        'bos_component' => 'Belanja pegawai',
        'requires_approval' => '1',
    ])->assertRedirect()->assertSessionHas('success', 'Jenis pengeluaran disimpan');

    $expenseType = DB::table('expense_types')
        ->where('school_id', $schoolId)
        ->where('name', 'Honorarium Guru Tidak Tetap')
        ->first();

    expect($expenseType)->not->toBeNull()
        ->and($expenseType->code)->toStartWith('JPK-')
        ->and($expenseType->source_funding)->toBe('BOS Reguler')
        ->and($expenseType->bos_component)->toBe('Belanja pegawai')
        ->and((bool) $expenseType->requires_approval)->toBeTrue();

    // 4. Update expense type
    $this->put(route('finance.expense-types.update', $expenseType->id), [
        'name' => 'Honorarium GTT & PTT',
        'source_funding' => 'Komite',
        'requires_approval' => '0',
    ])->assertRedirect()->assertSessionHas('success', 'Jenis pengeluaran disimpan');

    $updated = DB::table('expense_types')->where('id', $expenseType->id)->first();
    expect($updated->name)->toBe('Honorarium GTT & PTT')
        ->and($updated->source_funding)->toBe('Komite')
        ->and((bool) $updated->requires_approval)->toBeFalse();

    // 5. Delete expense type
    $this->delete(route('finance.expense-types.destroy', $expenseType->id))
        ->assertRedirect()->assertSessionHas('success');
    expect(DB::table('expense_types')->where('id', $expenseType->id)->count())->toBe(0);
});

it('implements Flow Perencanaan Anggaran actions according to Menu Bendahara page 12-15', function () {
    $schoolId = makeFinanceRouteSchool('Sekolah Perencanaan Anggaran');
    $user = makeFinanceRouteUser($schoolId);
    $this->actingAs($user)->withSession(['active_school_id' => $schoolId]);

    // 1. Tahun Anggaran: Store & notification
    $this->post(route('finance.budget-years.store'), [
        'name' => 'Tahun Anggaran 2026/2027',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ])->assertRedirect()->assertSessionHas('success', 'Tahun anggaran disimpan');

    $year = DB::table('budget_years')->where('school_id', $schoolId)->where('name', 'Tahun Anggaran 2026/2027')->first();
    expect($year)->not->toBeNull();

    // 2. Susun Anggaran: Store & notification
    $this->post(route('finance.budgets.store'), [
        'budget_year_id' => $year->id,
        'source_funding' => 'BOS',
        'program_name' => 'Pengembangan Perpustakaan',
        'activity_name' => 'Pengadaan Buku Pelajaran',
        'amount' => 15000000,
    ])->assertRedirect()->assertSessionHas('success', 'Susunan anggaran disimpan');

    $plan = DB::table('budget_plans')
        ->where('school_id', $schoolId)
        ->where('program_name', 'Pengembangan Perpustakaan')
        ->first();
    expect($plan)->not->toBeNull()
        ->and($plan->status)->toBe('pending');

    // 3. Revisi Anggaran: Store revision & notification
    $this->post(route('finance.budgets.revisions.store', $plan->id), [
        'new_amount' => 18000000,
        'reason' => 'Kebutuhan kurikulum baru',
    ])->assertRedirect()->assertSessionHas('success', 'Revisi anggaran disimpan');

    $revision = DB::table('budget_plan_revisions')->where('budget_plan_id', $plan->id)->first();
    expect($revision)->not->toBeNull()
        ->and((float) $revision->new_amount)->toEqual(18000000.0);

    // 4. Delete budget plan
    $this->delete(route('finance.budgets.destroy', $plan->id))
        ->assertRedirect()->assertSessionHas('success', 'Susunan anggaran berhasil dihapus.');
    expect(DB::table('budget_plans')->where('id', $plan->id)->count())->toBe(0);

    // 5. Delete budget year
    $this->delete(route('finance.budget-years.destroy', $year->id))
        ->assertRedirect()->assertSessionHas('success', 'Tahun anggaran berhasil dihapus.');
    expect(DB::table('budget_years')->where('id', $year->id)->count())->toBe(0);
});

it('implements Flow Buat Tagihan Baru and Flow Lihat Tunggakan Siswa matching Menu Bendahara page 16-22', function () {
    $schoolId = makeFinanceRouteSchool('Sekolah Tagihan & Tunggakan');
    $user = makeFinanceRouteUser($schoolId);
    $this->actingAs($user)->withSession(['active_school_id' => $schoolId]);

    // Create supporting master data
    $incomeTypeId = DB::table('income_types')->insertGetId([
        'school_id' => $schoolId,
        'code' => 'KMT-001',
        'name' => 'Iuran Komite',
        'category' => 'komite',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $academicYearId = DB::table('academic_years')->insertGetId([
        'school_id' => $schoolId,
        'year' => '2026/2027',
        'semester' => 'Ganjil',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $studentId = DB::table('students')->insertGetId([
        'school_id' => $schoolId,
        'name' => 'Ahmad Santoso',
        'nis' => '102938',
        'nisn' => '0098765432',
        'gender' => 'L',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $bankAccountId = DB::table('school_accounts')->insertGetId([
        'school_id' => $schoolId,
        'name' => 'Rekening Komite Bank BNI',
        'type' => 'bank',
        'bank_name' => 'BNI',
        'account_number' => '1234567890',
        'opening_balance' => 10000000,
        'current_balance' => 10000000,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $walletId = DB::table('virtual_wallets')->insertGetId([
        'school_id' => $schoolId,
        'name' => 'Dompet Sarpras Komite',
        'nominal' => 2000000,
        'status' => 'active',
        'source' => 'komite',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('fund_allocations')->insert([
        'school_id' => $schoolId,
        'income_type_id' => $incomeTypeId,
        'virtual_wallet_id' => $walletId,
        'name' => 'Alokasi Sarpras 20%',
        'method' => 'percentage',
        'amount' => 20.00,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // 1. Flow Buat Tagihan Baru (Page 16-17)
    $this->post(route('finance.billing.store'), [
        'income_type_id' => $incomeTypeId,
        'academic_year_id' => $academicYearId,
        'name' => 'Iuran Komite Semester Ganjil',
        'amount' => 500000,
        'allow_installment' => '1',
        'minimum_installment' => 100000,
        'has_late_fee' => '1',
        'late_fee_per_day' => 2000,
        'late_fee_maximum' => 50000,
        'billing_frequency' => 'Bulanan',
        'start_date' => '2026-07-01',
        'due_date' => '2026-07-10',
        'target_type' => 'school',
    ])->assertRedirect()->assertSessionHas('success', 'Tagihan berhasil disimpan.');

    $bill = DB::table('billing_items')
        ->where('school_id', $schoolId)
        ->where('name', 'Iuran Komite Semester Ganjil')
        ->first();
    expect($bill)->not->toBeNull()
        ->and((bool) $bill->allow_installment)->toBeTrue()
        ->and((float) $bill->minimum_installment)->toEqual(100000.0)
        ->and((bool) $bill->has_late_fee)->toBeTrue()
        ->and((float) $bill->late_fee_per_day)->toEqual(2000.0);

    // 2. Flow Lihat Tunggakan Siswa (Page 18-20)
    $invoiceId = DB::table('invoices')->insertGetId([
        'school_id' => $schoolId,
        'student_id' => $studentId,
        'academic_year_id' => $academicYearId,
        'invoice_number' => 'INV-2026-001',
        'due_date' => '2026-07-10',
        'total_amount' => 500000,
        'status' => 'Belum Lunas',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('invoice_items')->insert([
        'invoice_id' => $invoiceId,
        'name' => 'Iuran Komite Semester Ganjil',
        'amount' => 500000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $respBilling = $this->get(route('finance.billing'))->assertOk();
    $respBilling->assertSee('Ahmad Santoso', false);
    $respBilling->assertSee('102938', false);
    $respBilling->assertSee('data-table', false);

    // 3. Bayar Tagihan Tunggakan Siswa (Page 19-20 & 21-22)
    $this->post("/spp/transaksi/{$invoiceId}/bayar", [
        'amount_paid' => 300000,
        'payment_method' => 'Transfer Bank',
        'account_id' => $bankAccountId,
        'payment_date' => '2026-07-05',
    ])->assertRedirect()->assertSessionHas('success', 'Transaksi berhasil disimpan');

    // Verify invoice status updated to Cicilan
    $inv = DB::table('invoices')->where('id', $invoiceId)->first();
    expect($inv->status)->toBe('Cicilan');

    // Verify school account incremented
    $account = DB::table('school_accounts')->where('id', $bankAccountId)->first();
    expect((float) $account->current_balance)->toEqual(10300000.0);

    // Verify virtual wallet allocated 20% of 300,000 = 60,000 (2,000,000 + 60,000 = 2,060,000)
    $wallet = DB::table('virtual_wallets')->where('id', $walletId)->first();
    expect((float) $wallet->nominal)->toEqual(2060000.0);
});

it('implements Flow Input Pemasukan BOS, Hibah, and Lainnya matching Menu Bendahara page 25-29', function () {
    $schoolId = makeFinanceRouteSchool('Sekolah Pemasukan Non-Siswa');
    $user = makeFinanceRouteUser($schoolId);
    $this->actingAs($user)->withSession(['active_school_id' => $schoolId]);

    $bosAccountId = DB::table('school_accounts')->insertGetId([
        'school_id' => $schoolId,
        'name' => 'Rekening Kas BOS Reguler',
        'type' => 'bos',
        'bank_name' => 'Bank Mandiri',
        'account_number' => '987654321',
        'opening_balance' => 0,
        'current_balance' => 0,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $bankAccountId = DB::table('school_accounts')->insertGetId([
        'school_id' => $schoolId,
        'name' => 'Rekening Operasional Bank BRI',
        'type' => 'bank',
        'bank_name' => 'BRI',
        'account_number' => '1122334455',
        'opening_balance' => 5000000,
        'current_balance' => 5000000,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // 1. Flow Input Pemasukan BOS (Page 25)
    $this->post(route('finance.incomes.store'), [
        'category' => 'bos',
        'source_funding' => 'BOS Reguler',
        'bos_year' => 2026,
        'amount' => 75000000,
        'received_date' => '2026-07-15',
    ])->assertRedirect()->assertSessionHas('success', 'Transaksi berhasil disimpan');

    $bosAcc = DB::table('school_accounts')->where('id', $bosAccountId)->first();
    expect((float) $bosAcc->current_balance)->toEqual(75000000.0);

    // 2. Flow Input Pemasukan Hibah (Page 26-27)
    $this->post(route('finance.incomes.store'), [
        'category' => 'hibah',
        'donor_name' => 'PT. Inovasi Cemerlang',
        'amount' => 25000000,
        'received_date' => '2026-07-20',
        'payment_method' => 'Transfer',
        'account_id' => $bankAccountId,
    ])->assertRedirect()->assertSessionHas('success', 'Transaksi berhasil disimpan');

    $bankAcc = DB::table('school_accounts')->where('id', $bankAccountId)->first();
    expect((float) $bankAcc->current_balance)->toEqual(30000000.0);

    // 3. Flow Input Pendapatan Lainnya (Page 28-29)
    $this->post(route('finance.incomes.store'), [
        'category' => 'lainnya',
        'source_funding' => 'Bagi Hasil Kantin Sekolah',
        'amount' => 1500000,
        'received_date' => '2026-07-25',
        'payment_method' => 'Transfer',
        'account_id' => $bankAccountId,
    ])->assertRedirect()->assertSessionHas('success', 'Transaksi berhasil disimpan');

    $bankAccAfter = DB::table('school_accounts')->where('id', $bankAccountId)->first();
    expect((float) $bankAccAfter->current_balance)->toEqual(31500000.0);
});

it('implements Flow Input Pengeluaran Komite and BOS matching Menu Bendahara page 30-33', function () {
    $schoolId = makeFinanceRouteSchool('Sekolah Belanja Komite & BOS');
    $user = makeFinanceRouteUser($schoolId);
    $this->actingAs($user)->withSession(['active_school_id' => $schoolId]);

    $academicYearId = DB::table('academic_years')->insertGetId([
        'school_id' => $schoolId,
        'year' => '2026/2027',
        'semester' => 'Ganjil',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $expTypeId = DB::table('expense_types')->insertGetId([
        'school_id' => $schoolId,
        'code' => 'JPK-0010',
        'name' => 'Konsumsi Rapat & Tamu',
        'source_funding' => 'komite',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $walletId = DB::table('virtual_wallets')->insertGetId([
        'school_id' => $schoolId,
        'name' => 'Dompet Operasional Kantor',
        'nominal' => 5000000,
        'status' => 'active',
        'source' => 'komite',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $budgetCatId = DB::table('budget_categories')->insertGetId([
        'school_id' => $schoolId,
        'code' => '5.1.02',
        'name' => 'Belanja Barang dan Jasa BOS',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // 1. Visit /bos/belanja
    $resp = $this->get(route('bos.belanja.index'))->assertOk();
    $resp->assertSee('Operasional', false);
    $resp->assertSee('BOS', false);
    $resp->assertSee('data-table', false);

    // 2. Submit Operasional Expense (Page 30-31)
    $this->post(route('bos.belanja.store'), [
        'category' => 'Operasional',
        'source_funding' => 'Komite',
        'academic_year_id' => $academicYearId,
        'expense_type_id' => $expTypeId,
        'virtual_wallet_id' => $walletId,
        'expense_name' => 'Konsumsi Rapat Komite Bulanan',
        'amount' => 450000,
        'transaction_date' => '2026-07-28',
        'payment_method' => 'Tunai',
        'recipient_name' => 'Catering Bu Siti',
    ])->assertRedirect()->assertSessionHas('success');

    $expenseOp = DB::table('expenses')
        ->where('school_id', $schoolId)
        ->where('expense_name', 'Konsumsi Rapat Komite Bulanan')
        ->first();
    expect($expenseOp)->not->toBeNull()
        ->and($expenseOp->status)->toBe('pending');

    // 3. Submit BOS Expense with Document Checklists (Page 32-33)
    $this->post(route('bos.belanja.store'), [
        'category' => 'BOS',
        'source_funding' => 'BOS',
        'academic_year_id' => $academicYearId,
        'budget_category_id' => $budgetCatId,
        'expense_name' => 'Pengadaan Kertas & ATK Ujian',
        'amount' => 2500000,
        'transaction_date' => '2026-07-29',
        'payment_method' => 'Transfer',
        'recipient_name' => 'CV. Graha Media',
        'tax_type' => 'PPN',
        'tax_amount' => 275000,
        'document_checklist' => ['Kuitansi', 'Faktur/Nota', 'BAST'],
    ])->assertRedirect()->assertSessionHas('success');

    $expenseBos = DB::table('expenses')
        ->where('school_id', $schoolId)
        ->where('expense_name', 'Pengadaan Kertas & ATK Ujian')
        ->first();
    expect($expenseBos)->not->toBeNull()
        ->and($expenseBos->status)->toBe('pending');
});

it('implements Flow Tutup Buku Komite and BOS matching Menu Bendahara page 34-37', function () {
    $schoolId = makeFinanceRouteSchool('Sekolah Tutup Buku');
    $user = makeFinanceRouteUser($schoolId);
    $this->actingAs($user)->withSession(['active_school_id' => $schoolId]);

    // 1. Visit /finance/closing
    $resp = $this->get(route('finance.closing'))->assertOk();
    $resp->assertSee('Tutup Buku &amp; Penguncian Periode', false);
    $resp->assertSee('Pemeriksaan Kesiapan Tutup Buku', false);

    // 2. Perform Closing when all 8 conditions are met
    $this->post(route('finance.closing.store'), [
        'type' => 'monthly',
        'period' => '2026-07',
    ])->assertRedirect()->assertSessionHas('success', 'Periode finance berhasil ditutup dan saldo telah dikunci.');

    $closingMonthly = DB::table('book_closings')
        ->where('school_id', $schoolId)
        ->where('type', 'monthly')
        ->where('period', '2026-07')
        ->first();
    expect($closingMonthly)->not->toBeNull()
        ->and($closingMonthly->status)->toBe('closed');

    // 3. Perform Tutup Buku BOS (Page 35-36)
    $this->post(route('finance.closing.store'), [
        'type' => 'bos',
        'bos_type' => 'BOS Reguler',
        'period' => '2026-07',
    ])->assertRedirect()->assertSessionHas('success', 'Periode finance berhasil ditutup dan saldo telah dikunci.');

    $closingBos = DB::table('book_closings')
        ->where('school_id', $schoolId)
        ->where('type', 'bos')
        ->first();
    expect($closingBos)->not->toBeNull()
        ->and($closingBos->status)->toBe('closed');
});

it('implements Flow Revisi Anggaran according to workflow specification and places Dompet Virtual under Master Data Keuangan', function () {
    $schoolId = makeFinanceRouteSchool('SMK Revisi Anggaran');
    $user = makeFinanceRouteUser($schoolId);
    $this->actingAs($user);

    // 1. Sidebar menu verification: Dompet Virtual is in Master Data Keuangan, not a standalone single menu
    $navItems = collect(MenuHelper::getMainNavItems());
    $topLevelVirtualWallets = $navItems->filter(fn ($item) => ($item['name'] ?? '') === 'Dompet Virtual');
    expect($topLevelVirtualWallets->count())->toBe(0);

    $masterKeuangan = $navItems->firstWhere('name', 'Master Data Keuangan');
    expect($masterKeuangan)->not->toBeNull();
    $masterSubItems = collect($masterKeuangan['subItems'] ?? []);
    expect($masterSubItems->firstWhere('name', 'Dompet Virtual'))->not->toBeNull();

    // 2. Budget Year and Budget Plan setup
    $yearId = DB::table('budget_years')->insertGetId([
        'school_id' => $schoolId,
        'name' => 'Tahun Ajaran 2026/2027',
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $planId = DB::table('budget_plans')->insertGetId([
        'school_id' => $schoolId,
        'budget_year_id' => $yearId,
        'source_funding' => 'BOS Reguler',
        'program_name' => 'Program Pembelajaran',
        'activity_name' => 'Pengadaan Buku Pelajaran',
        'amount' => 50000000,
        'status' => 'approved',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // 3. Revisi Anggaran page render & elements check
    $resp = $this->get(route('finance.budgets.revisions', ['budget_year_id' => $yearId]))->assertOk();
    $resp->assertSee('Revisi Anggaran');
    $resp->assertSee('Hanya tahun anggaran yang berstatus aktif yang dapat direvisi.');
    $resp->assertSee('Daftar Program dan Kegiatan');
    $resp->assertSee('Program Pembelajaran');
    $resp->assertSee('Pengadaan Buku Pelajaran');
    $resp->assertSee('BOS Reguler');
    $resp->assertSee('data-table');

    // 4. Store revision
    $this->post(route('finance.budgets.revisions.store', $planId), [
        'new_amount' => 70000000,
        'reason' => 'Penambahan jumlah buku sesuai kebutuhan siswa tahun ajaran 2026/2027.',
    ])->assertRedirect()->assertSessionHas('success', 'Revisi anggaran disimpan');

    $revision = DB::table('budget_plan_revisions')->where('budget_plan_id', $planId)->first();
    expect($revision)->not->toBeNull()
        ->and((float) $revision->new_amount)->toEqual(70000000.0)
        ->and($revision->status)->toBe('pending');
});

it('implements Flow Buat Tagihan Baru matching the 10-step workflow specification', function () {
    $schoolId = makeFinanceRouteSchool('SMA Al-Falah Tagihan');
    $user = makeFinanceRouteUser($schoolId);
    $this->actingAs($user);

    $ayId = DB::table('academic_years')->insertGetId([
        'school_id' => $schoolId,
        'year' => '2025/2026',
        'semester' => 'Ganjil',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $incId = DB::table('income_types')->insertGetId([
        'school_id' => $schoolId,
        'code' => 'JP009',
        'name' => 'SPP Komite Bulanan',
        'category' => 'Komite',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $classId = DB::table('school_classes')->insertGetId([
        'school_id' => $schoolId,
        'academic_year_id' => $ayId,
        'name' => 'TKJ 1',
        'grade' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $studentId = DB::table('students')->insertGetId([
        'school_id' => $schoolId,
        'name' => 'Andi Pratama',
        'nis' => '250001',
        'nisn' => '0098765432',
        'gender' => 'L',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('class_students')->insert([
        'school_class_id' => $classId,
        'student_id' => $studentId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // 1. Visit /finance/billing: check elements
    $resp = $this->get(route('finance.billing'))->assertOk();
    $resp->assertSee('Tagihan Komite');
    $resp->assertSee('Buat Tagihan Baru');
    $resp->assertSee('Semua Tahun Ajaran');
    $resp->assertSee('Semua Status');
    $resp->assertSee('Catatan Penting Pengaturan Tagihan:');
    $resp->assertSee('data-table');

    // 2. Generate Tagihan (Step 4-7)
    $this->post(route('finance.billing.store'), [
        'income_type_id' => $incId,
        'academic_year_id' => $ayId,
        'name' => 'SPP Komite 2025/2026',
        'amount' => 300000,
        'allow_installment' => 'ya',
        'minimum_installment' => 100000,
        'has_late_fee' => 'ya',
        'late_fee_per_day' => 5000,
        'late_fee_maximum' => 100000,
        'billing_frequency' => 'Bulanan',
        'billing_day' => 5,
        'due_day' => 25,
        'target_type' => 'pilihan',
        'target_class_id' => $classId,
    ])->assertRedirect()->assertSessionHas('success_modal', true)
      ->assertSessionHas('success', 'Tagihan berhasil disimpan.');

    $bill = DB::table('billing_items')->where('school_id', $schoolId)->where('name', 'SPP Komite 2025/2026')->first();
    expect($bill)->not->toBeNull()
        ->and((float) $bill->amount)->toEqual(300000.0)
        ->and((bool) $bill->allow_installment)->toBeTrue()
        ->and((float) $bill->minimum_installment)->toEqual(100000.0);

    // Verify student invoice was generated
    $studentInvoice = DB::table('invoices')
        ->where('school_id', $schoolId)
        ->where('student_id', $studentId)
        ->first();
    expect($studentInvoice)->not->toBeNull()
        ->and((float) $studentInvoice->total_amount)->toEqual(300000.0);

    // 3. Safeguard: After invoice is paid, billing cannot be deleted
    DB::table('invoices')->where('id', $studentInvoice->id)->update([
        'status' => 'Lunas',
    ]);

    $this->delete(route('finance.billing.destroy', $bill->id))
        ->assertRedirect()
        ->assertSessionHas('error');
    expect(DB::table('billing_items')->where('id', $bill->id)->count())->toBe(1);
});

it('implements Flow Lihat Tunggakan Siswa dan Pembayaran matching the 10-step workflow', function () {
    $schoolId = makeFinanceRouteSchool('SMP Arrears Workflow School');
    $user = makeFinanceRouteUser($schoolId);
    $this->actingAs($user)->withSession(['active_school_id' => $schoolId]);

    $ayId = DB::table('academic_years')->insertGetId([
        'school_id' => $schoolId,
        'year' => '2025/2026',
        'semester' => 'Ganjil',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $classId = DB::table('school_classes')->insertGetId([
        'school_id' => $schoolId,
        'academic_year_id' => $ayId,
        'name' => 'VII-A',
        'grade' => 7,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $studentId = DB::table('students')->insertGetId([
        'school_id' => $schoolId,
        'name' => 'Budi Arrears Santoso',
        'nis' => '998877',
        'nisn' => '0011223344',
        'gender' => 'L',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('class_students')->insert([
        'school_class_id' => $classId,
        'student_id' => $studentId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $walletId = DB::table('virtual_wallets')->insertGetId([
        'school_id' => $schoolId,
        'name' => 'Dompet SPP',
        'nominal' => 0,
        'status' => 'active',
        'source' => 'komite',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $accountId = DB::table('school_accounts')->insertGetId([
        'school_id' => $schoolId,
        'name' => 'Bank BRI Komite',
        'account_number' => '1234567890',
        'bank_name' => 'BRI',
        'type' => 'bank',
        'opening_balance' => 1000000,
        'current_balance' => 1000000,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $incId = DB::table('income_types')->insertGetId([
        'school_id' => $schoolId,
        'name' => 'SPP Bulanan',
        'code' => 'SPP-01',
        'category' => 'Komite',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Setup automatic virtual wallet allocation (20% dari setiap pembayaran)
    DB::table('fund_allocations')->insert([
        'school_id' => $schoolId,
        'income_type_id' => $incId,
        'virtual_wallet_id' => $walletId,
        'name' => 'Alokasi Sarpras SPP',
        'method' => 'persentase',
        'amount' => 20,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $billId = DB::table('billing_items')->insertGetId([
        'school_id' => $schoolId,
        'income_type_id' => $incId,
        'academic_year_id' => $ayId,
        'name' => 'Tagihan SPP Juli 2025',
        'amount' => 500000,
        'allow_installment' => true,
        'minimum_installment' => 100000,
        'billing_frequency' => 'Bulanan',
        'due_date' => now()->subDays(5)->toDateString(), // Past due -> Menunggak
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $invId = DB::table('invoices')->insertGetId([
        'school_id' => $schoolId,
        'student_id' => $studentId,
        'academic_year_id' => $ayId,
        'invoice_number' => 'INV-2025-07-001',
        'total_amount' => 500000,
        'status' => 'Belum Lunas',
        'due_date' => now()->subDays(5)->toDateString(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('invoice_items')->insert([
        'invoice_id' => $invId,
        'name' => 'SPP Bulanan Juli 2025',
        'amount' => 500000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // 1. Visit /finance/billing?tab=arrears (Step 1 - Step 3)
    $resp = $this->get(route('finance.billing', ['tab' => 'arrears']))->assertOk();
    $resp->assertSee('Tunggakan Siswa');
    $resp->assertSee('Data tunggakan selalu real-time');
    $resp->assertSee('Budi Arrears Santoso');
    $resp->assertSee('998877');
    $resp->assertSee('VII-A');
    $resp->assertSee('Saldo Kas / Bank Sekolah Diperbarui');
    $resp->assertSee('Pembagian ke Dompet Virtual');
    $resp->assertSee('Notifikasi WhatsApp ke Orang Tua');
    $resp->assertSee('Panduan Status Pembayaran Siswa');
    $resp->assertSee('Setelah disimpan, sistem akan:');
    $resp->assertSee('Mengupdate saldo kas/rekening sekolah');
    // Karena tagihan sudah melewati jatuh tempo (due_date in the past), status awal adalah Menunggak
    $resp->assertSee('Menunggak');

    // 2. Perform partial payment (cicilan: 200,000) (Step 5 - 8)
    $payResp = $this->post(route('spp.transaksi.pay', $invId), [
        'amount_paid' => 200000,
        'payment_method' => 'Transfer',
        'account_id' => $accountId,
        'payment_date' => now()->toDateString(),
        'notes' => 'Cicilan pertama SPP',
    ])->assertRedirect()->assertSessionHas('payment_success_modal', true);

    $payResp->assertSessionHas('trx_number');
    $payResp->assertSessionHas('success');
    // User Note: Sistem mengirimkan notifikasi ke orang tua setiap ada transaksi uang masuk
    $payResp->assertSessionHas('parent_notification_sent', true);

    // Verify invoice status updated to Cicilan
    $invAfter1 = DB::table('invoices')->where('id', $invId)->first();
    expect($invAfter1->status)->toEqual('Cicilan');

    // Verify school account balance updated (1,000,000 + 200,000 = 1,200,000)
    $accountAfter1 = DB::table('school_accounts')->where('id', $accountId)->first();
    expect((float) $accountAfter1->current_balance)->toEqual(1200000.0);

    // User Note: Sistem otomatis bagi besaran transaksi masukkan ke Virtual wallet (20% dari 200,000 = 40,000)
    $walletAfter1 = DB::table('virtual_wallets')->where('id', $walletId)->first();
    expect((float) $walletAfter1->nominal)->toEqual(40000.0);

    // 3. Complete remaining payment (300,000) -> status becomes Lunas
    $payResp2 = $this->post(route('spp.transaksi.pay', $invId), [
        'amount_paid' => 300000,
        'payment_method' => 'Tunai',
        'payment_date' => now()->toDateString(),
        'notes' => 'Pelunasan SPP',
    ])->assertRedirect()->assertSessionHas('payment_success_modal', true);

    $payResp2->assertSessionHas('parent_notification_sent', true);

    $invAfter2 = DB::table('invoices')->where('id', $invId)->first();
    expect($invAfter2->status)->toEqual('Lunas');

    // Virtual wallet bertambah 20% dari 300,000 = 60,000 (total: 40,000 + 60,000 = 100,000)
    $walletAfter2 = DB::table('virtual_wallets')->where('id', $walletId)->first();
    expect((float) $walletAfter2->nominal)->toEqual(100000.0);
});

it('allows only kepala sekolah to approve or reject penyusunan anggaran and revisi anggaran', function () {
    $schoolId = makeFinanceRouteSchool('Sekolah Approval Kepsek');
    $bendahara = makeFinanceRouteUser($schoolId);
    $kepsek = User::factory()->create(['role' => 'kepsek']);
    DB::table('school_user_roles')->insert([
        'user_id' => $kepsek->id,
        'school_id' => $schoolId,
        'role' => 'kepsek',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $activeYearId = DB::table('academic_years')->insertGetId([
        'school_id' => $schoolId,
        'year' => '2026/2027',
        'semester' => 'Ganjil',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $budgetYear = app(FinanceService::class)->createBudgetYear($schoolId, [
        'name' => 'TA 2026/2027',
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
        'academic_year_id' => $activeYearId,
    ]);

    // 1. Bendahara creates a budget plan (Susun Anggaran)
    $this->actingAs($bendahara)->withSession(['active_school_id' => $schoolId]);
    $this->post('/finance/budgets', [
        'budget_year_id' => $budgetYear->id,
        'program_name' => 'Program Laboratorium Komputer',
        'activity_name' => 'Pengadaan PC Desktop',
        'source_funding' => 'Komite',
        'amount' => 50000000,
    ])->assertRedirect();

    $budgetPlan = DB::table('budget_plans')->where('school_id', $schoolId)->where('program_name', 'Program Laboratorium Komputer')->first();
    expect($budgetPlan)->not->toBeNull()
        ->and($budgetPlan->status)->toEqual('pending');

    $approvalBudget = DB::table('approval_requests')
        ->where('school_id', $schoolId)
        ->where('type', 'budget')
        ->where('approvable_id', $budgetPlan->id)
        ->first();
    expect($approvalBudget)->not->toBeNull()
        ->and($approvalBudget->status)->toEqual('pending');

    // Verify view /finance/approvals as bendahara shows notification that only Kepsek can approve
    $bendaharaView = $this->actingAs($bendahara)->withSession(['active_school_id' => $schoolId])
        ->get('/finance/approvals')->assertOk();
    $bendaharaView->assertSee('Antrian Approval Keuangan');
    $bendaharaView->assertSee('Rencana Anggaran');

    // Bendahara attempts to approve budget plan -> MUST be 403 Forbidden
    $this->actingAs($bendahara)->withSession(['active_school_id' => $schoolId])
        ->post("/approvals/{$approvalBudget->id}/approve")
        ->assertForbidden();

    // Bendahara attempts to reject budget plan -> MUST be 403 Forbidden
    $this->actingAs($bendahara)->withSession(['active_school_id' => $schoolId])
        ->post("/approvals/{$approvalBudget->id}/reject")
        ->assertForbidden();

    // Kepsek accesses /finance/approvals -> allowed
    $kepsekView = $this->actingAs($kepsek)->withSession(['active_school_id' => $schoolId])
        ->get('/finance/approvals')->assertOk();
    $kepsekView->assertSee('Antrian Approval Keuangan');

    // Kepsek approves the budget plan -> successful redirect
    $this->actingAs($kepsek)->withSession(['active_school_id' => $schoolId])
        ->post("/approvals/{$approvalBudget->id}/approve")
        ->assertRedirect();

    $budgetPlanAfter = DB::table('budget_plans')->where('id', $budgetPlan->id)->first();
    expect($budgetPlanAfter->status)->toEqual('approved');
    $approvalBudgetAfter = DB::table('approval_requests')->where('id', $approvalBudget->id)->first();
    expect($approvalBudgetAfter->status)->toEqual('approved');

    // 2. Bendahara creates a budget revision (Revisi Anggaran)
    $this->actingAs($bendahara)->withSession(['active_school_id' => $schoolId]);
    $this->post("/finance/budgets/{$budgetPlan->id}/revisions", [
        'new_amount' => 60000000,
        'reason' => 'Kebutuhan upgrade spesifikasi RAM PC',
    ])->assertRedirect();

    $revision = DB::table('budget_plan_revisions')->where('budget_plan_id', $budgetPlan->id)->first();
    expect($revision)->not->toBeNull()
        ->and($revision->status)->toEqual('pending');

    $approvalRevision = DB::table('approval_requests')
        ->where('school_id', $schoolId)
        ->where('type', 'budget_revision')
        ->where('approvable_id', $revision->id)
        ->first();
    expect($approvalRevision)->not->toBeNull()
        ->and($approvalRevision->status)->toEqual('pending');

    // Bendahara attempts to approve budget revision -> MUST be 403 Forbidden
    $this->actingAs($bendahara)->withSession(['active_school_id' => $schoolId])
        ->post("/approvals/{$approvalRevision->id}/approve")
        ->assertForbidden();

    // Bendahara attempts to reject budget revision -> MUST be 403 Forbidden
    $this->actingAs($bendahara)->withSession(['active_school_id' => $schoolId])
        ->post("/approvals/{$approvalRevision->id}/reject")
        ->assertForbidden();

    // Kepsek approves the budget revision -> successful
    $this->actingAs($kepsek)->withSession(['active_school_id' => $schoolId])
        ->post("/approvals/{$approvalRevision->id}/approve")
        ->assertRedirect();

    $revisionAfter = DB::table('budget_plan_revisions')->where('id', $revision->id)->first();
    expect($revisionAfter->status)->toEqual('approved');
    $planAfterRevision = DB::table('budget_plans')->where('id', $budgetPlan->id)->first();
    expect((float) $planAfterRevision->amount)->toEqual(60000000.0);
});

it('ensures nominal inputs use thousands masking and accepts dot-delimited values seamlessly', function () {
    $schoolId = makeFinanceRouteSchool('Sekolah Masking');
    $user = makeFinanceRouteUser($schoolId);
    $this->actingAs($user)->withSession(['active_school_id' => $schoolId]);

    // 1. Verify pages render data-mask="currency" on their nominal input fields
    $budgetResp = $this->get('/finance/budgets')->assertOk();
    $budgetResp->assertSee('data-mask="currency"', false);

    $virtualWalletResp = $this->get('/finance/virtual-wallets')->assertOk();
    $virtualWalletResp->assertSee('data-mask="currency"', false);

    $billingResp = $this->get('/finance/billing')->assertOk();
    $billingResp->assertSee('data-mask="currency"', false);

    $transaksiResp = $this->get('/spp/transaksi')->assertOk();
    $transaksiResp->assertSee('data-mask="currency"', false);

    $belanjaResp = $this->get('/bos/belanja')->assertOk();
    $belanjaResp->assertSee('data-mask="currency"', false);

    // 2. Submit virtual wallet with dot-delimited thousands masked nominal
    $this->post('/finance/virtual-wallets', [
        'name' => 'Dana Praktikum',
        'nominal' => '12.500.000',
        'source' => 'Komite',
        'status' => 'active',
    ])->assertRedirect();

    $wallet = DB::table('virtual_wallets')->where('school_id', $schoolId)->where('name', 'Dana Praktikum')->first();
    expect($wallet)->not->toBeNull()
        ->and((float) $wallet->nominal)->toEqual(12500000.0);

    // 3. Submit budget plan with dot-delimited thousands in array activities
    $activeYearId = DB::table('academic_years')->insertGetId([
        'school_id' => $schoolId,
        'year' => '2026/2027',
        'semester' => 'Ganjil',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $budgetYear = app(FinanceService::class)->createBudgetYear($schoolId, [
        'name' => 'TA 2026/2027 Mask',
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
        'academic_year_id' => $activeYearId,
    ]);

    $this->post('/finance/budgets', [
        'budget_year_id' => $budgetYear->id,
        'source_funding' => 'BOS',
        'programs' => [
            [
                'name' => 'Program Sarpras',
                'activities' => [
                    ['name' => 'Pengadaan Proyektor', 'amount' => '4.750.000'],
                    ['name' => 'Kabel HDMI', 'amount' => '250.000'],
                ],
            ],
        ],
    ])->assertRedirect();

    $plan1 = DB::table('budget_plans')->where('school_id', $schoolId)->where('activity_name', 'Pengadaan Proyektor')->first();
    expect($plan1)->not->toBeNull()
        ->and((float) $plan1->amount)->toEqual(4750000.0);

    $plan2 = DB::table('budget_plans')->where('school_id', $schoolId)->where('activity_name', 'Kabel HDMI')->first();
    expect($plan2)->not->toBeNull()
        ->and((float) $plan2->amount)->toEqual(250000.0);
});




