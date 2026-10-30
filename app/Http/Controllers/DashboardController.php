<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Department;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\Transaction;
use App\Services\FinanceHealthService;
use App\Services\SchoolContext;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(SchoolContext $schoolContext, FinanceHealthService $financeHealthService)
    {
        $user = auth()->user();
        if ($user) {
            if ($user->hasRole('alumni')) {
                return redirect()->route('alumni.index');
            }
            if ($user->hasRole('siswa')) {
                return redirect()->route('portal.siswa');
            }
            if ($user->hasRole('orang_tua')) {
                return redirect()->route('portal.orang-tua');
            }
        }

        $schoolId = $schoolContext->activeSchoolId() ?? ($user ? ($schoolContext->ensureDefaultSchool($user)?->id ?? $schoolContext->availableSchools($user)->first()?->id) : null);
        if ($schoolId && ! session()->has('active_school_id')) {
            session(['active_school_id' => $schoolId]);
        }
        $scope = fn ($query) => $schoolId ? $query->where('school_id', $schoolId) : $query;

        // 1. Core operational stats
        $totalStudents = $scope(Student::query())->where('is_active', true)->count();
        $totalTeachers = $scope(Teacher::query())->where('is_active', true)->count();
        $totalClasses = $scope(SchoolClass::query())->count();

        // 2. Core financial stats (SPP + BOS)
        $invoices = $schoolId ? Invoice::ownedBySchool($schoolId)
            ->withSum(['transactions' => fn ($query) => $query->whereIn('status', ['approved', 'confirmed'])], 'amount_paid')
            ->get(['id', 'student_id', 'total_amount']) : collect();

        $totalCollected = (float) $invoices->sum(fn (Invoice $invoice) => (float) ($invoice->transactions_sum_amount_paid ?? 0));
        $totalExpenses = (float) $scope(Expense::query())->where('status', 'approved')->sum('amount'); // Approved expenditures
        $totalOutstanding = (float) $invoices->sum(fn (Invoice $invoice) => max(0, (float) $invoice->total_amount - (float) ($invoice->transactions_sum_amount_paid ?? 0))); // Outstanding SPP

        $netBalance = $totalCollected - $totalExpenses; // Current cash balance
        $financeHealth = $financeHealthService->forSchool($schoolId);

        // 3. Gender demographics
        $genderL = $scope(Student::query())->where('is_active', true)->where('gender', 'L')->count();
        $genderP = $scope(Student::query())->where('is_active', true)->where('gender', 'P')->count();

        // 4. Student distribution by class
        $classesWithCounts = $scope(SchoolClass::query())->withCount('students')->get();

        // 5. Recent transaction logs (SPP)
        $recentTransactions = Transaction::with(['invoice.student'])
            ->whereHas('invoice', fn ($query) => $schoolId ? $query->ownedBySchool($schoolId) : $query)
            ->whereIn('status', ['approved', 'confirmed'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        $months = collect(range(5, 0))->map(fn (int $offset) => now()->subMonths($offset)->startOfMonth());
        $attendanceSeries = [
            'months' => $months->map(fn (Carbon $month) => $month->format('M Y'))->values()->all(),
            'students' => $months->map(fn (Carbon $month) => StudentAttendance::where('school_id', $schoolId)->where('status', 'H')->whereDate('attendance_date', '>=', $month)->whereDate('attendance_date', '<', $month->copy()->addMonth())->count())->values()->all(),
            'teachers' => $months->map(fn (Carbon $month) => TeacherAttendance::where('school_id', $schoolId)->where('status', 'H')->whereHas('teacher', fn ($query) => $query->where('role_type', 'Guru'))->whereDate('attendance_date', '>=', $month)->whereDate('attendance_date', '<', $month->copy()->addMonth())->count())->values()->all(),
            'staff' => $months->map(fn (Carbon $month) => TeacherAttendance::where('school_id', $schoolId)->where('status', 'H')->whereHas('teacher', fn ($query) => $query->where('role_type', '!=', 'Guru'))->whereDate('attendance_date', '>=', $month)->whereDate('attendance_date', '<', $month->copy()->addMonth())->count())->values()->all(),
        ];
        $financeSeries = [
            'months' => $attendanceSeries['months'],
            'income' => $months->map(fn (Carbon $month) => (float) Transaction::whereHas('invoice', fn ($query) => $schoolId ? $query->ownedBySchool($schoolId) : $query)->whereIn('status', ['approved', 'confirmed'])->whereDate('payment_date', '>=', $month)->whereDate('payment_date', '<', $month->copy()->addMonth())->sum('amount_paid'))->values()->all(),
            'expenses' => $months->map(fn (Carbon $month) => (float) Expense::where('school_id', $schoolId)->where('status', 'approved')->whereDate('transaction_date', '>=', $month)->whereDate('transaction_date', '<', $month->copy()->addMonth())->sum('amount'))->values()->all(),
        ];

        return view('pages.dashboard.school', [
            'title' => 'Dashboard Operasional & Keuangan Sekolah',
            'totalStudents' => $totalStudents,
            'totalTeachers' => $totalTeachers,
            'totalStaff' => $scope(Teacher::query())->where('is_active', true)->where('role_type', '!=', 'Guru')->count(),
            'totalClasses' => $totalClasses,
            'departmentCount' => $schoolId ? Department::where('school_id', $schoolId)->count() : 0,
            'totalCollected' => $totalCollected,
            'totalExpenses' => $totalExpenses,
            'totalOutstanding' => $totalOutstanding,
            'netBalance' => $netBalance,
            'financeHealth' => $financeHealth,
            'genderL' => $genderL,
            'genderP' => $genderP,
            'classesWithCounts' => $classesWithCounts,
            'recentTransactions' => $recentTransactions,
            'latestAnnouncements' => $schoolId ? Announcement::where('school_id', $schoolId)->latest('published_at')->take(5)->get() : collect(),
            'attendanceSeries' => $attendanceSeries,
            'financeSeries' => $financeSeries,
            'financeDashboard' => $user?->hasRole('bendahara') && $schoolId ? app(\App\Services\FinanceDashboardService::class)->forSchool($schoolId) : null,
        ]);
    }
}
