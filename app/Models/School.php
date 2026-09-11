<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'npsn',
        'level',
        'ownership',
        'foundation_name',
        'city',
        'province',
        'is_active',
        'status',
        'registration_code',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'school_user_roles')
            ->withPivot(['role', 'is_active'])
            ->withTimestamps();
    }

    public function roles(): HasMany
    {
        return $this->hasMany(SchoolUserRole::class);
    }

    public function rolePermissions(): HasMany
    {
        return $this->hasMany(SchoolRolePermission::class);
    }
}
