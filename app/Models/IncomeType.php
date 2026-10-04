<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IncomeType extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'uses_allocation' => 'boolean',
        'requires_approval' => 'boolean',
    ];

    public function fundAllocations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FundAllocation::class);
    }

    public function billingItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BillingItem::class);
    }
}
