<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceWalletMovement extends Model
{
    protected $guarded = [];
    protected $casts = ['effective_date' => 'date', 'amount' => 'decimal:2'];
}
