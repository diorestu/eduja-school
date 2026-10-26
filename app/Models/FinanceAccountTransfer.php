<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceAccountTransfer extends Model
{
    protected $guarded = [];
    protected $casts = ['transfer_date' => 'date', 'amount' => 'decimal:2'];
}
