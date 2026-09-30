<?php

use App\Models\ApprovalRequest;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function portalIntegritySchool(): School
{
    return School::create(['name' => 'Sekolah Portal Integritas', 'level' => 'sma', 'ownership' => 'swasta', 'status' => 'active', 'is_active' => true]);
}

function portalIntegrityMember(School $school, string $role): User
{
    $user = User::factory()->create(['role' => $role]);
    DB::table('school_user_roles')->insert([
        'user_id' => $user->id, 'school_id' => $school->id, 'role' => $role,
        'is_active' => true, 'membership_status' => 'active', 'created_at' => now(), 'updated_at' => now(),
    ]);
    return $user;
}

function portalIntegrityClass(School $school, Student $student): int
{
    $year = DB::table('academic_years')->insertGetId(['school_id' => $school->id, 'year' => '2026/2027', 'semester' => 'Ganjil', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    $class = DB::table('school_classes')->insertGetId(['school_id' => $school->id, 'academic_year_id' => $year, 'name' => 'X Portal', 'grade' => 10, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('class_students')->insert(['school_class_id' => $class, 'student_id' => $student->id, 'created_at' => now(), 'updated_at' => now()]);
    return $class;
}

it('creates a real pending approval when a guardian submits a portal permission request', function () {
    $school = portalIntegritySchool();
    $guardian = portalIntegrityMember($school, 'orang_tua');
    $student = Student::create(['school_id' => $school->id, 'guardian_user_id' => $guardian->id, 'nis' => 'PI-001', 'name' => 'Anak Portal', 'gender' => 'P', 'status' => 'active', 'is_active' => true]);

    $this->actingAs($guardian)->withSession(['active_school_id' => $school->id])
        ->post('/portal/siswa/permission', [
            'student_id' => $student->id, 'request_type' => 'izin', 'start_date' => today()->addDay()->toDateString(),
            'end_date' => today()->addDay()->toDateString(), 'reason' => 'Keperluan keluarga.',
        ])->assertRedirect()->assertSessionHasNoErrors();

    $request = \App\Models\AttendanceRequest::query()->firstOrFail();
    expect($request->requester_id)->toBe($guardian->id)
        ->and($request->subject_id)->toBe($student->id)
        ->and($request->status)->toBe('pending')
        ->and(ApprovalRequest::where('approvable_id', $request->id)->where('type', 'attendance')->exists())->toBeTrue();
});

it('requires a real browser location for student self attendance', function () {
    $school = portalIntegritySchool();
    $studentUser = portalIntegrityMember($school, 'siswa');
    $student = Student::create(['school_id' => $school->id, 'user_id' => $studentUser->id, 'nis' => 'PI-002', 'name' => 'Siswa Portal', 'gender' => 'L', 'status' => 'active', 'is_active' => true]);
    portalIntegrityClass($school, $student);

    $this->actingAs($studentUser)->withSession(['active_school_id' => $school->id])
        ->post('/portal/siswa/attendance', ['type' => 'masuk'])
        ->assertSessionHasErrors(['location.latitude', 'location.longitude']);

    Config::set('services.attendance.gps.schools.'.$school->id, ['latitude' => -5.1477, 'longitude' => 119.4327, 'radius_meters' => 250]);
    $this->post('/portal/siswa/attendance', ['type' => 'masuk', 'location' => ['latitude' => -5.1477, 'longitude' => 119.4327]])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect(DB::table('student_attendances')->where('student_id', $student->id)->value('source'))->toBe('gps');
});

it('renders honest unavailable states for academic data and map configuration', function () {
    $school = portalIntegritySchool();
    $studentUser = portalIntegrityMember($school, 'siswa');
    Student::create(['school_id' => $school->id, 'user_id' => $studentUser->id, 'nis' => 'PI-003', 'name' => 'Siswa Tampilan', 'gender' => 'L', 'status' => 'active', 'is_active' => true]);

    $this->actingAs($studentUser)->withSession(['active_school_id' => $school->id])
        ->get('/portal/siswa')
        ->assertOk()
        ->assertSee('Peta sekolah belum diaktifkan')
        ->assertSee('Nilai belum tersedia')
        ->assertSee('Jadwal belum tersedia');
});
