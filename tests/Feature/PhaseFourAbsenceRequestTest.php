<?php

use App\Models\ApprovalRequest;
use App\Models\AttendanceRequest;
use App\Models\School;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\AbsenceRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function absenceSchool(string $name = 'Sekolah Absensi'): School
{
    return School::create([
        'name' => $name,
        'level' => 'sma',
        'ownership' => 'swasta',
        'is_active' => true,
        'status' => 'active',
    ]);
}

function absenceMembership(User $user, int $schoolId, string $role): void
{
    DB::table('school_user_roles')->insert([
        'user_id' => $user->id,
        'school_id' => $schoolId,
        'role' => $role,
        'is_active' => true,
        'membership_status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function absenceDates(): array
{
    return [
        'start_date' => today()->addDay()->toDateString(),
        'end_date' => today()->addDays(2)->toDateString(),
    ];
}

it('submits a linked student request and creates a school-scoped pending approval', function () {
    $school = absenceSchool();
    $studentUser = User::factory()->create(['role' => 'siswa']);
    $student = Student::create([
        'school_id' => $school->id,
        'user_id' => $studentUser->id,
        'nis' => 'ABS-001',
        'name' => 'Siswa Terhubung',
        'gender' => 'L',
        'is_active' => true,
        'status' => 'active',
    ]);

    $request = app(AbsenceRequestService::class)->submitStudent($school->id, $studentUser, [
        'subject_id' => $student->id,
        'request_type' => 'sakit',
        ...absenceDates(),
        'reason' => 'Demam dan perlu istirahat.',
        'document_path' => 'attendance-documents/surat-001.pdf',
        'document_name' => 'surat-001.pdf',
        'document_mime' => 'application/pdf',
    ]);

    expect($request)->toBeInstanceOf(AttendanceRequest::class)
        ->and($request->school_id)->toBe($school->id)
        ->and($request->requester_id)->toBe($studentUser->id)
        ->and($request->submitted_by)->toBe($studentUser->id)
        ->and($request->subject_type)->toBe(Student::class)
        ->and($request->subject_id)->toBe($student->id)
        ->and($request->request_type)->toBe('sakit')
        ->and($request->status)->toBe('pending')
        ->and($request->document_name)->toBe('surat-001.pdf')
        ->and($request->start_date)->toBeInstanceOf(DateTimeInterface::class)
        ->and($request->end_date)->toBeInstanceOf(DateTimeInterface::class);

    $approval = ApprovalRequest::query()->where('approvable_type', AttendanceRequest::class)
        ->where('approvable_id', $request->id)->first();

    expect($approval)->not->toBeNull()
        ->and($approval->school_id)->toBe($school->id)
        ->and($approval->requested_by)->toBe($studentUser->id)
        ->and($approval->type)->toBe('attendance')
        ->and($approval->status)->toBe('pending')
        ->and($request->approval_request_id)->toBe($approval->id);
});

it('allows a guardian-owned student only in the active school scope', function () {
    $school = absenceSchool('Sekolah Guardian');
    $otherSchool = absenceSchool('Sekolah Guardian Lain');
    $guardian = User::factory()->create(['role' => 'orang_tua']);
    $ownedStudent = Student::create([
        'school_id' => $school->id,
        'guardian_user_id' => $guardian->id,
        'nis' => 'ABS-002',
        'name' => 'Anak Wali',
        'gender' => 'P',
        'is_active' => true,
        'status' => 'active',
    ]);
    $otherStudent = Student::create([
        'school_id' => $otherSchool->id,
        'guardian_user_id' => $guardian->id,
        'nis' => 'ABS-003',
        'name' => 'Anak Sekolah Lain',
        'gender' => 'L',
        'is_active' => true,
        'status' => 'active',
    ]);

    $request = app(AbsenceRequestService::class)->submitStudent($school->id, $guardian, [
        'subject_id' => $ownedStudent->id,
        'request_type' => 'izin',
        ...absenceDates(),
        'reason' => 'Keperluan keluarga.',
    ]);

    expect($request->subject_id)->toBe($ownedStudent->id);

    expect(fn () => app(AbsenceRequestService::class)->submitStudent($school->id, $guardian, [
        'subject_id' => $otherStudent->id,
        'request_type' => 'izin',
        ...absenceDates(),
        'reason' => 'Tidak boleh lintas sekolah.',
    ]))->toThrow(InvalidArgumentException::class);
});

it('submits sakit or cuti for a linked active guru or tendik', function () {
    $school = absenceSchool('Sekolah GTK');
    $teacherUser = User::factory()->create(['role' => 'guru']);
    $teacher = Teacher::create([
        'school_id' => $school->id,
        'user_id' => $teacherUser->id,
        'nuptk' => 'GTK-001',
        'name' => 'Tendik Terhubung',
        'role_type' => 'Staf TU',
        'staff_type' => 'Honorer',
        'is_active' => true,
        'status' => 'active',
    ]);

    $request = app(AbsenceRequestService::class)->submitTeacher($school->id, $teacherUser, [
        'subject_id' => $teacher->id,
        'request_type' => 'cuti',
        ...absenceDates(),
        'reason' => 'Cuti tahunan.',
    ]);

    expect($request->subject_type)->toBe(Teacher::class)
        ->and($request->subject_id)->toBe($teacher->id)
        ->and($request->request_type)->toBe('cuti')
        ->and(ApprovalRequest::where('approvable_id', $request->id)->where('status', 'pending')->exists())->toBeTrue();
});

it('rejects invalid dates, unsupported types, missing reason, and overlapping live requests', function () {
    $school = absenceSchool('Sekolah Validasi');
    $studentUser = User::factory()->create(['role' => 'siswa']);
    $student = Student::create([
        'school_id' => $school->id,
        'user_id' => $studentUser->id,
        'nis' => 'ABS-004',
        'name' => 'Siswa Validasi',
        'gender' => 'L',
        'is_active' => true,
        'status' => 'active',
    ]);
    $service = app(AbsenceRequestService::class);

    expect(fn () => $service->submitStudent($school->id, $studentUser, [
        'subject_id' => $student->id,
        'request_type' => 'sakit',
        'start_date' => today()->subDay()->toDateString(),
        'end_date' => today()->toDateString(),
        'reason' => 'Tanggal lampau.',
    ]))->toThrow(InvalidArgumentException::class);

    expect(fn () => $service->submitStudent($school->id, $studentUser, [
        'subject_id' => $student->id,
        'request_type' => 'dispensasi',
        'start_date' => today()->addDays(2)->toDateString(),
        'end_date' => today()->addDay()->toDateString(),
        'reason' => 'Urutan salah.',
    ]))->toThrow(InvalidArgumentException::class);

    expect(fn () => $service->submitStudent($school->id, $studentUser, [
        'subject_id' => $student->id,
        'request_type' => 'cuti',
        ...absenceDates(),
        'reason' => 'Jenis tidak sesuai.',
    ]))->toThrow(InvalidArgumentException::class);

    expect(fn () => $service->submitStudent($school->id, $studentUser, [
        'subject_id' => $student->id,
        'request_type' => 'izin',
        ...absenceDates(),
    ]))->toThrow(InvalidArgumentException::class);

    $service->submitStudent($school->id, $studentUser, [
        'subject_id' => $student->id,
        'request_type' => 'izin',
        ...absenceDates(),
        'reason' => 'Permohonan pertama.',
    ]);

    expect(fn () => $service->submitStudent($school->id, $studentUser, [
        'subject_id' => $student->id,
        'request_type' => 'sakit',
        'start_date' => today()->addDays(2)->toDateString(),
        'end_date' => today()->addDays(3)->toDateString(),
        'reason' => 'Bertabrakan.',
    ]))->toThrow(InvalidArgumentException::class);
});

it('allows a rejected request to be resubmitted without creating attendance', function () {
    $school = absenceSchool('Sekolah Resubmit');
    $studentUser = User::factory()->create(['role' => 'siswa']);
    $student = Student::create([
        'school_id' => $school->id,
        'user_id' => $studentUser->id,
        'nis' => 'ABS-005',
        'name' => 'Siswa Resubmit',
        'gender' => 'L',
        'is_active' => true,
        'status' => 'active',
    ]);
    $service = app(AbsenceRequestService::class);
    $attributes = [
        'subject_id' => $student->id,
        'request_type' => 'sakit',
        ...absenceDates(),
        'reason' => 'Permohonan dapat diajukan ulang.',
    ];

    $first = $service->submitStudent($school->id, $studentUser, $attributes);
    $first->update(['status' => 'rejected']);

    $second = $service->submitStudent($school->id, $studentUser, $attributes);

    expect($second->id)->not->toBe($first->id)
        ->and($second->status)->toBe('pending')
        ->and(DB::table('student_attendances')->where('student_id', $student->id)->count())->toBe(0);
});

it('submits requests through authenticated school-scoped student and teacher routes', function () {
    $school = absenceSchool('Sekolah Route Request');
    $studentUser = User::factory()->create(['role' => 'siswa']);
    $teacherUser = User::factory()->create(['role' => 'guru']);
    absenceMembership($studentUser, $school->id, 'siswa');
    absenceMembership($teacherUser, $school->id, 'guru');
    $student = Student::create([
        'school_id' => $school->id,
        'user_id' => $studentUser->id,
        'nis' => 'ABS-006',
        'name' => 'Siswa Route',
        'gender' => 'P',
        'is_active' => true,
        'status' => 'active',
    ]);
    $teacher = Teacher::create([
        'school_id' => $school->id,
        'user_id' => $teacherUser->id,
        'nuptk' => 'GTK-002',
        'name' => 'Guru Route',
        'role_type' => 'Guru',
        'staff_type' => 'Honorer',
        'is_active' => true,
        'status' => 'active',
    ]);

    $this->actingAs($studentUser)->withSession(['active_school_id' => $school->id])
        ->from('/portal/siswa')
        ->post('/attendance/requests/student', [
            'subject_id' => $student->id,
            'request_type' => 'dispensasi',
            ...absenceDates(),
            'reason' => 'Kegiatan sekolah.',
        ])->assertRedirect('/portal/siswa');

    $this->actingAs($teacherUser)->withSession(['active_school_id' => $school->id])
        ->from('/portal/guru')
        ->post('/attendance/requests/teacher', [
            'subject_id' => $teacher->id,
            'request_type' => 'sakit',
            ...absenceDates(),
            'reason' => 'Perlu istirahat.',
        ])->assertRedirect('/portal/guru');

    expect(AttendanceRequest::where('school_id', $school->id)->count())->toBe(2);
});
