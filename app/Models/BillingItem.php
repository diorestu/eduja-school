<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'due_date' => 'date',
        'start_date' => 'date',
        'allow_installment' => 'boolean',
        'has_late_fee' => 'boolean',
        'amount' => 'decimal:2',
        'minimum_installment' => 'decimal:2',
        'late_fee_per_day' => 'decimal:2',
        'late_fee_maximum' => 'decimal:2',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function incomeType(): BelongsTo
    {
        return $this->belongsTo(IncomeType::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'target_department_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'target_class_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'target_student_id');
    }
}
