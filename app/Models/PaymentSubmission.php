<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentSubmission extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'payment_date' => 'date',
    ];
}
