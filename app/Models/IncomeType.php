<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IncomeType extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'uses_allocation' => 'boolean',
        'requires_approval' => 'boolean',
    ];
}
