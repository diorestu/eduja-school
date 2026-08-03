<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Transaction;
use App\Services\SchoolContext;

class DashboardController extends Controller
{
    public function index(SchoolContext $schoolContext)
    {
        $user = auth()->user();
        if ($user) {
            if ($user->hasRole('siswa')) {
                return redirect()->route('portal.siswa');
            }
            if ($user->hasRole('orang_tua')) {
                return redirect()->route('portal.orang-tua');
            }
        }

        $schoolId = $schoolContext->activeSchoolId();
        $scope = fn ($query) => $schoolId ? $query->where('school_id', $schoolId) : $query;

        // 1. Core operational stats
        $totalStudents = $scope(Student::query())->where('is_active', true)->count();
        $totalTeachers = $scope(Teacher::query())->where('is_active', true)->count();
        $totalClasses = $scope(SchoolClass::query())->count();

        // 2. Core financial stats (SPP + BOS)
        $totalCollected = (float) Transaction::sum('amount_paid'); // Income from SPP
        $totalExpenses = (float) $scope(Expense::query())->sum('amount'); // Expenditures
        $totalOutstanding = (float) ($scope(Invoice::query())->sum('total_amount') - $totalCollected); // Outstanding SPP

        $netBalance = $totalCollected - $totalExpenses; // Current cash balance

        // 3. Gender demographics
        $genderL = $scope(Student::query())->where('is_active', true)->where('gender', 'L')->count();
        $genderP = $scope(Student::query())->where('is_active', true)->where('gender', 'P')->count();

        // 4. Student distribution by class
        $classesWithCounts = $scope(SchoolClass::query())->withCount('students')->get();

        // 5. Recent transaction logs (SPP)
        $recentTransactions = Transaction::with(['invoice.student'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return view('pages.dashboard.school', [
            'title' => 'Dashboard Operasional & Keuangan Sekolah',
            'totalStudents' => $totalStudents,
            'totalTeachers' => $totalTeachers,
            'totalClasses' => $totalClasses,
            'totalCollected' => $totalCollected,
            'totalExpenses' => $totalExpenses,
            'totalOutstanding' => $totalOutstanding,
            'netBalance' => $netBalance,
            'genderL' => $genderL,
            'genderP' => $genderP,
            'classesWithCounts' => $classesWithCounts,
            'recentTransactions' => $recentTransactions,
        ]);
    }
}
