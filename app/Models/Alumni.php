<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Alumni extends Model
{
    use HasFactory;

    protected $table = 'alumni';

    protected $guarded = [];

    public const CURRENT_STATUSES = ['Melanjutkan Pendidikan', 'Kuliah', 'Bekerja', 'Kuliah sambil bekerja', 'Wirausaha', 'Gap year'];

    public function educationEntries(): HasMany
    {
        return $this->hasMany(AlumniEducationHistory::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
