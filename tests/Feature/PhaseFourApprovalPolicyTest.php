<?php

use App\Models\ApprovalRequest;
use App\Models\AttendanceRequest;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\User;
use App\Services\AttendanceApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function approvalPolicySchool(string $name): School
{
    return School::create([
        'name' => $name,
        'level' => 'sma',
        'ownership' => 'swasta',
        'is_active' => true,
        'status' => 'active',
    ]);
}

function approvalPolicyMembership(User $user, School $school, string $role): void
{
    DB::table('school_user_roles')->insert([
        'user_id' => $user->id,
        'school_id' => $school->id,
        'role' => $role,
        'is_active' => true,
        'membership_status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function approvalPolicyStudent(School $school, User $user, string $nis = 'APP-001'): array
{
    $yearId = DB::table('academic_years')->insertGetId([
        'school_id' => $school->id,
        'year' => '2026/2027',
        'semester' => 'Ganjil',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $classId = DB::table('school_classes')->insertGetId([
        'school_id' => $school->id,
        'name' => 'X IPA 1',
        'grade' => 10,
        'academic_year_id' => $yearId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $student = Student::create([
        'school_id' => $school->id,
        'user_id' => $user->id,
        'nis' => $nis,
        'name' => 'Siswa Approval',
        'gender' => 'L',
        'is_active' => true,
        'status' => 'active',
    ]);
    DB::table('class_students')->insert([
        'school_class_id' => $classId,
        'student_id' => $student->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [$student, $classId];
}

function approvalPolicyTeacher(School $school, User $user, string $name = 'Guru Approval', string $roleType = 'Guru'): Teacher
{
    return Teacher::create([
        'school_id' => $school->id,
        'user_id' => $user->id,
        'nuptk' => 'GTK-'.str_replace(' ', '', $name),
        'name' => $name,
        'role_type' => $roleType,
        'staff_type' => 'Honorer',
        'is_active' => true,
        'status' => 'active',
    ]);
}

function approvalPolicyRequest(School $school, User $requester, string $subjectType, int $subjectId, string $requestType, string $date): ApprovalRequest
{
    $absence = AttendanceRequest::create([
        'school_id' => $school->id,
        'requester_id' => $requester->id,
        'submitted_by' => $requester->id,
        'subject_type' => $subjectType,
        'subject_id' => $subjectId,
        'request_type' => $requestType,
        'start_date' => $date,
        'end_date' => $date,
        'reason' => 'Alasan pengajuan approval.',
        'status' => 'pending',
    ]);
    $approval = ApprovalRequest::create([
        'school_id' => $school->id,
        'requested_by' => $requester->id,
        'approvable_type' => AttendanceRequest::class,
        'approvable_id' => $absence->id,
        'type' => 'attendance',
        'status' => 'pending',
    ]);
    $absence->update(['approval_request_id' => $approval->id]);

    return $approval->fresh();
}

it('approves a student request for an eligible same-school reviewer and maps its status atomically', function () {
    $school = approvalPolicySchool('Sekolah Approval Siswa');
    $requester = User::factory()->create(['role' => 'siswa']);
    $reviewer = User::factory()->create(['role' => 'guru']);
    approvalPolicyMembership($requester, $school, 'siswa');
    approvalPolicyMembership($reviewer, $school, 'wali_kelas');
    [$student, $classId] = approvalPolicyStudent($school, $requester);
    session(['active_school_id' => $school->id]);
    $approval = approvalPolicyRequest($school, $requester, Student::class, $student->id, 'dispensasi', '2026-09-16');

    $service = app(AttendanceApprovalService::class);

    expect($service->canReview($approval, $reviewer))->toBeTrue();

    $service->approve($approval, $reviewer, 'Bukti kegiatan diterima.');

    $attendance = StudentAttendance::query()->sole();
    expect($attendance->school_id)->toBe($school->id)
        ->and($attendance->student_id)->toBe($student->id)
        ->and($attendance->school_class_id)->toBe($classId)
        ->and($attendance->status)->toBe('D')
        ->and($attendance->request_id)->toBe($approval->approvable_id)
        ->and($attendance->reviewed_by)->toBe($reviewer->id)
        ->and($attendance->note)->toBe('Bukti kegiatan diterima.')
        ->and(AttendanceRequest::query()->sole()->status)->toBe('approved')
        ->and(AttendanceRequest::query()->sole()->reviewed_by)->toBe($reviewer->id)
        ->and(AttendanceRequest::query()->sole()->review_note)->toBe('Bukti kegiatan diterima.')
        ->and(ApprovalRequest::query()->sole()->status)->toBe('approved')
        ->and(ApprovalRequest::query()->sole()->reviewed_by)->toBe($reviewer->id);
});

it('maps sakit and cuti for guru and tendik requests to their attendance records', function () {
    $school = approvalPolicySchool('Sekolah Approval GTK');
    $guruRequester = User::factory()->create(['role' => 'guru']);
    $tendikRequester = User::factory()->create(['role' => 'tendik']);
    $reviewer = User::factory()->create(['role' => 'wakasek']);
    approvalPolicyMembership($guruRequester, $school, 'guru');
    approvalPolicyMembership($tendikRequester, $school, 'tendik');
    approvalPolicyMembership($reviewer, $school, 'wakasek');
    $guru = approvalPolicyTeacher($school, $guruRequester, 'Guru Sakit');
    $tendik = approvalPolicyTeacher($school, $tendikRequester, 'Tendik Cuti', 'Staf TU');
    session(['active_school_id' => $school->id]);
    $sakit = approvalPolicyRequest($school, $guruRequester, Teacher::class, $guru->id, 'sakit', '2026-09-17');
    $cuti = approvalPolicyRequest($school, $tendikRequester, Teacher::class, $tendik->id, 'cuti', '2026-09-18');
    $service = app(AttendanceApprovalService::class);

    expect($service->canReview($sakit, $reviewer))->toBeTrue()
        ->and($service->canReview($cuti, $reviewer))->toBeTrue();

    $service->approve($sakit, $reviewer);
    $service->approve($cuti, $reviewer);

    expect(TeacherAttendance::query()->where('teacher_id', $guru->id)->sole()->status)->toBe('S')
        ->and(TeacherAttendance::query()->where('teacher_id', $tendik->id)->sole()->status)->toBe('C');
});

it('denies self approval, inactive-school context, and cross-school subjects', function () {
    $schoolA = approvalPolicySchool('Sekolah Scope Approval A');
    $schoolB = approvalPolicySchool('Sekolah Scope Approval B');
    $requester = User::factory()->create(['role' => 'siswa']);
    $sameUserReviewer = $requester;
    $otherSchoolReviewer = User::factory()->create(['role' => 'wali_kelas']);
    approvalPolicyMembership($requester, $schoolA, 'wali_kelas');
    approvalPolicyMembership($otherSchoolReviewer, $schoolB, 'wali_kelas');
    [$student] = approvalPolicyStudent($schoolA, User::factory()->create(['role' => 'siswa']));
    $approval = approvalPolicyRequest($schoolA, $requester, Student::class, $student->id, 'sakit', '2026-09-19');
    $service = app(AttendanceApprovalService::class);

    session(['active_school_id' => $schoolA->id]);
    expect($service->canReview($approval, $sameUserReviewer))->toBeFalse()
        ->and($service->canReview($approval, $otherSchoolReviewer))->toBeFalse();

    session()->forget('active_school_id');
    expect($service->canReview($approval, $otherSchoolReviewer))->toBeFalse();
});

it('rejects an attendance request without changing an existing attendance record', function () {
    $school = approvalPolicySchool('Sekolah Reject Attendance');
    $requester = User::factory()->create(['role' => 'siswa']);
    $reviewer = User::factory()->create(['role' => 'tu']);
    approvalPolicyMembership($requester, $school, 'siswa');
    approvalPolicyMembership($reviewer, $school, 'tu');
    [$student, $classId] = approvalPolicyStudent($school, $requester, 'APP-002');
    session(['active_school_id' => $school->id]);
    $date = '2026-09-20';
    $existing = StudentAttendance::create([
        'school_id' => $school->id,
        'student_id' => $student->id,
        'school_class_id' => $classId,
        'attendance_date' => $date,
        'status' => 'H',
        'source' => 'rfid',
        'note' => 'Data awal',
    ]);
    $approval = approvalPolicyRequest($school, $requester, Student::class, $student->id, 'sakit', $date);

    app(AttendanceApprovalService::class)->reject($approval, $reviewer, 'Surat tidak cukup.');

    expect(AttendanceRequest::query()->sole()->status)->toBe('rejected')
        ->and(ApprovalRequest::query()->sole()->status)->toBe('rejected')
        ->and(StudentAttendance::query()->sole()->fresh()->status)->toBe('H')
        ->and(StudentAttendance::query()->sole()->fresh()->source)->toBe('rfid')
        ->and(StudentAttendance::query()->sole()->fresh()->id)->toBe($existing->id);
});

it('returns a handled-state error for duplicate decisions without mutation', function () {
    $school = approvalPolicySchool('Sekolah Duplicate Approval');
    $requester = User::factory()->create(['role' => 'siswa']);
    $reviewer = User::factory()->create(['role' => 'wali_kelas']);
    approvalPolicyMembership($requester, $school, 'siswa');
    approvalPolicyMembership($reviewer, $school, 'wali_kelas');
    [$student] = approvalPolicyStudent($school, $requester, 'APP-003');
    session(['active_school_id' => $school->id]);
    $approval = approvalPolicyRequest($school, $requester, Student::class, $student->id, 'izin', '2026-09-21');
    $service = app(AttendanceApprovalService::class);
    $service->approve($approval, $reviewer, 'Setuju.');

    expect(fn () => $service->reject($approval->fresh(), $reviewer, 'Tolak.'))
        ->toThrow(LogicException::class, 'Approval ini sudah diproses.');

    expect(ApprovalRequest::query()->sole()->status)->toBe('approved')
        ->and(AttendanceRequest::query()->sole()->status)->toBe('approved')
        ->and(StudentAttendance::query()->sole()->status)->toBe('I');
});

it('does not partially approve when the student has no class in the active school', function () {
    $school = approvalPolicySchool('Sekolah Atomic Approval');
    $requester = User::factory()->create(['role' => 'siswa']);
    $reviewer = User::factory()->create(['role' => 'wakasek']);
    approvalPolicyMembership($requester, $school, 'siswa');
    approvalPolicyMembership($reviewer, $school, 'wakasek');
    $student = Student::create([
        'school_id' => $school->id,
        'user_id' => $requester->id,
        'nis' => 'APP-004',
        'name' => 'Siswa Tanpa Kelas',
        'gender' => 'P',
        'is_active' => true,
        'status' => 'active',
    ]);
    session(['active_school_id' => $school->id]);
    $approval = approvalPolicyRequest($school, $requester, Student::class, $student->id, 'sakit', '2026-09-22');

    expect(fn () => app(AttendanceApprovalService::class)->approve($approval, $reviewer))
        ->toThrow(InvalidArgumentException::class);

    expect(ApprovalRequest::query()->sole()->status)->toBe('pending')
        ->and(AttendanceRequest::query()->sole()->status)->toBe('pending')
        ->and(StudentAttendance::query()->count())->toBe(0);
});
