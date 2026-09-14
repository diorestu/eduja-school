<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\AttendanceRequest;
use App\Models\School;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AbsenceRequestService
{
    private const STUDENT_REQUEST_TYPES = ['sakit', 'izin', 'dispensasi'];

    private const TEACHER_REQUEST_TYPES = ['sakit', 'cuti'];

    public function submitStudent(int $schoolId, User $requester, array $attributes): AttendanceRequest
    {
        $this->assertActiveSchool($schoolId);

        $subjectId = $this->subjectId($attributes);
        $student = Student::query()
            ->active()
            ->where('school_id', $schoolId)
            ->whereKey($subjectId)
            ->where(fn ($query) => $query
                ->where('user_id', $requester->id)
                ->orWhere('guardian_user_id', $requester->id))
            ->first();

        if (! $student) {
            throw new InvalidArgumentException('Siswa tidak terhubung dengan pemohon pada sekolah aktif.');
        }

        return $this->submit($schoolId, $requester, Student::class, $student->id, $attributes, self::STUDENT_REQUEST_TYPES);
    }

    public function submitTeacher(int $schoolId, User $requester, array $attributes): AttendanceRequest
    {
        $this->assertActiveSchool($schoolId);

        $subjectId = $this->subjectId($attributes);
        $teacher = Teacher::query()
            ->active()
            ->where('school_id', $schoolId)
            ->whereKey($subjectId)
            ->where('user_id', $requester->id)
            ->first();

        if (! $teacher) {
            throw new InvalidArgumentException('Guru atau tendik tidak terhubung dengan pemohon pada sekolah aktif.');
        }

        return $this->submit($schoolId, $requester, Teacher::class, $teacher->id, $attributes, self::TEACHER_REQUEST_TYPES);
    }

    public function validateDates(int $schoolId, string $subjectType, int $subjectId, string $start, string $end): void
    {
        $this->assertActiveSchool($schoolId);
        $startDate = $this->parseDate($start);
        $endDate = $this->parseDate($end);

        if ($startDate->isBefore(today())) {
            throw new InvalidArgumentException('Tanggal mulai tidak boleh berada di masa lalu.');
        }

        if ($endDate->isBefore($startDate)) {
            throw new InvalidArgumentException('Tanggal selesai harus sama atau setelah tanggal mulai.');
        }

        $overlaps = AttendanceRequest::query()
            ->where('school_id', $schoolId)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('start_date', '<=', $endDate->toDateString())
            ->where(fn ($query) => $query
                ->whereNull('end_date')
                ->orWhereDate('end_date', '>=', $startDate->toDateString()))
            ->exists();

        if ($overlaps) {
            throw new InvalidArgumentException('Pengajuan pada rentang tanggal tersebut sudah menunggu atau telah mendapat persetujuan.');
        }
    }

    private function submit(
        int $schoolId,
        User $requester,
        string $subjectType,
        int $subjectId,
        array $attributes,
        array $allowedTypes,
    ): AttendanceRequest {
        $requestType = $this->requestType($attributes, $allowedTypes);
        $reason = trim((string) ($attributes['reason'] ?? ''));

        if ($reason === '') {
            throw new InvalidArgumentException('Alasan pengajuan wajib diisi.');
        }

        $start = $this->parseDate((string) ($attributes['start_date'] ?? ''));
        $end = $this->parseDate((string) ($attributes['end_date'] ?? $start->toDateString()));
        $this->validateDates($schoolId, $subjectType, $subjectId, $start->toDateString(), $end->toDateString());

        return DB::transaction(function () use ($schoolId, $requester, $subjectType, $subjectId, $requestType, $reason, $start, $end, $attributes): AttendanceRequest {
            $absenceRequest = AttendanceRequest::query()->create([
                'school_id' => $schoolId,
                'requester_id' => $requester->id,
                'submitted_by' => $requester->id,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'request_type' => $requestType,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'reason' => $reason,
                'document_path' => $attributes['document_path'] ?? null,
                'document_name' => $attributes['document_name'] ?? null,
                'document_mime' => $attributes['document_mime'] ?? null,
                'status' => 'pending',
            ]);

            $approval = ApprovalRequest::query()->create([
                'school_id' => $schoolId,
                'requested_by' => $requester->id,
                'approvable_type' => AttendanceRequest::class,
                'approvable_id' => $absenceRequest->id,
                'type' => 'attendance',
                'status' => 'pending',
            ]);

            $absenceRequest->update(['approval_request_id' => $approval->id]);

            return $absenceRequest->fresh();
        });
    }

    private function subjectId(array $attributes): int
    {
        $subjectId = filter_var($attributes['subject_id'] ?? null, FILTER_VALIDATE_INT);

        if (! $subjectId || $subjectId < 1) {
            throw new InvalidArgumentException('Subjek pengajuan tidak valid.');
        }

        return $subjectId;
    }

    private function requestType(array $attributes, array $allowedTypes): string
    {
        $requestType = strtolower(trim((string) ($attributes['request_type'] ?? '')));

        if (! in_array($requestType, $allowedTypes, true)) {
            throw new InvalidArgumentException('Jenis pengajuan tidak sesuai dengan subjek.');
        }

        return $requestType;
    }

    private function parseDate(string $value): Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new InvalidArgumentException('Tanggal pengajuan tidak valid.');
        }

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            throw new InvalidArgumentException('Tanggal pengajuan tidak valid.');
        }

        if (! $date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Tanggal pengajuan tidak valid.');
        }

        return $date;
    }

    private function assertActiveSchool(int $schoolId): void
    {
        if (! School::query()->whereKey($schoolId)->where('is_active', true)->where('status', 'active')->exists()) {
            throw new InvalidArgumentException('Sekolah aktif tidak ditemukan.');
        }
    }
}
