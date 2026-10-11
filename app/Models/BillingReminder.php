<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingReminder extends Model
{
    protected $guarded = [];

    protected $casts = ['invoice_ids' => 'array', 'scheduled_date' => 'date', 'sent_at' => 'datetime'];
}
