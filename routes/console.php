<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\AcademicYear;
use App\Models\ClassStudent;
use App\Models\SppTariff;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Artisan command to automatically generate monthly SPP invoices.
 * Usage: php artisan spp:generate-billing
 */
Artisan::command('spp:generate-billing', function () {
    $activeYear = AcademicYear::where('is_active', true)->first();
    if (!$activeYear) {
        $this->error('Tidak ada Tahun Akademik yang aktif.');
        return;
    }

    $date = Carbon::now();
    $monthLabel = "SPP Bulanan - " . $date->translatedFormat('F') . " " . $date->year;

    $classStudents = ClassStudent::whereHas('schoolClass', function ($q) use ($activeYear) {
        $q->where('academic_year_id', $activeYear->id);
    })->with(['student', 'schoolClass'])->get();

    $generated = 0;
    
    DB::transaction(function () use ($classStudents, $activeYear, $monthLabel, &$generated) {
        foreach ($classStudents as $cs) {
            $student = $cs->student;
            $class = $cs->schoolClass;

            $existing = Invoice::where('student_id', $student->id)
                ->where('academic_year_id', $activeYear->id)
                ->whereHas('invoiceItems', function ($q) use ($monthLabel) {
                    $q->where('name', $monthLabel);
                })->exists();

            if ($existing) {
                continue;
            }

            $tariff = SppTariff::where('academic_year_id', $activeYear->id)
                ->where('type', 'Bulanan')
                ->where(function ($q) use ($class) {
                    $q->where('school_class_id', $class->id)->orWhereNull('school_class_id');
                })
                ->orderBy('school_class_id', 'desc')
                ->first();

            if (!$tariff) {
                continue;
            }

            $invoiceNumber = 'INV/' . Carbon::now()->format('Ymd') . '/' . $student->id . '/' . rand(100, 999);

            $invoice = Invoice::create([
                'student_id' => $student->id,
                'academic_year_id' => $activeYear->id,
                'invoice_number' => $invoiceNumber,
                'due_date' => Carbon::now()->endOfMonth()->toDateString(),
                'total_amount' => $tariff->amount,
                'status' => 'Belum Lunas'
            ]);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'spp_tariff_id' => $tariff->id,
                'name' => $monthLabel,
                'amount' => $tariff->amount
            ]);

            $generated++;
        }
    });

    $this->info("Berhasil membuat {$generated} invoice SPP baru untuk bulan " . $date->translatedFormat('F') . " " . $date->year . ".");
})->purpose('Generate monthly SPP billing automatically');

// Schedule monthly SPP billing generation on the 1st of every month at 00:00
Schedule::command('spp:generate-billing')->monthlyOn(1, '00:00');
