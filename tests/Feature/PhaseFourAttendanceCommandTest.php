<?php

use App\Models\School;
use App\Models\StudentAttendance;
use App\Models\TeacherAttendance;
use App\Services\AttendanceService;
use App\Services\AttendanceCommandService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function commandSchool(string $name): int
{
    return School::create([
        'name' => $name,
        'level' => 'sma',
        'ownership' => 'swasta',
        'is_active' => true,
        'status' => 'active',
    ])->id;
}

function commandClass(int $schoolId, string $name = 'X IPA 1'): int
{
    $yearId = DB::table('academic_years')->insertGetId([
        'school_id' => $schoolId,
        'year' => '2026/2027',
        'semester' => 'Ganjil',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return DB::table('school_classes')->insertGetId([
        'school_id' => $schoolId,
        'name' => $name,
        'grade' => 10,
        'academic_year_id' => $yearId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function commandStudent(int $schoolId, string $nis, bool $active = true): int
{
    return DB::table('students')->insertGetId([
        'school_id' => $schoolId,
        'nis' => $nis,
        'name' => "Siswa {$nis}",
        'gender' => 'L',
        'is_active' => $active,
        'status' => $active ? 'active' : 'graduated',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function commandTeacher(int $schoolId, string $name, string $roleType = 'Guru', bool $active = true): int
{
    return DB::table('teachers')->insertGetId([
        'school_id' => $schoolId,
        'name' => $name,
        'role_type' => $roleType,
        'staff_type' => 'Honorer',
        'is_active' => $active,
        'status' => $active ? 'active' : 'resigned',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('records an active student only with a class from the same school and preserves supplied metadata', function () {
    $schoolId = commandSchool('Sekolah Command Siswa');
    $otherSchoolId = commandSchool('Sekolah Lain');
    $classId = commandClass($schoolId);
    $studentId = commandStudent($schoolId, 'CMD-001');
    $otherClassId = commandClass($otherSchoolId, 'X IPS 1');
    DB::table('class_students')->insert([
        'school_class_id' => $classId,
        'student_id' => $studentId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $attendance = app(AttendanceCommandService::class)->recordStudent($schoolId, $studentId, [
        'school_class_id' => $classId,
        'attendance_date' => '2026-09-14',
        'status' => 'D',
        'source' => 'gps',
        'clock_in_at' => '07:11:00',
        'latitude' => '-5.147665',
        'longitude' => '119.432732',
        'photo_path' => 'attendance/cmd-001.jpg',
        'note' => 'Kegiatan sekolah',
    ]);

    expect($attendance)->toBeInstanceOf(App\Models\StudentAttendance::class)
        ->and($attendance->school_id)->toBe($schoolId)
        ->and($attendance->student_id)->toBe($studentId)
        ->and($attendance->school_class_id)->toBe($classId)
        ->and($attendance->status)->toBe('D')
        ->and($attendance->source)->toBe('gps')
        ->and($attendance->latitude)->toBe('-5.1476650')
        ->and($attendance->longitude)->toBe('119.4327320')
        ->and(StudentAttendance::count())->toBe(1);

    expect(fn () => app(AttendanceCommandService::class)->recordStudent($schoolId, $studentId, [
        'school_class_id' => $otherClassId,
        'attendance_date' => '2026-09-15',
        'status' => 'H',
    ]))->toThrow(InvalidArgumentException::class);
});

it('updates a student daily record without replacing omitted source or location metadata', function () {
    $schoolId = commandSchool('Sekolah Duplicate Siswa');
    $classId = commandClass($schoolId);
    $studentId = commandStudent($schoolId, 'CMD-002');
    DB::table('class_students')->insert([
        'school_class_id' => $classId,
        'student_id' => $studentId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = app(AttendanceCommandService::class);
    $service->recordStudent($schoolId, $studentId, [
        'school_class_id' => $classId,
        'attendance_date' => '2026-09-14',
        'status' => 'H',
        'source' => 'rfid',
        'latitude' => '-5.1000000',
        'longitude' => '119.4000000',
    ]);
    $updated = $service->recordStudent($schoolId, $studentId, [
        'school_class_id' => $classId,
        'attendance_date' => '2026-09-14',
        'status' => 'I',
    ]);

    expect(StudentAttendance::count())->toBe(1)
        ->and($updated->status)->toBe('I')
        ->and($updated->source)->toBe('rfid')
        ->and($updated->latitude)->toBe('-5.1000000')
        ->and($updated->longitude)->toBe('119.4000000');
});

it('rejects inactive or cross-school students and teachers before creating attendance', function () {
    $schoolId = commandSchool('Sekolah Scope Command');
    $otherSchoolId = commandSchool('Sekolah Scope Lain');
    $classId = commandClass($schoolId);
    $activeStudentId = commandStudent($schoolId, 'CMD-003');
    $inactiveStudentId = commandStudent($schoolId, 'CMD-004', false);
    $otherStudentId = commandStudent($otherSchoolId, 'CMD-005');
    $teacherId = commandTeacher($schoolId, 'Guru Scope');
    $inactiveTeacherId = commandTeacher($schoolId, 'Guru Tidak Aktif', 'Guru', false);
    $otherTeacherId = commandTeacher($otherSchoolId, 'Guru Sekolah Lain');
    DB::table('class_students')->insert([
        'school_class_id' => $classId,
        'student_id' => $activeStudentId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = app(AttendanceCommandService::class);
    foreach ([$inactiveStudentId, $otherStudentId] as $studentId) {
        expect(fn () => $service->recordStudent($schoolId, $studentId, [
            'school_class_id' => $classId,
            'attendance_date' => '2026-09-14',
            'status' => 'H',
        ]))->toThrow(InvalidArgumentException::class);
    }

    foreach ([$inactiveTeacherId, $otherTeacherId] as $teacherIdToReject) {
        expect(fn () => $service->recordTeacher($schoolId, $teacherIdToReject, [
            'attendance_date' => '2026-09-14',
            'status' => 'H',
        ]))->toThrow(InvalidArgumentException::class);
    }

    expect(TeacherAttendance::count())->toBe(0);
});

it('records guru and tendik statuses and separates them in scoped period summaries', function () {
    $schoolId = commandSchool('Sekolah Summary Command');
    $otherSchoolId = commandSchool('Sekolah Summary Lain');
    $classId = commandClass($schoolId);
    $studentId = commandStudent($schoolId, 'CMD-006');
    DB::table('class_students')->insert([
        'school_class_id' => $classId,
        'student_id' => $studentId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $guruId = commandTeacher($schoolId, 'Guru Summary', 'Guru');
    $tendikId = commandTeacher($schoolId, 'Tendik Summary', 'Staf TU');
    $otherGuruId = commandTeacher($otherSchoolId, 'Guru Summary Lain', 'Guru');
    $service = app(AttendanceCommandService::class);

    $service->recordStudent($schoolId, $studentId, [
        'school_class_id' => $classId,
        'attendance_date' => '2026-09-14',
        'status' => 'H',
    ]);
    $service->recordTeacher($schoolId, $guruId, [
        'attendance_date' => '2026-09-14',
        'status' => 'H',
    ]);
    $service->recordTeacher($schoolId, $tendikId, [
        'attendance_date' => '2026-09-13',
        'status' => 'C',
    ]);
    $service->recordTeacher($otherSchoolId, $otherGuruId, [
        'attendance_date' => '2026-09-14',
        'status' => 'H',
    ]);

    $summary = app(AttendanceService::class)->summary($schoolId, [
        'start_date' => '2026-09-13',
        'end_date' => '2026-09-14',
    ]);

    expect($summary['students']['H'])->toBe(1)
        ->and($summary['guru']['H'])->toBe(1)
        ->and($summary['tendik']['C'])->toBe(1)
        ->and($summary['guru']['H'] + $summary['tendik']['H'])->toBe(1);
});

it('routes manual student and gtk writes through school-scoped commands', function () {
    $schoolId = commandSchool('Sekolah Controller Command');
    $otherSchoolId = commandSchool('Sekolah Controller Lain');
    $classId = commandClass($schoolId);
    $studentId = commandStudent($schoolId, 'CMD-007');
    $otherStudentId = commandStudent($otherSchoolId, 'CMD-008');
    $teacherId = commandTeacher($schoolId, 'Guru Controller');
    $user = User::factory()->create(['role' => 'super_admin']);
    DB::table('school_user_roles')->insert([
        'school_id' => $schoolId,
        'user_id' => $user->id,
        'role' => 'staf_tu',
        'is_active' => true,
        'membership_status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('class_students')->insert([
        'school_class_id' => $classId,
        'student_id' => $studentId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)->withSession(['active_school_id' => $schoolId])
        ->post('/presensi/siswa', [
            'school_class_id' => $classId,
            'attendance_date' => '2026-09-14',
            'attendances' => [$studentId => 'D', $otherStudentId => 'H'],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('attendances');

    expect(StudentAttendance::count())->toBe(0);

    $this->post('/presensi/gtk', [
        'attendance_date' => '2026-09-14',
        'attendances' => [$teacherId => 'C'],
    ])->assertRedirect();

    expect(TeacherAttendance::query()->where('teacher_id', $teacherId)->value('status'))->toBe('C');
});
