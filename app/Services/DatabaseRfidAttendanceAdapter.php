<?php

namespace App\Services;

use App\Contracts\RfidAttendanceAdapter;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Teacher;
use App\Models\TeacherAttendance;

class DatabaseRfidAttendanceAdapter implements RfidAttendanceAdapter
{
    public function resolve(string $uid, int $schoolId): array
    {
        $normalizedUid = strtoupper(trim($uid));
        if ($normalizedUid === '') {
            return ['reason' => 'RFID tidak terdaftar.'];
        }

        $studentLinks = StudentAttendance::query()
            ->whereRaw('UPPER(TRIM(rfid_uid)) = ?', [$normalizedUid])
            ->get(['student_id', 'school_id'])
            ->map(fn (StudentAttendance $attendance): array => ['type' => 'student', 'id' => $attendance->student_id, 'school_id' => $attendance->school_id]);
        $teacherLinks = TeacherAttendance::query()
            ->whereRaw('UPPER(TRIM(rfid_uid)) = ?', [$normalizedUid])
            ->get(['teacher_id', 'school_id'])
            ->map(fn (TeacherAttendance $attendance): array => ['type' => 'teacher', 'id' => $attendance->teacher_id, 'school_id' => $attendance->school_id]);
        $links = $studentLinks->concat($teacherLinks)->unique(fn (array $link): string => $link['type'].':'.$link['id']);

        if ($links->isEmpty()) {
            return ['reason' => 'RFID tidak terdaftar.'];
        }

        $activeLinks = $links->filter(function (array $link) use ($schoolId): bool {
            if ($link['school_id'] !== $schoolId) {
                return false;
            }

            return $link['type'] === 'student'
                ? Student::query()->active()->whereKey($link['id'])->where('school_id', $schoolId)->exists()
                : Teacher::query()->active()->whereKey($link['id'])->where('school_id', $schoolId)->exists();
        });

        if ($activeLinks->count() !== 1) {
            return ['reason' => $activeLinks->isEmpty()
                ? 'Identitas RFID tidak aktif atau tidak berada pada sekolah tersebut.'
                : 'RFID terhubung ke lebih dari satu identitas aktif.'];
        }

        $link = $activeLinks->first();

        return [
            'person_type' => $link['type'],
            'person_id' => (int) $link['id'],
            'school_id' => $schoolId,
            'source' => 'rfid',
            'rfid_uid' => $normalizedUid,
        ];
    }
}
