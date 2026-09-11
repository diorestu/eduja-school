<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Teacher;
use App\Models\Transaction;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Carbon\Carbon;

class ExecutiveDashboardController extends Controller
{
    public function dinas(): View
    {
        $months = collect(range(5, 0))->map(fn (int $offset) => now()->subMonths($offset)->startOfMonth());
        $schoolQuery = School::query()->where('is_active', true);
        $schoolIds = (clone $schoolQuery)->pluck('id');
        $districtDashboard = [
            'schoolBreakdown' => [
                'sd_mi' => (clone $schoolQuery)->whereIn('level', ['sd', 'mi'])->count(),
                'smp_mts' => (clone $schoolQuery)->whereIn('level', ['smp', 'mts'])->count(),
                'sma_ma' => (clone $schoolQuery)->whereIn('level', ['sma', 'ma'])->count(),
                'smk_mak' => (clone $schoolQuery)->whereIn('level', ['smk', 'mak'])->count(),
                'negeri' => (clone $schoolQuery)->where('ownership', 'negeri')->count(),
                'swasta' => (clone $schoolQuery)->where('ownership', 'swasta')->count(),
            ],
            'people' => [
                'students' => Student::whereIn('school_id', $schoolIds)->where('is_active', true)->count(),
                'teachers' => Teacher::whereIn('school_id', $schoolIds)->where('is_active', true)->where('role_type', 'Guru')->count(),
                'staff' => Teacher::whereIn('school_id', $schoolIds)->where('is_active', true)->where('role_type', '!=', 'Guru')->count(),
            ],
            'months' => $months->map(fn (Carbon $month) => $month->format('M Y'))->values()->all(),
            'attendance' => [
                'students' => $months->map(fn (Carbon $month) => StudentAttendance::whereIn('school_id', $schoolIds)->whereDate('attendance_date', '>=', $month)->whereDate('attendance_date', '<', $month->copy()->addMonth())->where('status', 'H')->count())->values()->all(),
                'teachers' => $months->map(fn (Carbon $month) => \App\Models\TeacherAttendance::whereIn('school_id', $schoolIds)->whereHas('teacher', fn ($query) => $query->where('role_type', 'Guru'))->whereDate('attendance_date', '>=', $month)->whereDate('attendance_date', '<', $month->copy()->addMonth())->where('status', 'H')->count())->values()->all(),
                'staff' => $months->map(fn (Carbon $month) => \App\Models\TeacherAttendance::whereIn('school_id', $schoolIds)->whereHas('teacher', fn ($query) => $query->where('role_type', '!=', 'Guru'))->whereDate('attendance_date', '>=', $month)->whereDate('attendance_date', '<', $month->copy()->addMonth())->where('status', 'H')->count())->values()->all(),
            ],
            'bos' => $months->map(fn (Carbon $month) => (float) Expense::where('source_funding', 'BOS')->where('status', '!=', 'rejected')->whereDate('transaction_date', '>=', $month)->whereDate('transaction_date', '<', $month->copy()->addMonth())->sum('amount'))->values()->all(),
        ];

        return view('pages.foundation.dinas', compact('districtDashboard'));
    }

    public function yayasan(Request $request): View
    {
        $foundationSchools = $this->foundationSchools($request);
        $schoolIds = $foundationSchools->pluck('id')->all();

        return $this->render('Dashboard Yayasan', $schoolIds, 'Ringkasan sekolah dalam naungan yayasan aktif.', $foundationSchools);
    }

    public function yayasanSchool(Request $request, School $school): View
    {
        $foundationSchools = $this->foundationSchools($request);
        abort_unless($foundationSchools->contains('id', $school->id), 403);

        return $this->render('Detail '.$school->name, [$school->id], 'Statistik sekolah dalam cakupan yayasan.', $foundationSchools, $school);
    }

    private function foundationSchools(Request $request)
    {
        return School::query()->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->where('user_id', $request->user()->id)->where('role', 'yayasan')->where('is_active', true))
            ->orderBy('name')->get();
    }

    private function render(string $title, array $schoolIds, string $description, $foundationSchools = null, ?School $selectedSchool = null): View
    {
        $schoolScope = fn ($query) => $schoolIds === [] ? $query : $query->whereIn('school_id', $schoolIds);
        $totalCollected = (float) $schoolScope(Transaction::query())->sum('amount_paid');
        $totalInvoices = (float) $schoolScope(Invoice::query())->sum('total_amount');

        $metrics = [
            ['label' => 'Sekolah', 'value' => School::query()->when($schoolIds !== [], fn ($query) => $query->whereIn('id', $schoolIds))->count()],
            ['label' => 'Siswa', 'value' => $schoolScope(Student::query())->where('is_active', true)->count()],
            ['label' => 'Guru/GTK', 'value' => $schoolScope(Teacher::query())->where('is_active', true)->count()],
            ['label' => 'Kelas', 'value' => $schoolScope(SchoolClass::query())->count()],
            ['label' => 'Presensi Hari Ini', 'value' => $schoolScope(StudentAttendance::query())->whereDate('attendance_date', today())->count()],
            ['label' => 'SPP Terkumpul', 'value' => 'Rp '.number_format($totalCollected, 0, ',', '.')],
            ['label' => 'Tunggakan', 'value' => 'Rp '.number_format(max($totalInvoices - $totalCollected, 0), 0, ',', '.')],
            ['label' => 'Pengeluaran BOS', 'value' => 'Rp '.number_format((float) $schoolScope(Expense::query())->where('status', 'approved')->where('source_funding', 'BOS')->sum('amount'), 0, ',', '.')],
        ];

        return view('pages.foundation.index', [
            'title' => $title,
            'eyebrow' => 'Monitoring Eksekutif',
            'description' => $description,
            'foundationSchools' => $foundationSchools,
            'selectedSchool' => $selectedSchool,
            'metrics' => $metrics,
            'sections' => [
                [
                    'title' => 'Prioritas Operasional',
                    'items' => [
                        'Pantau sekolah tanpa login ulang per unit.',
                        'Bandingkan presensi, tunggakan, dan serapan BOS dari satu layar.',
                        'Gunakan data sekolah aktif sebagai filter default laporan.',
                    ],
                ],
            ],
        ]);
    }
}
