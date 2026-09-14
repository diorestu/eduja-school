<?php

namespace App\Services;

use App\Models\StudentAttendance;
use App\Models\TeacherAttendance;
use App\Models\School;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class AttendanceService
{
    public function summary(int $schoolId, array $filters = []): array
    {
        if (! School::query()->whereKey($schoolId)->where('is_active', true)->where('status', 'active')->exists()) {
            throw new InvalidArgumentException('Sekolah aktif tidak ditemukan.');
        }

        [$startDate, $endDate] = $this->period($filters);
        $students = StudentAttendance::query()
            ->where('school_id', $schoolId)
            ->whereDate('attendance_date', '>=', $startDate)
            ->whereDate('attendance_date', '<=', $endDate)
            ->whereHas('student', fn ($query) => $query->where('school_id', $schoolId));
        $teachers = TeacherAttendance::query()
            ->where('school_id', $schoolId)
            ->whereDate('attendance_date', '>=', $startDate)
            ->whereDate('attendance_date', '<=', $endDate)
            ->whereHas('teacher', fn ($query) => $query->where('school_id', $schoolId));

        $studentCounts = $this->counts($students, StudentAttendance::STATUSES);
        $guruCounts = $this->counts((clone $teachers)->whereHas('teacher', fn ($query) => $query->where('role_type', 'Guru')), TeacherAttendance::STATUSES);
        $tendikCounts = $this->counts((clone $teachers)->whereHas('teacher', fn ($query) => $query->where('role_type', '!=', 'Guru')), TeacherAttendance::STATUSES);

        return [
            'students' => $studentCounts,
            'guru' => $guruCounts,
            'tendik' => $tendikCounts,
        ];
    }

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

    private function counts($query, array $statuses): array
    {
        $counts = array_fill_keys($statuses, 0);
        foreach ($query->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status') as $status => $count) {
            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) $count;
            }
        }

        return $counts;
    }

    private function period(array $filters): array
    {
        if (isset($filters['date']) || isset($filters['attendance_date'])) {
            $date = Carbon::parse($filters['date'] ?? $filters['attendance_date'])->toDateString();

            return [$date, $date];
        }

        if (isset($filters['start_date']) || isset($filters['end_date'])) {
            $start = Carbon::parse($filters['start_date'] ?? $filters['end_date'])->toDateString();
            $end = Carbon::parse($filters['end_date'] ?? $filters['start_date'])->toDateString();
            if ($start > $end) {
                throw new InvalidArgumentException('Periode presensi tidak valid.');
            }

            return [$start, $end];
        }

        $today = Carbon::today();
        $period = $filters['period'] ?? 'today';
        if ($period === 'week') {
            return [$today->copy()->startOfWeek()->toDateString(), $today->copy()->endOfWeek()->toDateString()];
        }
        if ($period === 'month') {
            return [$today->copy()->startOfMonth()->toDateString(), $today->copy()->endOfMonth()->toDateString()];
        }

        return [$today->toDateString(), $today->toDateString()];
    }
}
