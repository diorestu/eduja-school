<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BudgetYear extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function budgetPlans(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BudgetPlan::class);
    }
}
