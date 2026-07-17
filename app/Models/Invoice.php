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
    ];

    protected $casts = [
        'due_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    /**
     * Get the student billed.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
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
        return $this->transactions()->sum('amount_paid');
    }

    /**
     * Calculate outstanding balance.
     */
    public function getRemainingAmountAttribute()
    {
        return $this->total_amount - $this->paid_amount;
    }
}
