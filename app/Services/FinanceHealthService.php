<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Student;

class FinanceHealthService
{
    public function forSchool(?int $schoolId): array
    {
        if (! $schoolId) {
            return $this->fromTotals(0, 0, 0, 0);
        }

        $invoices = Invoice::query()
            ->where('school_id', $schoolId)
            ->withSum('transactions', 'amount_paid')
            ->get(['id', 'student_id', 'total_amount']);

        $totalBilled = (float) $invoices->sum('total_amount');
        $totalPaid = (float) $invoices->sum(fn (Invoice $invoice) => (float) ($invoice->transactions_sum_amount_paid ?? 0));
        $totalOutstanding = (float) $invoices->sum(fn (Invoice $invoice) => max(0, (float) $invoice->total_amount - (float) ($invoice->transactions_sum_amount_paid ?? 0)));
        $delinquentStudents = $invoices
            ->filter(fn (Invoice $invoice) => (float) ($invoice->transactions_sum_amount_paid ?? 0) < (float) $invoice->total_amount)
            ->pluck('student_id')
            ->unique()
            ->count();
        $activeStudents = Student::query()->where('school_id', $schoolId)->active()->count();

        return $this->fromTotals($totalBilled, $totalPaid, $totalOutstanding, $activeStudents, $delinquentStudents);
    }

    private function fromTotals(float $totalBilled, float $totalPaid, float $totalOutstanding, int $activeStudents, int $delinquentStudents = 0): array
    {
        $collectionRate = $totalBilled > 0 ? round(($totalPaid / $totalBilled) * 100, 1) : 0.0;
        $arrearsRate = $totalBilled > 0 ? round(($totalOutstanding / $totalBilled) * 100, 1) : 0.0;
        $delinquentStudentRate = $activeStudents > 0 ? round(($delinquentStudents / $activeStudents) * 100, 1) : 0.0;

        $noBilling = [
            'percentage' => 0.0,
            'label' => 'Belum Ada Tagihan',
            'headline' => 'Belum ada tagihan untuk dihitung.',
            'description' => 'Buat tagihan terlebih dahulu agar indikator pembayaran dan tunggakan dapat dipantau.',
            'color' => 'green',
        ];

        return [
            'hasBilling' => $totalBilled > 0,
            'totalBilled' => $totalBilled,
            'totalPaid' => $totalPaid,
            'totalOutstanding' => $totalOutstanding,
            'collection' => $totalBilled > 0 ? $this->collectionHealth($collectionRate) : $noBilling,
            'arrears' => $totalBilled > 0 ? $this->arrearsHealth($arrearsRate) : $noBilling,
            'delinquentStudents' => [
                'count' => $delinquentStudents,
                'total' => $activeStudents,
                'percentage' => $delinquentStudentRate,
            ],
        ];
    }

    private function collectionHealth(float $percentage): array
    {
        foreach ([
            [90, 'Sangat Lancar', 'Arus pembayaran sekolah sangat sehat.', 'Hampir seluruh tagihan telah tertagih. EDUJA melihat kondisi pembayaran yang sangat lancar dan stabil.', 'green'],
            [75, 'Lancar', 'Pembayaran berjalan dengan baik.', 'Sebagian besar tagihan telah tertagih. Tinggal beberapa pembayaran yang perlu ditindaklanjuti.', 'green'],
            [60, 'Perlu Perhatian', 'Pembayaran Mulai Melambat', 'Sebagian tagihan masih menunggu pembayaran. Pembayaran masih berjalan, tetapi ada cukup banyak tagihan yang perlu diperhatikan agar arus kas tetap terjaga.', 'yellow'],
            [40, 'Perlu Tindakan', 'EDUJA menemukan cukup banyak tagihan yang belum tertagih.', 'Kondisi ini mulai memengaruhi kelancaran pemasukan sekolah. Pertimbangkan untuk menindaklanjuti tagihan yang sudah jatuh tempo.', 'orange'],
            [25, 'Tertahan', 'Sebagian besar tagihan masih menunggu pembayaran.', 'Pemasukan dari tagihan berjalan cukup lambat. EDUJA menyarankan bendahara memeriksa daftar tunggakan dan melakukan tindak lanjut.', 'red'],
            [0, 'Sangat Tertahan', 'Pemasukan dari tagihan masih sangat terbatas.', 'Sebagian besar tagihan belum tertagih. Periksa tagihan yang jatuh tempo dan prioritaskan tindak lanjut pembayaran.', 'red'],
        ] as [$minimum, $label, $headline, $description, $color]) {
            if ($percentage >= $minimum) {
                return compact('percentage', 'label', 'headline', 'description', 'color');
            }
        }
    }

    private function arrearsHealth(float $percentage): array
    {
        foreach ([
            [0, 5, 'Sangat Terkendali', 'Pembayaran murid sangat tertib.', 'Hanya sebagian kecil tagihan yang masih tertunggak. Kondisi tunggakan sekolah sangat terkendali.', 'green'],
            [5, 10, 'Terkendali', 'Sebagian besar pembayaran berjalan tertib.', 'Tunggakan masih berada pada tingkat yang relatif terkendali. Tetap pantau tagihan yang mulai melewati jatuh tempo.', 'green'],
            [10, 15, 'Mulai Meningkat', 'Beberapa tunggakan mulai membutuhkan perhatian.', 'EDUJA menemukan peningkatan jumlah tagihan yang belum terselesaikan. Periksa murid dengan tunggakan terbesar atau terlama.', 'yellow'],
            [15, 25, 'Perlu Ditangani', 'Tunggakan mulai berpengaruh pada arus pemasukan.', 'Sebagian tagihan belum terselesaikan. EDUJA menyarankan peninjauan daftar tunggakan dan tindak lanjut pembayaran.', 'orange'],
            [25, 40, 'Tinggi', 'Cukup banyak tagihan masih tertunda.', 'Tingkat tunggakan sudah cukup tinggi dan dapat menghambat arus kas sekolah. Prioritaskan tagihan yang telah lama tertunda.', 'red'],
            [40, INF, 'Sangat Tinggi', 'Porsi tagihan tertunda sangat besar.', 'Sebagian besar tagihan belum terselesaikan. EDUJA menyarankan bendahara memprioritaskan penanganan tunggakan dan memantau arus kas sekolah.', 'red'],
        ] as [$minimum, $maximum, $label, $headline, $description, $color]) {
            if ($percentage >= $minimum && $percentage <= $maximum) {
                return compact('percentage', 'label', 'headline', 'description', 'color');
            }
        }
    }
}
