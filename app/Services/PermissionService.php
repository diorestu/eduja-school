<?php

namespace App\Services;

use App\Models\SchoolRolePermission;
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

    public function can(User $user, string $permission, array $defaultRoles = []): bool
    {
        $roles = $this->rolesForActiveSchool($user);

        if (count(array_intersect($roles, ['super_admin', 'kepsek'])) > 0) {
            return true;
        }

        if (count(array_intersect($roles, $defaultRoles)) > 0) {
            return true;
        }

        $schoolId = session('active_school_id');

        if (! $schoolId || empty($roles)) {
            return false;
        }

        return SchoolRolePermission::query()
            ->where('school_id', $schoolId)
            ->whereIn('role', $roles)
            ->where('permission', $permission)
            ->where('is_allowed', true)
            ->exists();
    }

    public function grantsForRole(int $schoolId, string $role): array
    {
        return SchoolRolePermission::query()
            ->where('school_id', $schoolId)
            ->where('role', $role)
            ->where('is_allowed', true)
            ->orderBy('permission')
            ->pluck('permission')
            ->all();
    }
}
