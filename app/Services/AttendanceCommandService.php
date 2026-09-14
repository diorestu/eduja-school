<?php

namespace App\Services;

use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class AttendanceCommandService
{
    public function recordStudent(int $schoolId, int $studentId, array $attributes): StudentAttendance
    {
        $this->assertActiveSchool($schoolId);

        $student = Student::query()->active()->whereKey($studentId)->where('school_id', $schoolId)->first();
        if (! $student) {
            throw new InvalidArgumentException('Siswa tidak aktif atau tidak berada pada sekolah aktif.');
        }

        $classId = (int) ($attributes['school_class_id'] ?? 0);
        $class = SchoolClass::query()->whereKey($classId)->where('school_id', $schoolId)->first();
        if (! $class || ! DB::table('class_students')->where('school_class_id', $classId)->where('student_id', $studentId)->exists()) {
            throw new InvalidArgumentException('Kelas siswa tidak berada pada sekolah aktif atau siswa tidak terdaftar di kelas tersebut.');
        }

        $date = $this->attendanceDate($attributes);
        $status = $this->status($attributes, StudentAttendance::STATUSES);

        return DB::transaction(function () use ($schoolId, $studentId, $classId, $date, $status, $attributes): StudentAttendance {
            $values = $this->values($schoolId, $classId, $status, $attributes);
            $attendance = StudentAttendance::query()
                ->where('student_id', $studentId)
                ->whereDate('attendance_date', $date)
                ->first();

            if ($attendance) {
                $attendance->fill($values);
                $attendance->save();

                return $attendance;
            }

            return StudentAttendance::query()->create([
                'student_id' => $studentId,
                'attendance_date' => $date,
                ...$values,
            ]);
        });
    }

    public function recordTeacher(int $schoolId, int $teacherId, array $attributes): TeacherAttendance
    {
        $this->assertActiveSchool($schoolId);

        if (! Teacher::query()->active()->whereKey($teacherId)->where('school_id', $schoolId)->exists()) {
            throw new InvalidArgumentException('GTK tidak aktif atau tidak berada pada sekolah aktif.');
        }

        $date = $this->attendanceDate($attributes);
        $status = $this->status($attributes, TeacherAttendance::STATUSES);

        return DB::transaction(function () use ($schoolId, $teacherId, $date, $status, $attributes): TeacherAttendance {
            $values = $this->values($schoolId, null, $status, $attributes);
            $attendance = TeacherAttendance::query()
                ->where('teacher_id', $teacherId)
                ->whereDate('attendance_date', $date)
                ->first();

            if ($attendance) {
                $attendance->fill($values);
                $attendance->save();

                return $attendance;
            }

            return TeacherAttendance::query()->create([
                'teacher_id' => $teacherId,
                'attendance_date' => $date,
                ...$values,
            ]);
        });
    }

    private function assertActiveSchool(int $schoolId): void
    {
        if (! School::query()->whereKey($schoolId)->where('is_active', true)->where('status', 'active')->exists()) {
            throw new InvalidArgumentException('Sekolah aktif tidak ditemukan.');
        }
    }

    private function attendanceDate(array $attributes): string
    {
        if (! isset($attributes['attendance_date'])) {
            throw new InvalidArgumentException('Tanggal presensi wajib diisi.');
        }

        try {
            return Carbon::parse($attributes['attendance_date'])->toDateString();
        } catch (Throwable) {
            throw new InvalidArgumentException('Tanggal presensi tidak valid.');
        }
    }

    private function status(array $attributes, array $allowed): string
    {
        $status = $attributes['status'] ?? null;
        if (! is_string($status) || ! in_array($status, $allowed, true)) {
            throw new InvalidArgumentException('Status presensi tidak valid.');
        }

        return $status;
    }

    private function values(int $schoolId, ?int $classId, string $status, array $attributes): array
    {
        $values = [
            'school_id' => $schoolId,
            'status' => $status,
        ];

        if ($classId !== null) {
            $values['school_class_id'] = $classId;
        }

        foreach ([
            'clock_in_at', 'clock_out_at', 'source', 'rfid_uid', 'latitude', 'longitude',
            'photo_path', 'sync_status', 'note', 'request_id', 'reviewed_by', 'reviewed_at',
        ] as $field) {
            if (array_key_exists($field, $attributes)) {
                $values[$field] = $attributes[$field];
            }
        }

        if (array_key_exists('source', $values) && ! in_array($values['source'], ['manual', 'rfid', 'gps'], true)) {
            throw new InvalidArgumentException('Sumber presensi tidak valid.');
        }

        return $values;
    }
}
