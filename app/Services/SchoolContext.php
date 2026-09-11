<?php

namespace App\Services;

use App\Models\School;
use App\Models\User;
use Illuminate\Support\Collection;

class SchoolContext
{
    public function activeSchool(): ?School
    {
        $schoolId = session('active_school_id');

        return $schoolId ? School::find($schoolId) : null;
    }

    public function activeSchoolId(): ?int
    {
        return session('active_school_id');
    }

    public function availableSchools(User $user): Collection
    {
        return School::query()
            ->where('is_active', true)
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->where('membership_status', 'active'))
            ->with(['roles' => fn ($query) => $query->where('user_id', $user->id)->where('is_active', true)->where('membership_status', 'active')])
            ->orderBy('name')
            ->get();
    }

    public function setActiveSchool(User $user, int $schoolId): School
    {
        $school = $this->availableSchools($user)->firstWhere('id', $schoolId);
        abort_unless($school, 403);
        session(['active_school_id' => $school->id]);

        return $school;
    }

    public function ensureDefaultSchool(User $user): ?School
    {
        if (session()->has('active_school_id')) {
            return $this->activeSchool();
        }

        $schools = $this->availableSchools($user);

        if ($schools->count() === 1) {
            session(['active_school_id' => $schools->first()->id]);

            return $schools->first();
        }

        return null;
    }
}
