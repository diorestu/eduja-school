<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\AttendanceRequest;
use App\Models\School;
use App\Models\SchoolUserRole;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

class AttendanceApprovalService
{
    public function approve(ApprovalRequest $approval, User $reviewer, ?string $note = null): void
    {
        $this->transition($approval, $reviewer, 'approved', $note);
    }

    public function reject(ApprovalRequest $approval, User $reviewer, ?string $note = null): void
    {
        $this->transition($approval, $reviewer, 'rejected', $note);
    }

    public function canReview(ApprovalRequest $approval, User $reviewer): bool
    {
        return $this->canReviewRequest($approval, $reviewer, true);
    }

    private function canReviewRequest(ApprovalRequest $approval, User $reviewer, bool $denySelfApproval): bool
    {
        $activeSchoolId = $this->activeSchoolId($reviewer);

        if (! $activeSchoolId || (int) $approval->school_id !== $activeSchoolId) {
            return false;
        }

        if ($approval->type !== 'attendance' || $approval->status !== 'pending') {
            return false;
        }

        $absence = $this->absenceRequest($approval);
        if (! $absence || (int) $absence->school_id !== $activeSchoolId || $absence->status !== 'pending') {
            return false;
        }

        if ($denySelfApproval && $this->requesterId($approval, $absence) === $reviewer->id) {
            return false;
        }

        if ($absence->subject_type && ! $this->subject($absence)) {
            return false;
        }

        return $this->hasAllowedRole($reviewer, $activeSchoolId, $this->allowedRoles($absence));
    }

    private function transition(ApprovalRequest $approval, User $reviewer, string $status, ?string $note): void
    {
        DB::transaction(function () use ($approval, $reviewer, $status, $note): void {
            $lockedApproval = ApprovalRequest::query()->lockForUpdate()->findOrFail($approval->id);
            $this->assertPending($lockedApproval);

            $absence = $this->absenceRequest($lockedApproval);
            if (! $absence || (int) $absence->school_id !== (int) $lockedApproval->school_id) {
                throw new AuthorizationException('Approval absensi tidak berada pada sekolah yang sesuai.');
            }

            $absence = AttendanceRequest::query()->lockForUpdate()->findOrFail($absence->id);
            $this->assertPending($absence);

            if (! $this->canReviewRequest($lockedApproval, $reviewer, $status === 'approved')) {
                throw new AuthorizationException('Reviewer tidak memiliki kewenangan untuk approval absensi ini.');
            }

            $subject = $this->subject($absence);
            if ($status === 'approved') {
                if (! $subject) {
                    throw new InvalidArgumentException('Subjek approval absensi tidak valid.');
                }

                $this->applyAttendance($absence, $subject, $reviewer, $note);
            }

            $reviewedAt = now();
            $absence->forceFill([
                'status' => $status,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => $reviewedAt,
                'review_note' => $note,
            ])->save();

            $lockedApproval->forceFill([
                'status' => $status,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => $reviewedAt,
                'note' => $note,
            ])->save();
        });
    }

