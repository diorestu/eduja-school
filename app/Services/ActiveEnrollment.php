<?php

namespace App\Services;

use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ActiveEnrollment
{
    public function classes(int $schoolId): Builder
    {
        return SchoolClass::query()->where('school_id', $schoolId)
            ->whereHas('academicYear', fn ($q) => $q->where('school_id', $schoolId)->where('is_active', true));
    }

    public function forStudent(int $schoolId, int $studentId): ?SchoolClass
    {
        return $this->classes($schoolId)->whereHas('students', fn ($q) => $q->where('students.id', $studentId))
            ->with('teacher')->orderByDesc('id')->first();
    }

    public function assignedClasses(int $schoolId, User $user): Builder
    {
        return $this->classes($schoolId)->whereHas('teacher', fn ($q) => $q->active()->where('school_id', $schoolId)->where('user_id', $user->id));
    }
}
