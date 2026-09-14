<?php

use App\Models\ApprovalRequest;
use App\Models\AttendanceRequest;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('adds phase four attendance metadata without removing daily uniqueness', function () {
    expect(Schema::hasColumns('student_attendances', [
        'school_id', 'source', 'rfid_uid', 'latitude', 'longitude', 'photo_path',
        'sync_status', 'request_id', 'reviewed_by', 'reviewed_at',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('teacher_attendances', [
            'school_id', 'source', 'rfid_uid', 'latitude', 'longitude', 'photo_path',
            'sync_status', 'request_id', 'reviewed_by', 'reviewed_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('attendance_requests', [
            'school_id', 'requester_id', 'subject_type', 'subject_id', 'request_type',
            'start_date', 'end_date', 'reason', 'document_path', 'document_name',
            'document_mime', 'status', 'approval_request_id', 'reviewed_by',
            'reviewed_at', 'review_note',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('approval_requests', [
            'school_id', 'requested_by', 'reviewed_by', 'approvable_type',
            'approvable_id', 'type', 'status', 'note', 'reviewed_at',
        ]))->toBeTrue()
        ->and(collect(Schema::getIndexes('student_attendances'))->pluck('name')->all())
        ->toContain('student_attendances_student_id_attendance_date_unique')
        ->and(collect(Schema::getIndexes('teacher_attendances'))->pluck('name')->all())
        ->toContain('teacher_attendances_teacher_id_attendance_date_unique');
});

it('exposes attendance status vocabularies and typed metadata', function () {
    expect(StudentAttendance::STATUSES)->toBe(['H', 'S', 'I', 'D', 'A'])
        ->and(TeacherAttendance::STATUSES)->toBe(['H', 'S', 'I', 'C', 'A', 'DL'])
        ->and(AttendanceRequest::STATUSES)->toBe(['pending', 'approved', 'rejected']);

    $studentAttendance = new StudentAttendance([
        'attendance_date' => '2026-09-14',
        'latitude' => '1.2345678',
        'longitude' => '2.3456789',
        'reviewed_at' => '2026-09-14 09:00:00',
    ]);

    expect($studentAttendance->attendance_date)->toBeInstanceOf(DateTimeInterface::class)
        ->and($studentAttendance->latitude)->toBe('1.2345678')
        ->and($studentAttendance->reviewed_at)->toBeInstanceOf(DateTimeInterface::class);
});

it('defines school, person, request, and approval ownership relationships', function () {
    expect((new StudentAttendance)->school())->toBeInstanceOf(BelongsTo::class)
        ->and((new StudentAttendance)->student())->toBeInstanceOf(BelongsTo::class)
        ->and((new StudentAttendance)->schoolClass())->toBeInstanceOf(BelongsTo::class)
        ->and((new TeacherAttendance)->school())->toBeInstanceOf(BelongsTo::class)
        ->and((new TeacherAttendance)->teacher())->toBeInstanceOf(BelongsTo::class)
        ->and((new AttendanceRequest)->school())->toBeInstanceOf(BelongsTo::class)
        ->and((new AttendanceRequest)->requester())->toBeInstanceOf(BelongsTo::class)
        ->and((new AttendanceRequest)->subject())->toBeInstanceOf(MorphTo::class)
        ->and((new AttendanceRequest)->approvalRequest())->toBeInstanceOf(BelongsTo::class)
        ->and((new ApprovalRequest)->school())->toBeInstanceOf(BelongsTo::class)
        ->and((new ApprovalRequest)->requester())->toBeInstanceOf(BelongsTo::class)
        ->and((new ApprovalRequest)->reviewer())->toBeInstanceOf(BelongsTo::class)
        ->and((new ApprovalRequest)->approvable())->toBeInstanceOf(MorphTo::class);
});
