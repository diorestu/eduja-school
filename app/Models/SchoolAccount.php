<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolAccount extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_active' => 'boolean',
        'last_reconciled_at' => 'datetime',
    ];
    public function isBosBankAccount(): bool
    {
        return strtolower($this->type) === 'bos'
            || (strtolower($this->type) === 'bank' && strtolower($this->source_funding ?? '') === 'bos');
    }

    public function isCommitteePaymentAccount(): bool
    {
        return in_array(strtolower($this->type), ['cash', 'tunai', 'bank'], true)
            && in_array(strtolower($this->source_funding ?? ''), ['', 'komite', 'lainnya'], true);
    }
}
