<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\BillingItem;
use App\Models\BillingPeriod;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Student;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillingService
{
    public function targetStudents(BillingItem $bill): Builder
    {
        $query = Student::where('school_id', $bill->school_id)->active();
        if ($bill->target_type !== 'pilihan') {
            return $query;
        }
        $query->when($bill->target_student_id, fn ($q) => $q->whereKey($bill->target_student_id))
            ->when($bill->target_generation, fn ($q) => $q->where('generation', $bill->target_generation));
        if ($bill->target_class_id || $bill->target_department_id) {
            $query->whereHas('schoolClasses', function ($q) use ($bill) {
                $q->where('school_classes.school_id', $bill->school_id)
                    ->where('school_classes.academic_year_id', $bill->academic_year_id)
                    ->when($bill->target_class_id, fn ($q) => $q->where('school_classes.id', $bill->target_class_id))
                    ->when($bill->target_department_id, fn ($q) => $q->where('school_classes.department_id', $bill->target_department_id));
            });
        }

        return $query;
    }

    public function create(int $schoolId, array $attributes): BillingItem
    {
        return DB::transaction(function () use ($schoolId, $attributes) {
            $year = AcademicYear::whereKey($attributes['academic_year_id'])
                ->where(fn ($q) => $q->where('school_id', $schoolId)->orWhereNull('school_id'))->firstOrFail();
            if ($attributes['billing_frequency'] === 'Bulanan') {
                if (! $year->start_date || ! $year->end_date || $year->end_date->lt($year->start_date)) {
                    throw ValidationException::withMessages(['academic_year_id' => 'Lengkapi tanggal awal dan akhir tahun ajaran untuk tagihan bulanan.']);
                }
                if (now()->startOfMonth()->gt($year->end_date)) {
                    throw ValidationException::withMessages(['academic_year_id' => 'Tahun ajaran ini sudah berakhir.']);
                }
                $first = now()->startOfMonth()->max($year->start_date->copy()->startOfMonth());
                $attributes['recurrence_start'] = $first->toDateString();
                $attributes['start_date'] = $this->onDay($first, (int) $attributes['billing_day'])->max($year->start_date)->toDateString();
                $attributes['due_date'] = $this->onDay($first, (int) $attributes['due_day'])->toDateString();
                if ($attributes['due_date'] < $attributes['start_date']) {
                    throw ValidationException::withMessages(['due_day' => 'Deadline harus setelah awal tahun ajaran dan tanggal mulai.']);
                }
            } else {
                $attributes['recurrence_start'] = $attributes['start_date'];
            }
            if (($attributes['target_type'] ?? 'school') === 'school') {
                foreach (['target_student_id', 'target_class_id', 'target_department_id', 'target_generation'] as $key) {
                    $attributes[$key] = null;
                }
            }
            $bill = new BillingItem([...$attributes, 'school_id' => $schoolId, 'status' => 'active']);
            if (! $this->targetStudents($bill)->exists()) {
                throw ValidationException::withMessages(['target_type' => 'Tidak ada siswa aktif yang sesuai dengan pilihan target.']);
            }
            $bill->save();
            $this->generate($bill, now());

            return $bill;
        });
    }

    public function onDay(CarbonInterface $month, int $day): Carbon
    {
        $date = Carbon::instance($month)->startOfMonth();

        return $date->day(min($day, $date->daysInMonth));
    }

    public function generate(BillingItem $bill, CarbonInterface $date): int
    {
        return DB::transaction(function () use ($bill, $date) {
            $bill = BillingItem::whereKey($bill->id)->lockForUpdate()->firstOrFail();
            if ($bill->status !== 'active' || ! $bill->recurrence_start) {
                return 0;
            }
            $dates = [];
            if ($bill->billing_frequency === 'Sekali' && $bill->start_date && $bill->due_date) {
                $dates[] = ['once', $bill->start_date, $bill->due_date];
            } elseif ($bill->billing_frequency === 'Bulanan' && $bill->recurrence_start && $bill->billing_day && $bill->due_day) {
                $year = $bill->academicYear;
                if (! $year?->start_date || ! $year?->end_date) {
                    return 0;
                }
                for ($month = $bill->recurrence_start->copy()->startOfMonth(); $month->lte($date) && $month->lte($year->end_date); $month->addMonth()) {
                    $start = $this->onDay($month, $bill->billing_day)->max($year->start_date);
                    $due = $this->onDay($month, $bill->due_day);
                    if ($start->lte($date) && $start->lte($year->end_date) && $due->gte($start)) {
                        $dates[] = [$month->format('Y-m'), $start, $due];
                    }
                }
            }
            $count = 0;
            foreach ($dates as [$key, $start, $due]) {
                if ($bill->periods()->where('period_key', $key)->exists()) {
                    continue;
                }
                $period = $bill->periods()->create(['school_id' => $bill->school_id, 'period_key' => $key, 'start_date' => $start, 'due_date' => $due, 'status' => $due->copy()->endOfDay()->lt($date) ? 'deactive' : 'active']);
                foreach ($this->targetStudents($bill)->get() as $student) {
                    $invoice = Invoice::create([
                        'school_id' => $bill->school_id, 'student_id' => $student->id, 'academic_year_id' => $bill->academic_year_id,
                        'billing_item_id' => $bill->id, 'billing_period_id' => $period->id,
                        'invoice_number' => 'KOM/'.$bill->id.'/'.$key.'/'.$student->id,
                        'due_date' => $due, 'total_amount' => $bill->amount, 'principal_amount' => $bill->amount,
                        'allow_installment' => $bill->allow_installment, 'minimum_installment' => $bill->minimum_installment,
                        'late_fee_per_day' => $bill->has_late_fee ? $bill->late_fee_per_day : 0,
                        'late_fee_maximum' => $bill->has_late_fee ? $bill->late_fee_maximum : 0, 'status' => 'Belum Lunas',
                    ]);
                    InvoiceItem::create(['invoice_id' => $invoice->id, 'name' => $bill->name, 'amount' => $bill->amount]);
                    $count++;
                }
            }

            return $count;
        });
    }

    public function process(CarbonInterface $date): int
    {
        $count = 0;
        BillingItem::where('status', 'active')->whereNotNull('recurrence_start')->whereNotNull('academic_year_id')->chunkById(100, function ($bills) use ($date, &$count) {
            foreach ($bills as $bill) {
                $count += $this->generate($bill, $date);
            }
        });
        BillingPeriod::where('due_date', '<', $date->toDateString())->where('status', 'active')->update(['status' => 'deactive']);
        Invoice::whereNotNull('billing_period_id')->where('status', '!=', 'Lunas')->chunkById(100, function ($invoices) use ($date) {
            foreach ($invoices as $invoice) {
                app(BillingPaymentService::class)->refreshCharges($invoice, $date);
            }
        });

        return $count;
    }

    public function activeBills(?int $schoolId = null)
    {
        return BillingItem::query()
            ->when($schoolId, fn ($query) => $query->where('school_id', $schoolId))
            ->latest()
            ->get();
    }
}
