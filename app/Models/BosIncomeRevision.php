<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BosIncomeRevision extends Model
{
    protected $guarded = [];

    protected $casts = ['old_data' => 'array', 'new_data' => 'array', 'reviewed_at' => 'datetime', 'applied_at' => 'datetime'];

    public function income()
    {
        return $this->belongsTo(FinanceIncome::class, 'finance_income_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
