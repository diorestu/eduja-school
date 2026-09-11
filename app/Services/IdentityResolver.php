<?php

namespace App\Services;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;

class IdentityResolver
{
    public function studentFor(User $user, ?int $schoolId = null): ?Student
    {
        return Student::query()->where('user_id', $user->id)->when($schoolId, fn ($query) => $query->where('school_id', $schoolId))->first();
    }

    public function teacherFor(User $user, ?int $schoolId = null): ?Teacher
    {
        return Teacher::query()->where('user_id', $user->id)->when($schoolId, fn ($query) => $query->where('school_id', $schoolId))->first();
    }
}
