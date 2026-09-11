<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LifecycleHistory extends Model
{
    protected $guarded = [];

    protected $casts = [
        'effective_date' => 'date',
        'metadata' => 'array',
    ];
}
