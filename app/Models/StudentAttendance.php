<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAttendance extends Model
{
    use HasFactory;

    public const STATUSES = ['H', 'S', 'I', 'D', 'A'];

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
        'request_id',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'reviewed_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(AttendanceRequest::class, 'request_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
