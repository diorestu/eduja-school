<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Student;
use Illuminate\Support\Collection;

class StudentArrearsService
{
    public function invoices(int $schoolId, array $filters = [], ?int $studentId = null): Collection
    {
        $query = Invoice::ownedBySchool($schoolId)->with(['billingPeriod', 'invoiceItems'])
            ->withSum(['transactions as approved_paid_total' => fn ($q) => $q->whereIn('status', ['approved', 'confirmed'])], 'amount_paid')
            ->when($studentId, fn ($q) => $q->where('student_id', $studentId));
        if ($filters['committee_only'] ?? false) {
            $query->committeeOnly($schoolId);
        }
        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->whereHas('student', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('nis', 'like', $term)->orWhere('nisn', 'like', $term)));
        }
        if (! empty($filters['class_id'])) {
            $query->whereHas('student.schoolClasses', fn ($q) => $q->where('school_classes.school_id', $schoolId)->where('school_classes.id', $filters['class_id']));
        }
        if (! empty($filters['month']) || ! empty($filters['year'])) {
            $periodFilter = function ($q, string $column) use ($filters) {
                $q->when($filters['month'] ?? null, fn ($q, $month) => $q->whereMonth($column, $month))
                    ->when($filters['year'] ?? null, fn ($q, $year) => $q->whereYear($column, $year));
            };
            $query->where(function ($q) use ($periodFilter) {
                $q->whereHas('billingPeriod', fn ($period) => $periodFilter($period, 'start_date'))
                    ->orWhere(function ($legacy) use ($periodFilter) {
                        $legacy->whereNull('billing_period_id');
                        $periodFilter($legacy, 'due_date');
                    });
            });
        }
        $invoices = $query->orderBy('due_date')->orderBy('id')->get();
        foreach ($invoices as $invoice) {
            app(BillingPaymentService::class)->previewCharges($invoice, now());
        }

        return $invoices;
    }

    public function status(Invoice $invoice): string
    {
        if ($invoice->remaining_amount <= 0 || $invoice->status === 'Lunas') {
            return 'Lunas';
        }
        if ($invoice->due_date->copy()->endOfDay()->lt(now())) {
            return 'Menunggak';
        }

        return $invoice->paid_amount > 0 ? 'Cicil' : 'Belum Bayar';
    }

    public function studentRow(Student $student, Collection $invoices): array
    {
        $unpaid = $invoices->filter(fn ($inv) => $inv->remaining_amount > 0 && $inv->status !== 'Lunas');
        $classes = $student->schoolClasses->where('school_id', $student->school_id);
        $current = $classes->first(fn ($class) => $class->academicYear?->is_active) ?? $classes->sortByDesc(fn ($class) => $class->academicYear?->start_date?->timestamp ?? $class->academic_year_id)->first();
        $status = 'Lunas';
        if ($unpaid->isNotEmpty()) {
            $status = $unpaid->contains(fn ($inv) => $this->status($inv) === 'Menunggak') ? 'Menunggak'
                : ($unpaid->contains(fn ($inv) => $inv->paid_amount > 0) ? 'Cicil' : 'Belum Bayar');
        }

        return ['student' => $student, 'class_name' => $current?->name ?? '-', 'class_id' => $current?->id,
            'total_arrears' => $unpaid->sum(fn ($inv) => $inv->remaining_amount), 'status' => $status, 'invoices' => $invoices];
    }

    public function rows(int $schoolId, array $filters): Collection
    {
        $invoices = $this->invoices($schoolId, $filters);
        $groups = $invoices->groupBy('student_id');
        $query = Student::where('school_id', $schoolId)->whereIn('id', $invoices->pluck('student_id')->unique())
            ->with('schoolClasses.academicYear');
        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('nis', 'like', $term)->orWhere('nisn', 'like', $term));
        }
        $rows = $query->get()->map(fn ($student) => $this->studentRow($student, $groups->get($student->id, collect())))
            ->filter(fn ($row) => $row['total_arrears'] > 0)
            ->when($filters['class_id'] ?? null, fn ($rows, $id) => $rows->where('class_id', $id));

        return $rows->sort(function ($a, $b) use ($filters) {
            $order = $a['total_arrears'] <=> $b['total_arrears'];

            return ($filters['sort'] ?? 'desc') === 'asc' ? ($order ?: strcmp($a['student']->name, $b['student']->name)) : (-$order ?: strcmp($a['student']->name, $b['student']->name));
        })->values();
    }
}
