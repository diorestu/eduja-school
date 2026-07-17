<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'school_id',
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

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}
