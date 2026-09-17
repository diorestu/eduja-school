<?php

namespace App\Services;

use App\Models\User;

class AttendanceAccess
{
    public function canEdit(User $user): bool
    {
        $schoolId = app(SchoolContext::class)->activeSchoolIdFor($user);
        $roles = $user->schoolRoles()->where('school_id', $schoolId)
            ->where('is_active', true)->where('membership_status', 'active')->pluck('role');

        // Principal membership is read-only even when the account carries a legacy admin role.
        if ($roles->contains('kepsek')) {
            return false;
        }

        return $roles->intersect(['super_admin', 'pic_sekolah', 'tu', 'staf_tu'])->isNotEmpty();
    }
}
