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
    public function dinas(Request $request, \App\Services\DistrictDashboardService $dashboardService): View
    {
        $validated = $request->validate(['school_id' => ['nullable', 'integer', 'min:1']]);
        $schools = School::query()->where('is_active', true)->where('status', 'active')->orderBy('name')->get();
        $selectedSchool = null;
        if (isset($validated['school_id'])) {
            $selectedSchool = $schools->firstWhere('id', (int) $validated['school_id']);
            abort_unless($selectedSchool, 404);
        }
        $districtDashboard = $dashboardService->dashboard($selectedSchool);

        return view('pages.foundation.dinas', compact('districtDashboard', 'schools', 'selectedSchool'));
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
        return School::query()->where('is_active', true)->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->where('user_id', $request->user()->id)->where('role', 'yayasan')->where('is_active', true)->where('membership_status', 'active'))
            ->orderBy('name')->get();
    }

    private function render(string $title, array $schoolIds, string $description, $foundationSchools = null, ?School $selectedSchool = null): View
    {
        $schoolScope = fn ($query) => $query->whereIn('school_id', $schoolIds);
        $totalCollected = (float) $schoolScope(Transaction::query())->sum('amount_paid');
        $totalInvoices = (float) $schoolScope(Invoice::query())->sum('total_amount');

        $metrics = [
            ['label' => 'Sekolah', 'value' => count($schoolIds)],
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
