<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'school_id',
        'academic_year_id',
        'invoice_number',
        'due_date',
        'total_amount',
        'status',
        'billing_item_id',
        'billing_period_id',
        'principal_amount',
        'late_fee_amount',
        'allow_installment',
        'minimum_installment',
        'late_fee_per_day',
        'late_fee_maximum',
    ];

    protected $casts = [
        'due_date' => 'date',
        'total_amount' => 'decimal:2',
        'principal_amount' => 'decimal:2',
        'late_fee_amount' => 'decimal:2',
        'allow_installment' => 'boolean',
        'minimum_installment' => 'decimal:2',
        'late_fee_per_day' => 'decimal:2',
        'late_fee_maximum' => 'decimal:2',
    ];

    /**
     * Get the student billed.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function billingItem(): BelongsTo
    {
        return $this->belongsTo(BillingItem::class);
    }

    public function scopeOwnedBySchool($query, int $schoolId)
    {
        return $query->where(fn ($q) => $q->where('school_id', $schoolId)
            ->orWhere(fn ($legacy) => $legacy->whereNull('school_id')->whereHas('student', fn ($student) => $student->where('school_id', $schoolId))
                ->whereHas('academicYear', fn ($year) => $year->where('school_id', $schoolId)->orWhereNull('school_id'))));
    }

    public function billingPeriod(): BelongsTo
    {
        return $this->belongsTo(BillingPeriod::class);
    }

    /**
     * Get the academic year.
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    /**
     * Get the line items of the invoice.
     */
    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id');
    }

    /**
     * Get the payment transactions made.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'invoice_id');
    }

    /**
     * Calculate total paid amount from transactions.
     */
    public function getPaidAmountAttribute()
    {
        return $this->transactions()->whereIn('status', ['approved', 'confirmed'])->sum('amount_paid');
    }

    /**
     * Calculate outstanding balance.
     */
    public function getRemainingAmountAttribute()
    {
        return max(0, $this->total_amount - $this->paid_amount);
    }
}
