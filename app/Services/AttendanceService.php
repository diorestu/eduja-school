<?php

namespace App\Services;

use App\Models\StudentAttendance;
use App\Models\TeacherAttendance;

class AttendanceService
{
    public function todaySummary(?int $schoolId = null): array
    {
        $students = StudentAttendance::query()->whereDate('attendance_date', today());
        $teachers = TeacherAttendance::query()->whereDate('attendance_date', today());

        if ($schoolId) {
            $students->where('school_id', $schoolId);
            $teachers->where('school_id', $schoolId);
        }

        return [
            'students_present' => (clone $students)->where('status', 'H')->count(),
            'teachers_present' => (clone $teachers)->where('status', 'H')->count(),
            'students_alpha' => (clone $students)->where('status', 'A')->count(),
        ];
    }
}
