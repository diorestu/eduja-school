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
        $rawRoles = [];

        if ($schoolId) {
            $rawRoles = $user->schoolRoles()
                ->where('school_id', $schoolId)
                ->where('is_active', true)
                ->pluck('role')
                ->all();
        }

        if (empty($rawRoles)) {
            $rawRoles = [$user->role];
        }

        $allRoles = [];
        foreach ($rawRoles as $role) {
            $allRoles[] = $role;
            if ($role === 'tu') {
                $allRoles[] = 'staf_tu';
            }
            if ($role === 'staf_tu') {
                $allRoles[] = 'tu';
            }
            if ($role === 'kepsek') {
                $allRoles[] = 'super_admin';
            }
            if ($role === 'super_admin') {
                $allRoles[] = 'kepsek';
            }
        }

        return array_values(array_unique(array_filter($allRoles)));
    }

    public function hasAnyRole(User $user, array $roles): bool
    {
        return count(array_intersect($this->rolesForActiveSchool($user), $roles)) > 0;
    }
}
