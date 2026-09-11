<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'nis',
        'school_id',
        'guardian_user_id',
        'user_id',
        'nisn',
        'name',
        'gender',
        'phone',
        'parent_name',
        'parent_phone',
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
     * Get the student's class mappings.
     */
    public function classStudents(): HasMany
    {
        return $this->hasMany(ClassStudent::class, 'student_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The classes that the student is enrolled in.
     */
    public function schoolClasses(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'class_students', 'student_id', 'school_class_id')
            ->withTimestamps();
    }
}
