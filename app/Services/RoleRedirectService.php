<?php

namespace App\Services;

use App\Models\User;

class RoleRedirectService
{
    public function afterLogin(User $user): string
    {
        if ($user->role === 'dinas') return 'dinas.dashboard';
        if ($user->role === 'yayasan') return 'yayasan.dashboard';

        return $this->schoolCount($user) === 1 ? 'dashboard' : 'school.select';
    }

    private function schoolCount(User $user): int
    {
        return app(SchoolContext::class)->availableSchools($user)->count();
    }
}
