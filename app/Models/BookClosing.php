<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookClosing extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'snapshot' => 'array',
        'validation_results' => 'array',
        'audit_metadata' => 'array',
        'closed_at' => 'datetime',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
