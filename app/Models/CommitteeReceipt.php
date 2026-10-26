<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommitteeReceipt extends Model
{
    protected $guarded = [];

    protected $casts = ['amount_paid' => 'decimal:2', 'payment_date' => 'date'];

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
