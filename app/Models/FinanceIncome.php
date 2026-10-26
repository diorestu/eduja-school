<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceIncome extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'received_date' => 'date',
        'posted_at' => 'datetime',
        'revised_at' => 'datetime',
        'grant_allocations' => 'array',
        'other_allocations' => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(SchoolAccount::class, 'account_id');
    }

    public function incomeType(): BelongsTo
    {
        return $this->belongsTo(IncomeType::class);
    }

    public function revisions()
    {
        return $this->hasMany(BosIncomeRevision::class);
    }

    public function scopeBos($query)
    {
        return $query->where(fn ($q) => $q->whereNotNull('bos_year')
            ->orWhereHas('incomeType', fn ($type) => $type->whereRaw('LOWER(category) = ?', ['bos']))
            ->orWhereRaw('LOWER(source_funding) LIKE ?', ['bos%'])->orWhereRaw('LOWER(source_funding) LIKE ?', ['bop%'])->orWhereRaw('LOWER(source_funding) LIKE ?', ['dak%']));
    }

    public function grantRevisions()
    {
        return $this->hasMany(GrantIncomeRevision::class);
    }

    public function scopeGrant($query)
    {
        return $query->whereRaw('LOWER(source_funding) = ?', ['hibah']);
    }

    public function otherRevisions()
    {
        return $this->hasMany(OtherIncomeRevision::class);
    }

    public function scopeOther($query)
    {
        return $query->where(function ($query) {
            $query->where('income_category', 'lainnya')->orWhere(function ($legacy) {
                $legacy->whereNull('income_category')->whereNull('bos_year')->whereNull('donor_name')
                    ->whereNotNull('source_funding')->whereRaw('LOWER(TRIM(source_funding)) NOT IN (?, ?)', ['komite', 'hibah'])
                    ->whereRaw('LOWER(TRIM(source_funding)) NOT LIKE ?', ['bos%'])->whereRaw('LOWER(TRIM(source_funding)) NOT LIKE ?', ['bop%'])->whereRaw('LOWER(TRIM(source_funding)) NOT LIKE ?', ['dak%'])
                    ->whereDoesntHave('incomeType', fn ($type) => $type->whereRaw('LOWER(category) IN (?, ?, ?)', ['bos', 'komite', 'hibah']));
            });
        });
    }
}
