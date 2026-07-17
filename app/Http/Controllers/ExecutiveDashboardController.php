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

class ExecutiveDashboardController extends Controller
{
    public function dinas(): View
    {
        return $this->render('Dashboard Dinas', School::query()->pluck('id')->all(), 'Agregat lintas sekolah untuk pemantauan dinas.');
    }

    public function yayasan(): View
    {
        $schoolIds = School::query()
            ->whereNotNull('foundation_name')
            ->pluck('id')
            ->all();

        if ($schoolIds === []) {
            $schoolIds = School::query()->pluck('id')->all();
        }

        return $this->render('Dashboard Yayasan', $schoolIds, 'Ringkasan sekolah dalam naungan yayasan aktif.');
    }

    private function render(string $title, array $schoolIds, string $description): View
    {
        $schoolScope = fn ($query) => $schoolIds === [] ? $query : $query->whereIn('school_id', $schoolIds);
        $totalCollected = (float) Transaction::sum('amount_paid');
        $totalInvoices = (float) $schoolScope(Invoice::query())->sum('total_amount');

        $metrics = [
            ['label' => 'Sekolah', 'value' => School::query()->when($schoolIds !== [], fn ($query) => $query->whereIn('id', $schoolIds))->count()],
            ['label' => 'Siswa', 'value' => $schoolScope(Student::query())->where('is_active', true)->count()],
            ['label' => 'Guru/GTK', 'value' => $schoolScope(Teacher::query())->where('is_active', true)->count()],
            ['label' => 'Kelas', 'value' => $schoolScope(SchoolClass::query())->count()],
            ['label' => 'Presensi Hari Ini', 'value' => $schoolScope(StudentAttendance::query())->whereDate('attendance_date', today())->count()],
            ['label' => 'SPP Terkumpul', 'value' => 'Rp '.number_format($totalCollected, 0, ',', '.')],
            ['label' => 'Tunggakan', 'value' => 'Rp '.number_format(max($totalInvoices - $totalCollected, 0), 0, ',', '.')],
            ['label' => 'Pengeluaran BOS', 'value' => 'Rp '.number_format((float) $schoolScope(Expense::query())->where('source_funding', 'BOS')->sum('amount'), 0, ',', '.')],
        ];

        return view('pages.foundation.index', [
            'title' => $title,
            'eyebrow' => 'Monitoring Eksekutif',
            'description' => $description,
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
