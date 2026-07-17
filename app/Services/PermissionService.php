<?php

namespace App\Services;

use App\Models\User;

class PermissionService
{
    private const LEGACY_ROLE_MAP = [
        'super_admin' => 'kepsek',
        'bendahara' => 'bendahara',
        'staf_tu' => 'tu',
    ];

    public function rolesForActiveSchool(User $user): array
    {
        $schoolId = session('active_school_id');

        if ($schoolId) {
            $roles = $user->schoolRoles()
                ->where('school_id', $schoolId)
                ->where('is_active', true)
                ->pluck('role')
                ->all();

            if ($roles !== []) {
                return $roles;
            }
        }

        return array_values(array_unique(array_filter([
            $user->role,
            self::LEGACY_ROLE_MAP[$user->role] ?? null,
        ])));
    }

    public function hasAnyRole(User $user, array $roles): bool
    {
        return count(array_intersect($this->rolesForActiveSchool($user), $roles)) > 0;
    }
}
