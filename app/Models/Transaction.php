<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'amount_paid',
        'payment_date',
        'payment_method',
        'receipt_number',
        'recipient_name',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount_paid' => 'decimal:2',
    ];

    /**
     * Get the associated invoice.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }
}
