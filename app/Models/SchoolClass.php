<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolClass extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'school_id',
        'grade',
        'department_id',
        'academic_year_id',
        'teacher_id',
    ];

    /**
     * Get the academic year this class belongs to.
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    /**
     * Get the homeroom teacher (Wali Kelas) for this class.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * Get the class student mappings.
     */
    public function classStudents(): HasMany
    {
        return $this->hasMany(ClassStudent::class, 'school_class_id');
    }

    /**
     * Get the students enrolled in this class.
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'class_students', 'school_class_id', 'student_id')
            ->withTimestamps();
    }
}
