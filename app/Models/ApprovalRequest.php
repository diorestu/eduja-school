<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalRequest extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];
}
