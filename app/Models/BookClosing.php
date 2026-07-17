<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookClosing extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'snapshot' => 'array',
        'closed_at' => 'datetime',
    ];
}
