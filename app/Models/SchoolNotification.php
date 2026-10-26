<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolNotification extends Model
{
    protected $guarded = [];

    protected $casts = ['data' => 'array', 'read_at' => 'datetime'];
}
