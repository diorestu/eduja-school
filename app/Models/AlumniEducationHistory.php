<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlumniEducationHistory extends Model
{
    protected $guarded = [];

    protected $casts = ['entry_year' => 'integer', 'graduation_year' => 'integer'];
}
