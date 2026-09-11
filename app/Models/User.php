<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'phone_normalized',
        'password',
        'role',
        'registration_type',
        'onboarding_status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Check if user is Super Admin (Kepala Sekolah)
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /**
     * Check if user is Bendahara Sekolah
     */
    public function isBendahara(): bool
    {
        return $this->role === 'bendahara';
    }

    /**
     * Check if user is Staf Tata Usaha
     */
    public function isStafTu(): bool
    {
        return $this->role === 'staf_tu';
    }

    /**
     * Check if user has one of the specified roles
     */
    public function hasRole(string|array $roles): bool
    {
        $roles = is_array($roles) ? $roles : [$roles];
        $schoolId = session('active_school_id');

        if ($schoolId && $this->schoolRoles()->where('school_id', $schoolId)->where('is_active', true)->exists()) {
            return app(\App\Services\PermissionService::class)->hasAnyRole($this, $roles);
        }

        $legacyRoleMap = [
            'super_admin' => 'kepsek',
            'bendahara' => 'bendahara',
            'staf_tu' => 'tu',
        ];

        $currentRoles = array_filter([$this->role, $legacyRoleMap[$this->role] ?? null]);

        return count(array_intersect($currentRoles, $roles)) > 0;
    }

    public function schoolRoles(): HasMany
    {
        return $this->hasMany(SchoolUserRole::class);
    }

    public function schools(): BelongsToMany
    {
        return $this->belongsToMany(School::class, 'school_user_roles')
            ->withPivot(['role', 'is_active'])
            ->withTimestamps();
    }

    public function guardianStudents(): HasMany
    {
        return $this->hasMany(Student::class, 'guardian_user_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function teachers(): HasMany
    {
        return $this->hasMany(Teacher::class);
    }

    public function legacyHasRole(string|array $roles): bool
    {
        if (is_array($roles)) {
            return in_array($this->role, $roles);
        }

        return $this->role === $roles;
    }
}
