<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'budget_category_id',
        'expense_type_id',
        'virtual_wallet_id',
        'school_id',
        'account_id',
        'academic_year_id',
        'expense_name',
        'amount',
        'transaction_date',
        'source_funding',
        'payment_method',
        'reference_invoice',
        'proof_path',
        'document_checklist',
        'recipient_name',
        'tax_type',
        'tax_amount',
        'is_tax_paid',
        'status',
        'reviewed_by',
        'reviewed_at',
        'posted_at',
        'posted_by',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'is_tax_paid' => 'boolean',
        'amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'reviewed_at' => 'datetime',
        'posted_at' => 'datetime',
        'document_checklist' => 'array',
    ];

    public function budgetCategory(): BelongsTo
    {
        return $this->belongsTo(BudgetCategory::class);
    }

    public function expenseType(): BelongsTo
    {
        return $this->belongsTo(ExpenseType::class);
    }

    public function virtualWallet(): BelongsTo
    {
        return $this->belongsTo(VirtualWallet::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SchoolAccount::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
