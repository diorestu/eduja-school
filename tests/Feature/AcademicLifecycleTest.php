<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function academicSchool(array $attributes = []): int
{
    return DB::table('schools')->insertGetId(array_merge([
        'name' => 'Sekolah Akademik',
        'level' => 'sma',
        'ownership' => 'swasta',
        'is_active' => true,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ], $attributes));
}

function academicUserFor(int $schoolId, string $role = 'wakasek'): User
{
    $user = User::factory()->create(['role' => 'super_admin']);

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

it('creates academic years and activates them only within the active school', function () {
    $school = academicSchool(['name' => 'Sekolah A']);
    $otherSchool = academicSchool(['name' => 'Sekolah B']);
    $user = academicUserFor($school, 'staf_tu');
    $otherYear = DB::table('academic_years')->insertGetId([
        'school_id' => $otherSchool,
        'year' => '2026/2027',
        'semester' => 'Ganjil',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->withSession(['active_school_id' => $school])
        ->post('/akademik', [
            'year' => '2027/2028',
            'semester' => 'Ganjil',
            'start_date' => '2027-07-01',
            'end_date' => '2027-12-31',
            'is_active' => 1,
        ])
        ->assertRedirect();

    expect(DB::table('academic_years')->where('school_id', $school)->where('is_active', true)->count())->toBe(1);
    expect(DB::table('academic_years')->where('id', $otherYear)->value('is_active'))->toBe(1);

    $this->post("/akademik/{$otherYear}/toggle")
        ->assertNotFound();
});

it('limits departments to vocational schools and the active school', function () {
    $school = academicSchool(['level' => 'smk']);
    $otherSchool = academicSchool(['level' => 'smk']);
    $sma = academicSchool(['level' => 'sma']);
    $user = academicUserFor($school);

    $this->actingAs($user)
        ->withSession(['active_school_id' => $school])
        ->post('/academic/departments', ['code' => 'TKJ', 'name' => 'Teknik Komputer dan Jaringan'])
        ->assertRedirect();

    expect(DB::table('departments')->where('school_id', $school)->where('code', 'TKJ')->exists())->toBeTrue();

    $this->withSession(['active_school_id' => $otherSchool])
        ->post('/academic/departments', ['code' => 'AKL', 'name' => 'Akuntansi'])
        ->assertForbidden();

    expect(DB::table('departments')->where('school_id', $otherSchool)->where('code', 'AKL')->exists())->toBeFalse();

    $smaUser = academicUserFor($sma);
    $this->actingAs($smaUser)->withSession(['active_school_id' => $sma])
        ->post('/academic/departments', ['code' => 'IPA', 'name' => 'Ilmu Pengetahuan Alam'])
        ->assertSessionHasErrors('code');

    expect(DB::table('departments')->where('school_id', $sma)->exists())->toBeFalse();
});

it('generates vocational rombels from grade, department, and count in one school', function () {
    $school = academicSchool(['level' => 'smk']);
    $user = academicUserFor($school, 'staf_tu');
    $year = DB::table('academic_years')->insertGetId([
        'school_id' => $school,
        'year' => '2027/2028',
        'semester' => 'Ganjil',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $department = DB::table('departments')->insertGetId([
        'school_id' => $school,
        'code' => 'TKJ',
        'name' => 'Teknik Komputer dan Jaringan',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->withSession(['active_school_id' => $school])
        ->post('/kelas', [
            'grade' => 10,
            'academic_year_id' => $year,
            'department_id' => $department,
            'rombel_count' => 2,
        ])
        ->assertRedirect();

    expect(DB::table('school_classes')->where('school_id', $school)->pluck('name')->all())
        ->toEqualCanonicalizing(['X TKJ 1', 'X TKJ 2']);
});

it('promotes students while retaining enrollment history and rejecting another school target', function () {
    $school = academicSchool();
    $otherSchool = academicSchool(['name' => 'Sekolah Lain']);
    $user = academicUserFor($school);
    $sourceYear = DB::table('academic_years')->insertGetId([
        'school_id' => $school, 'year' => '2026/2027', 'semester' => 'Genap', 'is_active' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $targetYear = DB::table('academic_years')->insertGetId([
        'school_id' => $school, 'year' => '2027/2028', 'semester' => 'Ganjil', 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $sourceClass = DB::table('school_classes')->insertGetId([
        'school_id' => $school, 'name' => 'X 1', 'grade' => 10, 'academic_year_id' => $sourceYear,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $targetClass = DB::table('school_classes')->insertGetId([
        'school_id' => $school, 'name' => 'XI 1', 'grade' => 11, 'academic_year_id' => $targetYear,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $otherClass = DB::table('school_classes')->insertGetId([
        'school_id' => $otherSchool, 'name' => 'XI Lain', 'grade' => 11, 'academic_year_id' => DB::table('academic_years')->insertGetId([
            'school_id' => $otherSchool, 'year' => '2027/2028', 'semester' => 'Ganjil', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $student = DB::table('students')->insertGetId([
        'school_id' => $school, 'nis' => 'P-001', 'name' => 'Siswa Promosi', 'gender' => 'L',
        'is_active' => true, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('class_students')->insert([
        'school_class_id' => $sourceClass, 'student_id' => $student, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->withSession(['active_school_id' => $school])
        ->post('/academic/promotion', ['student_ids' => [$student], 'target_class_id' => $targetClass])
        ->assertRedirect();

    expect(DB::table('class_students')->where('student_id', $student)->where('school_class_id', $sourceClass)->exists())->toBeFalse();
    expect(DB::table('class_students')->where('student_id', $student)->where('school_class_id', $targetClass)->exists())->toBeTrue();
    expect(DB::table('student_class_histories')->where('student_id', $student)->where('school_class_id', $sourceClass)->exists())->toBeTrue();

    $this->post('/academic/promotion', ['student_ids' => [$student], 'target_class_id' => $otherClass])
        ->assertNotFound();
});

it('blocks graduation on outstanding bills and preserves no partial state', function () {
    $school = academicSchool();
    $user = academicUserFor($school);
    $year = DB::table('academic_years')->insertGetId([
        'school_id' => $school, 'year' => '2026/2027', 'semester' => 'Genap', 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $student = DB::table('students')->insertGetId([
        'school_id' => $school, 'nis' => 'G-001', 'name' => 'Siswa Menunggak', 'gender' => 'P',
        'is_active' => true, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('invoices')->insert([
        'school_id' => $school, 'student_id' => $student, 'academic_year_id' => $year,
        'invoice_number' => 'INV-G-001', 'due_date' => '2026-07-31', 'total_amount' => 500000,
        'status' => 'Belum Lunas', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->withSession(['active_school_id' => $school])
        ->post('/academic/graduation', [
            'student_ids' => [$student], 'graduation_date' => '2027-06-30', 'graduation_year' => 2027,
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(DB::table('students')->where('id', $student)->value('status'))->toBe('active');
    expect(DB::table('alumni')->where('student_id', $student)->exists())->toBeFalse();
});

it('graduates students, archives alumni, ends class access, history, attendance, and school login', function () {
    $school = academicSchool();
    $user = academicUserFor($school);
    $studentUser = User::factory()->create(['role' => 'siswa']);
    DB::table('school_user_roles')->insert([
        'user_id' => $studentUser->id, 'school_id' => $school, 'role' => 'siswa', 'is_active' => true,
        'membership_status' => 'active', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $year = DB::table('academic_years')->insertGetId([
        'school_id' => $school, 'year' => '2026/2027', 'semester' => 'Genap', 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $class = DB::table('school_classes')->insertGetId([
        'school_id' => $school, 'name' => 'XII IPA 1', 'grade' => 12, 'academic_year_id' => $year,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $student = DB::table('students')->insertGetId([
        'school_id' => $school, 'user_id' => $studentUser->id, 'nis' => 'L-001', 'nisn' => '9001',
        'name' => 'Siswa Lulus', 'gender' => 'L', 'phone' => '0811', 'is_active' => true,
        'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('class_students')->insert([
        'school_class_id' => $class, 'student_id' => $student, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->withSession(['active_school_id' => $school])
        ->post('/academic/graduation', [
            'student_ids' => [$student], 'graduation_date' => '2027-06-30', 'graduation_year' => 2027,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(DB::table('students')->where('id', $student)->value('status'))->toBe('graduated');
    expect((bool) DB::table('students')->where('id', $student)->value('is_active'))->toBeFalse();
    expect(DB::table('alumni')->where('student_id', $student)->where('graduation_year', 2027)->exists())->toBeTrue();
    expect(DB::table('class_students')->where('student_id', $student)->exists())->toBeFalse();
    expect(DB::table('student_class_histories')->where('student_id', $student)->where('action', 'graduated')->exists())->toBeTrue();
    expect(DB::table('lifecycle_histories')->where('subject_type', 'student')->where('subject_id', $student)->where('to_status', 'graduated')->exists())->toBeTrue();
    expect(DB::table('school_user_roles')->where('user_id', $studentUser->id)->where('school_id', $school)->value('is_active'))->toBe(0);
});

it('updates student and teacher lifecycle states with immutable history and removes active access', function () {
    $school = academicSchool();
    $user = academicUserFor($school, 'staf_tu');
    $student = DB::table('students')->insertGetId([
        'school_id' => $school, 'nis' => 'S-001', 'name' => 'Siswa Pindah', 'gender' => 'P',
        'is_active' => true, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $teacher = DB::table('teachers')->insertGetId([
        'school_id' => $school, 'nuptk' => 'T-001', 'name' => 'Guru Resign', 'role_type' => 'Guru',
        'is_active' => true, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->withSession(['active_school_id' => $school])
        ->post('/siswa', [
            'action' => 'status', 'student_id' => $student, 'status' => 'transferred',
            'status_date' => '2027-01-15', 'status_note' => 'Pindah kota',
            'transfer_destination' => 'Sekolah Tujuan',
        ])
        ->assertRedirect();

    $this->post('/guru', [
        'action' => 'status', 'teacher_id' => $teacher, 'status' => 'resigned',
        'status_date' => '2027-01-31', 'status_note' => 'Mengundurkan diri',
    ])->assertRedirect();

    expect(DB::table('students')->where('id', $student)->value('status'))->toBe('transferred');
    expect(DB::table('teachers')->where('id', $teacher)->value('status'))->toBe('resigned');
    expect(DB::table('lifecycle_histories')->where('subject_id', $student)->where('from_status', 'active')->where('to_status', 'transferred')->exists())->toBeTrue();
    expect(DB::table('lifecycle_histories')->where('subject_id', $teacher)->where('from_status', 'active')->where('to_status', 'resigned')->exists())->toBeTrue();
    expect(json_decode(DB::table('lifecycle_histories')->where('subject_id', $student)->value('metadata'), true)['transfer_destination'])->toBe('Sekolah Tujuan');
});

it('does not allow inactive people to be listed or recorded in scoped attendance', function () {
    $school = academicSchool();
    $user = academicUserFor($school, 'staf_tu');
    $year = DB::table('academic_years')->insertGetId([
        'school_id' => $school, 'year' => '2026/2027', 'semester' => 'Ganjil', 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $class = DB::table('school_classes')->insertGetId([
        'school_id' => $school, 'name' => 'X 1', 'grade' => 10, 'academic_year_id' => $year,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $student = DB::table('students')->insertGetId([
        'school_id' => $school, 'nis' => 'I-001', 'name' => 'Siswa Tidak Aktif', 'gender' => 'L',
        'is_active' => false, 'status' => 'deceased', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('class_students')->insert([
        'school_class_id' => $class, 'student_id' => $student, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->withSession(['active_school_id' => $school])
        ->get('/presensi/siswa?school_class_id='.$class)
        ->assertOk()
        ->assertDontSee('Siswa Tidak Aktif');

    $this->post('/presensi/siswa', [
        'school_class_id' => $class,
        'attendance_date' => '2027-02-01',
        'attendances' => [$student => 'H'],
    ])->assertSessionHasErrors('attendances');

    expect(Schema::hasTable('lifecycle_histories'))->toBeTrue();
});
