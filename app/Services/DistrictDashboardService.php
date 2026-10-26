<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use Carbon\Carbon;

class DistrictDashboardService
{
    public function dashboard(?School $selectedSchool): array
    {
        $schools = School::query()->where('is_active', true)->where('status', 'active')
            ->when($selectedSchool, fn ($query) => $query->whereKey($selectedSchool->id));
        $schoolIds = (clone $schools)->pluck('id');
        $financeSchoolIds = (clone $schools)->where('dinas_finance_allowed', true)->pluck('id');
        $months = collect(range(5, 0))->map(fn (int $offset) => now()->startOfMonth()->subMonths($offset));

        $dashboard = [
            'schoolBreakdown' => [
                'sd_mi' => (clone $schools)->whereIn('level', ['sd', 'mi'])->count(),
                'smp_mts' => (clone $schools)->whereIn('level', ['smp', 'mts'])->count(),
                'sma_ma' => (clone $schools)->whereIn('level', ['sma', 'ma'])->count(),
                'smk_mak' => (clone $schools)->whereIn('level', ['smk', 'mak'])->count(),
                'negeri' => (clone $schools)->where('ownership', 'negeri')->count(),
                'swasta' => (clone $schools)->where('ownership', 'swasta')->count(),
            ],
            'people' => [
                'students' => Student::whereIn('school_id', $schoolIds)->where('is_active', true)->count(),
                'teachers' => Teacher::whereIn('school_id', $schoolIds)->where('is_active', true)->where('role_type', 'Guru')->count(),
                'staff' => Teacher::whereIn('school_id', $schoolIds)->where('is_active', true)->where('role_type', '!=', 'Guru')->count(),
            ],
            'months' => $months->map(fn (Carbon $month) => $month->format('M Y'))->all(),
            'attendance' => [
                'students' => $months->map(fn (Carbon $month) => StudentAttendance::whereIn('school_id', $schoolIds)
                    ->where('status', 'H')->whereDate('attendance_date', '>=', $month)
                    ->whereDate('attendance_date', '<', $month->copy()->addMonth())->count())->all(),
                'teachers' => $months->map(fn (Carbon $month) => TeacherAttendance::whereIn('school_id', $schoolIds)
                    ->whereHas('teacher', fn ($query) => $query->whereIn('school_id', $schoolIds)->where('role_type', 'Guru'))
                    ->where('status', 'H')->whereDate('attendance_date', '>=', $month)
                    ->whereDate('attendance_date', '<', $month->copy()->addMonth())->count())->all(),
                'staff' => $months->map(fn (Carbon $month) => TeacherAttendance::whereIn('school_id', $schoolIds)
                    ->whereHas('teacher', fn ($query) => $query->whereIn('school_id', $schoolIds)->where('role_type', '!=', 'Guru'))
                    ->where('status', 'H')->whereDate('attendance_date', '>=', $month)
                    ->whereDate('attendance_date', '<', $month->copy()->addMonth())->count())->all(),
            ],
            'finance' => [
                'available' => $financeSchoolIds->isNotEmpty(),
                'schoolCount' => $financeSchoolIds->count(),
                'totalSchoolCount' => $schoolIds->count(),
            ],
        ];

        if ($financeSchoolIds->isNotEmpty()) {
            $dashboard['bos'] = $months->map(fn (Carbon $month) => (float) Expense::whereIn('school_id', $financeSchoolIds)
                ->where('status', 'approved')->whereRaw('LOWER(source_funding) LIKE ?', ['bos%'])
                ->whereDate('transaction_date', '>=', $month)->whereDate('transaction_date', '<', $month->copy()->addMonth())
                ->sum('amount'))->all();
        }

        return $dashboard;
    }
}
