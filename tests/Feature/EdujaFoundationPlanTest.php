<?php

use App\Helpers\MenuHelper;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates the foundation tables required by the ten gap plan', function () {
    foreach ([
        'schools',
        'school_user_roles',
        'departments',
        'alumni',
        'school_accounts',
        'income_types',
        'expense_types',
        'budget_years',
        'budget_plans',
        'approval_requests',
        'billing_items',
        'payment_submissions',
        'book_closings',
        'attendance_requests',
        'announcements',
        'ai_materials',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Missing table: {$table}");
    }
});

it('requires a multi-school user to choose an active school before dashboard access', function () {
    $user = User::factory()->create(['role' => 'staf_tu']);
    $schoolA = DB::table('schools')->insertGetId(['name' => 'Sekolah A', 'level' => 'sma', 'ownership' => 'swasta', 'created_at' => now(), 'updated_at' => now()]);
    $schoolB = DB::table('schools')->insertGetId(['name' => 'Sekolah B', 'level' => 'smp', 'ownership' => 'negeri', 'created_at' => now(), 'updated_at' => now()]);

    DB::table('school_user_roles')->insert([
        ['user_id' => $user->id, 'school_id' => $schoolA, 'role' => 'tu', 'created_at' => now(), 'updated_at' => now()],
        ['user_id' => $user->id, 'school_id' => $schoolB, 'role' => 'bendahara', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $this->actingAs($user)->get('/dashboard')->assertRedirect('/school/select');

    $this->actingAs($user)
        ->post('/school/switch', ['school_id' => $schoolB])
        ->assertRedirect('/dashboard')
        ->assertSessionHas('active_school_id', $schoolB);
});

it('provides school-scoped principal dashboard data', function () {
    $user = User::factory()->create(['role' => 'super_admin']);
    $school = DB::table('schools')->insertGetId(['name' => 'Sekolah Kepala', 'level' => 'smk', 'ownership' => 'swasta', 'created_at' => now(), 'updated_at' => now()]);
    $otherSchool = DB::table('schools')->insertGetId(['name' => 'Sekolah Lain', 'level' => 'sma', 'ownership' => 'negeri', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('school_user_roles')->insert(['user_id' => $user->id, 'school_id' => $school, 'role' => 'kepsek', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('teachers')->insert([
        ['school_id' => $school, 'name' => 'Guru Sekolah', 'role_type' => 'Guru', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['school_id' => $school, 'name' => 'Tendik Sekolah', 'role_type' => 'Staf TU', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['school_id' => $otherSchool, 'name' => 'Guru Lain', 'role_type' => 'Guru', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $this->actingAs($user)->withSession(['active_school_id' => $school])->get('/dashboard')
        ->assertOk()
        ->assertViewHas('totalTeachers', 2)
        ->assertViewHas('totalStaff', 1)
        ->assertViewHas('departmentCount', 0)
        ->assertViewHas('attendanceSeries', fn ($series) => count($series['months']) === 6)
        ->assertViewHas('financeSeries', fn ($series) => count($series['months']) === 6);
});

it('authorizes protected modules from the active school role instead of the flat user role', function () {
    $user = User::factory()->create(['role' => 'staf_tu']);
    $school = DB::table('schools')->insertGetId(['name' => 'Sekolah Role', 'level' => 'sma', 'ownership' => 'swasta', 'created_at' => now(), 'updated_at' => now()]);

    DB::table('school_user_roles')->insert([
        'user_id' => $user->id,
        'school_id' => $school,
        'role' => 'bendahara',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->withSession(['active_school_id' => $school])
        ->get('/finance/accounts')
        ->assertOk();

    $this->actingAs($user)
        ->withSession(['active_school_id' => $school])
        ->get('/academic/departments')
        ->assertForbidden();
});

it('exposes the v1 route groups for dashboards, academic, finance, portals, attendance, announcements, ai, and alumni', function () {
    $user = User::factory()->create(['role' => 'super_admin']);
    $school = DB::table('schools')->insertGetId(['name' => 'Sekolah Routes', 'level' => 'sma', 'ownership' => 'swasta', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('school_user_roles')->insert(['user_id' => $user->id, 'school_id' => $school, 'role' => 'kepsek', 'created_at' => now(), 'updated_at' => now()]);

    $paths = [
        '/dinas',
        '/yayasan',
        '/academic/departments',
        '/academic/promotion',
        '/academic/graduation',
        '/alumni',
        '/finance/accounts',
        '/finance/income-types',
        '/finance/expense-types',
        '/finance/budgets',
        '/finance/approvals',
        '/finance/closing',
        '/portal/guru',
        '/portal/siswa',
        '/portal/orang-tua',
        '/attendance/requests',
        '/announcements',
        '/ai',
    ];

    foreach ($paths as $path) {
        $response = $this->actingAs($user)
            ->withSession(['active_school_id' => $school])
            ->get($path);

        expect($response->getStatusCode())->toBeIn([200, 403]);
    }
});

it('keeps the previously developed finance menus visible for an active school principal', function () {
    $user = User::factory()->create(['role' => 'super_admin']);
    $school = DB::table('schools')->insertGetId(['name' => 'Sekolah Menu Keuangan', 'level' => 'sma', 'ownership' => 'swasta', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('school_user_roles')->insert(['user_id' => $user->id, 'school_id' => $school, 'role' => 'kepsek', 'created_at' => now(), 'updated_at' => now()]);

    $this->actingAs($user)->withSession(['active_school_id' => $school]);

    $items = collect(MenuHelper::getMainNavItems());
    $spp = $items->firstWhere('name', 'Keuangan SPP');
    $bos = $items->firstWhere('name', 'Keuangan BOS & BKU');

    expect($spp)->not->toBeNull();
    expect(collect($spp['subItems'])->pluck('path')->all())->toContain('/spp/tarif', '/spp/transaksi', '/spp/laporan', '/tabungan');
    expect($bos)->not->toBeNull();
    expect(collect($bos['subItems'])->pluck('path')->all())->toContain('/bos/anggaran', '/bos/belanja', '/bos/bku');

    $this->get('/spp/laporan')->assertOk();
    $this->get('/bos/bku')->assertOk();
    $this->get('/tabungan')->assertOk();
});

it('promotes selected active students into the target class', function () {
    $user = User::factory()->create(['role' => 'super_admin']);
    $school = DB::table('schools')->insertGetId(['name' => 'Sekolah Promosi', 'level' => 'sma', 'ownership' => 'swasta', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('school_user_roles')->insert(['user_id' => $user->id, 'school_id' => $school, 'role' => 'wakasek', 'created_at' => now(), 'updated_at' => now()]);

    $year = DB::table('academic_years')->insertGetId(['school_id' => $school, 'year' => '2026/2027', 'semester' => 'Ganjil', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    $sourceClass = DB::table('school_classes')->insertGetId(['school_id' => $school, 'name' => 'X IPA 1', 'grade' => 10, 'academic_year_id' => $year, 'created_at' => now(), 'updated_at' => now()]);
    $targetClass = DB::table('school_classes')->insertGetId(['school_id' => $school, 'name' => 'XI IPA 1', 'grade' => 11, 'academic_year_id' => $year, 'created_at' => now(), 'updated_at' => now()]);
    $student = DB::table('students')->insertGetId(['school_id' => $school, 'nis' => 'P001', 'nisn' => '9001', 'name' => 'Siswa Promosi', 'gender' => 'L', 'is_active' => true, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('class_students')->insert(['school_class_id' => $sourceClass, 'student_id' => $student, 'created_at' => now(), 'updated_at' => now()]);

    $this->actingAs($user)
        ->withSession(['active_school_id' => $school])
        ->post('/academic/promotion', [
            'student_ids' => [$student],
            'target_class_id' => $targetClass,
        ])
        ->assertRedirect();

    expect(DB::table('class_students')->where('student_id', $student)->where('school_class_id', $sourceClass)->exists())->toBeFalse();
    expect(DB::table('class_students')->where('student_id', $student)->where('school_class_id', $targetClass)->exists())->toBeTrue();
});

it('blocks graduation when a selected student still has outstanding invoices', function () {
    $user = User::factory()->create(['role' => 'super_admin']);
    $school = DB::table('schools')->insertGetId(['name' => 'Sekolah Lulus Blokir', 'level' => 'sma', 'ownership' => 'swasta', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('school_user_roles')->insert(['user_id' => $user->id, 'school_id' => $school, 'role' => 'wakasek', 'created_at' => now(), 'updated_at' => now()]);

    $year = DB::table('academic_years')->insertGetId(['school_id' => $school, 'year' => '2026/2027', 'semester' => 'Ganjil', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    $student = DB::table('students')->insertGetId(['school_id' => $school, 'nis' => 'G001', 'nisn' => '9101', 'name' => 'Siswa Menunggak', 'gender' => 'P', 'is_active' => true, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('invoices')->insert(['school_id' => $school, 'student_id' => $student, 'academic_year_id' => $year, 'invoice_number' => 'INV-GRAD-001', 'due_date' => now()->toDateString(), 'total_amount' => 500000, 'status' => 'Belum Lunas', 'created_at' => now(), 'updated_at' => now()]);

    $this->actingAs($user)
        ->withSession(['active_school_id' => $school])
        ->post('/academic/graduation', [
            'student_ids' => [$student],
            'graduation_date' => '2026-07-10',
            'graduation_year' => 2026,
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(DB::table('students')->where('id', $student)->value('status'))->toBe('active');
    expect(DB::table('alumni')->where('student_id', $student)->exists())->toBeFalse();
});

it('graduates selected students, archives alumni, and removes active class mapping', function () {
    $user = User::factory()->create(['role' => 'super_admin']);
    $school = DB::table('schools')->insertGetId(['name' => 'Sekolah Lulus', 'level' => 'sma', 'ownership' => 'swasta', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('school_user_roles')->insert(['user_id' => $user->id, 'school_id' => $school, 'role' => 'wakasek', 'created_at' => now(), 'updated_at' => now()]);

    $year = DB::table('academic_years')->insertGetId(['school_id' => $school, 'year' => '2026/2027', 'semester' => 'Ganjil', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    $class = DB::table('school_classes')->insertGetId(['school_id' => $school, 'name' => 'XII IPA 1', 'grade' => 12, 'academic_year_id' => $year, 'created_at' => now(), 'updated_at' => now()]);
    $student = DB::table('students')->insertGetId(['school_id' => $school, 'nis' => 'G002', 'nisn' => '9102', 'name' => 'Siswa Lulus', 'gender' => 'L', 'phone' => '0811', 'is_active' => true, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('class_students')->insert(['school_class_id' => $class, 'student_id' => $student, 'created_at' => now(), 'updated_at' => now()]);

    $this->actingAs($user)
        ->withSession(['active_school_id' => $school])
        ->post('/academic/graduation', [
            'student_ids' => [$student],
            'graduation_date' => '2026-07-10',
            'graduation_year' => 2026,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(DB::table('students')->where('id', $student)->value('status'))->toBe('graduated');
    expect((bool) DB::table('students')->where('id', $student)->value('is_active'))->toBeFalse();
    expect(DB::table('alumni')->where('student_id', $student)->where('graduation_year', 2026)->exists())->toBeTrue();
    expect(DB::table('class_students')->where('student_id', $student)->exists())->toBeFalse();
});

it('approves a payment submission through the approval queue', function () {
    $user = User::factory()->create(['role' => 'bendahara']);
    $school = DB::table('schools')->insertGetId(['name' => 'Sekolah Approval', 'level' => 'sma', 'ownership' => 'swasta', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('school_user_roles')->insert(['user_id' => $user->id, 'school_id' => $school, 'role' => 'bendahara', 'created_at' => now(), 'updated_at' => now()]);

    $submission = DB::table('payment_submissions')->insertGetId(['school_id' => $school, 'amount' => 150000, 'payment_date' => now()->toDateString(), 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
    $approval = DB::table('approval_requests')->insertGetId(['school_id' => $school, 'requested_by' => $user->id, 'approvable_type' => App\Models\PaymentSubmission::class, 'approvable_id' => $submission, 'type' => 'payment', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

    $this->actingAs($user)
        ->withSession(['active_school_id' => $school])
        ->post("/approvals/{$approval}/approve", ['note' => 'Bukti valid'])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(DB::table('approval_requests')->where('id', $approval)->value('status'))->toBe('approved');
    expect(DB::table('payment_submissions')->where('id', $submission)->value('status'))->toBe('approved');
});

it('approves an expense through the approval queue', function () {
    $user = User::factory()->create(['role' => 'super_admin']);
    $school = DB::table('schools')->insertGetId(['name' => 'Sekolah Approval Pengeluaran', 'level' => 'sma', 'ownership' => 'swasta', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('school_user_roles')->insert(['user_id' => $user->id, 'school_id' => $school, 'role' => 'kepsek', 'created_at' => now(), 'updated_at' => now()]);

    $year = DB::table('academic_years')->insertGetId(['school_id' => $school, 'year' => '2026/2027', 'semester' => 'Ganjil', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    $expense = DB::table('expenses')->insertGetId([
        'school_id' => $school,
        'academic_year_id' => $year,
        'expense_name' => 'Belanja ATK',
        'amount' => 250000,
        'transaction_date' => now()->toDateString(),
        'source_funding' => 'BOS',
        'payment_method' => 'Tunai',
        'status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $approval = DB::table('approval_requests')->insertGetId(['school_id' => $school, 'requested_by' => $user->id, 'approvable_type' => App\Models\Expense::class, 'approvable_id' => $expense, 'type' => 'expense', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

    $this->actingAs($user)
        ->withSession(['active_school_id' => $school])
        ->post("/approvals/{$approval}/approve", ['note' => 'Pengeluaran sesuai'])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(DB::table('approval_requests')->where('id', $approval)->value('status'))->toBe('approved');
    expect(DB::table('expenses')->where('id', $expense)->value('status'))->toBe('approved');
});

it('rejects an attendance request through the approval queue', function () {
    $user = User::factory()->create(['role' => 'super_admin']);
    $school = DB::table('schools')->insertGetId(['name' => 'Sekolah Approval Izin', 'level' => 'sma', 'ownership' => 'swasta', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('school_user_roles')->insert(['user_id' => $user->id, 'school_id' => $school, 'role' => 'wakasek', 'created_at' => now(), 'updated_at' => now()]);

    $requestId = DB::table('attendance_requests')->insertGetId(['school_id' => $school, 'request_type' => 'sakit', 'start_date' => now()->toDateString(), 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
    $approval = DB::table('approval_requests')->insertGetId(['school_id' => $school, 'requested_by' => $user->id, 'approvable_type' => App\Models\AttendanceRequest::class, 'approvable_id' => $requestId, 'type' => 'attendance', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

    $this->actingAs($user)
        ->withSession(['active_school_id' => $school])
        ->post("/approvals/{$approval}/reject", ['note' => 'Dokumen tidak jelas'])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(DB::table('approval_requests')->where('id', $approval)->value('status'))->toBe('rejected');
    expect(DB::table('attendance_requests')->where('id', $requestId)->value('status'))->toBe('rejected');
});
