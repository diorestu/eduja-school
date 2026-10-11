<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingPeriod extends Model
{
    protected $guarded = [];

    protected $casts = ['start_date' => 'date', 'due_date' => 'date'];

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
