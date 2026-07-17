<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Teacher extends Model
{
    use HasFactory;

    protected $fillable = [
        'nuptk',
        'school_id',
        'nip',
        'name',
        'role_type',
        'staff_type',
        'phone',
        'is_active',
        'status',
        'status_date',
        'status_note',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'status_date' => 'date',
    ];

    /**
     * Get the classes where this teacher is a homeroom teacher (Wali Kelas).
     */
    public function schoolClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class, 'teacher_id');
    }
}
