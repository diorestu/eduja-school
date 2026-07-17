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
            ->whereHas('roles', fn ($query) => $query
                ->where('user_id', $user->id)
                ->where('is_active', true))
            ->orderBy('name')
            ->get();
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