    private function applyAttendance(AttendanceRequest $absence, Student|Teacher $subject, User $reviewer, ?string $note): void
    {
        $status = $this->attendanceStatus($absence, $subject);
        $start = Carbon::parse($absence->start_date);
        $end = Carbon::parse($absence->end_date ?: $absence->start_date);

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            if ($subject instanceof Student) {
                $this->applyStudentAttendance($absence, $subject, $reviewer, $status, $date, $note);
            } else {
                $this->applyTeacherAttendance($absence, $subject, $reviewer, $status, $date, $note);
            }
        }
    }

    private function applyStudentAttendance(
        AttendanceRequest $absence,
        Student $student,
        User $reviewer,
        string $status,
        Carbon $date,
        ?string $note,
    ): void {
        $attendance = StudentAttendance::query()
            ->where('school_id', $absence->school_id)
            ->where('student_id', $student->id)
            ->whereDate('attendance_date', $date)
            ->first();

        $classId = $attendance?->school_class_id ?: DB::table('class_students')
            ->join('school_classes', 'school_classes.id', '=', 'class_students.school_class_id')
            ->where('class_students.student_id', $student->id)
            ->where('school_classes.school_id', $absence->school_id)
            ->orderByDesc('class_students.id')
            ->value('class_students.school_class_id');

        if (! $classId) {
            throw new InvalidArgumentException('Kelas siswa tidak ditemukan pada sekolah aktif.');
        }

        $values = [
            'school_id' => $absence->school_id,
            'school_class_id' => $classId,
            'status' => $status,
            'request_id' => $absence->id,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ];
        if ($note !== null) {
            $values['note'] = $note;
        }

        if ($attendance) {
            $attendance->forceFill($values)->save();

            return;
        }

        StudentAttendance::query()->create([
            'student_id' => $student->id,
            'attendance_date' => $date->toDateString(),
            ...$values,
        ]);
    }

    private function applyTeacherAttendance(
        AttendanceRequest $absence,
        Teacher $teacher,
        User $reviewer,
        string $status,
        Carbon $date,
        ?string $note,
    ): void {
        $attendance = TeacherAttendance::query()
            ->where('school_id', $absence->school_id)
            ->where('teacher_id', $teacher->id)
            ->whereDate('attendance_date', $date)
            ->first();

        $values = [
            'school_id' => $absence->school_id,
            'status' => $status,
            'request_id' => $absence->id,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ];
        if ($note !== null) {
            $values['note'] = $note;
        }

        if ($attendance) {
            $attendance->forceFill($values)->save();

            return;
        }

        TeacherAttendance::query()->create([
            'teacher_id' => $teacher->id,
            'attendance_date' => $date->toDateString(),
            ...$values,
        ]);
    }

    private function attendanceStatus(AttendanceRequest $absence, Student|Teacher $subject): string
    {
        $statuses = $subject instanceof Student
            ? ['sakit' => 'S', 'izin' => 'I', 'dispensasi' => 'D']
            : ['sakit' => 'S', 'cuti' => 'C'];

        if (! isset($statuses[$absence->request_type])) {
            throw new InvalidArgumentException('Jenis approval absensi tidak sesuai dengan subjek.');
        }

        return $statuses[$absence->request_type];
    }

    private function activeSchoolId(User $reviewer): ?int
    {
        $schoolId = app(SchoolContext::class)->activeSchoolId();

        if (! $schoolId || ! School::query()
            ->whereKey($schoolId)
            ->where('is_active', true)
            ->where('status', 'active')
            ->exists()) {
            return null;
        }

        return (int) $schoolId;
    }

    private function hasAllowedRole(User $reviewer, int $schoolId, array $allowedRoles): bool
    {
        $roles = SchoolUserRole::query()
            ->where('user_id', $reviewer->id)
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->where('membership_status', 'active')
            ->pluck('role');

        if (empty($allowedRoles)) {
            return $roles->isNotEmpty();
        }

        return $roles->contains(fn (string $role): bool => in_array($role, $allowedRoles, true)
            || ($role === 'staf_tu' && in_array('tu', $allowedRoles, true)));
    }

    private function allowedRoles(AttendanceRequest $absence): array
    {
        if ($absence->subject_type === Teacher::class || $absence->request_type === 'cuti') {
            return ['kepsek', 'wakasek', 'tu'];
        }

        return ['wali_kelas', 'wakasek', 'tu'];
    }

    private function absenceRequest(ApprovalRequest $approval): ?AttendanceRequest
    {
        if ($approval->type !== 'attendance'
            || $approval->approvable_type !== AttendanceRequest::class
            || ! $approval->approvable_id) {
            return null;
        }

        return AttendanceRequest::query()->find($approval->approvable_id);
    }

    private function subject(AttendanceRequest $absence): Student|Teacher|null
    {
        if (! $absence->subject_type || ! $absence->subject_id) {
            return null;
        }

        if ($absence->subject_type === Student::class) {
            return Student::query()->whereKey($absence->subject_id)->where('school_id', $absence->school_id)->first();
        }

        if ($absence->subject_type === Teacher::class) {
            return Teacher::query()->whereKey($absence->subject_id)->where('school_id', $absence->school_id)->first();
        }

        return null;
    }

    private function requesterId(ApprovalRequest $approval, AttendanceRequest $absence): ?int
    {
        return $absence->requester_id ?: $absence->submitted_by ?: $approval->requested_by;
    }

    private function assertPending(ApprovalRequest|AttendanceRequest $model): void
    {
        if ($model->status !== 'pending') {
            throw new LogicException('Approval ini sudah diproses.');
        }
    }
}
