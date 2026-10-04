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

    foreach ([
        'Tagihan' => '/finance/billing',
        'Pemasukan' => '/spp/transaksi',
        'Pengeluaran' => '/bos/belanja',
        'Approval' => '/finance/approvals',
        'Dompet Virtual' => '/finance/accounts',
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




