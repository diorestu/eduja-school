<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VirtualWallet extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'nominal' => 'decimal:2',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function fundAllocations(): HasMany
    {
        return $this->hasMany(FundAllocation::class, 'virtual_wallet_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return in_array(strtolower($this->status), ['active', 'aktif']) ? 'Aktif' : 'Nonaktif';
    }
}
