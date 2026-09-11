<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpenseType extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'requires_approval' => 'boolean',
    ];
}
