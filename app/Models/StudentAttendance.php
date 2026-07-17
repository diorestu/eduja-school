<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'school_id',
        'school_class_id',
        'attendance_date',
        'clock_in_at',
        'clock_out_at',
        'source',
        'rfid_uid',
        'latitude',
        'longitude',
        'photo_path',
        'sync_status',
        'status',
        'note',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }
}
