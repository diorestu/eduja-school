<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Student;
use App\Models\User;

class AnnouncementAudience
{
    public function allows(Announcement $announcement, User $user): bool
    {
        $schoolId = app(SchoolContext::class)->activeSchoolId();
        if ((int) $announcement->school_id !== (int) $schoolId || ! $announcement->published_at || $announcement->published_at->isFuture()) {
            return false;
        }
        if ((int) $announcement->created_by === $user->id || $user->isSuperAdmin()) {
            return true;
        }
        if ($announcement->target_type === 'school') {
            return true;
        }
        if ($announcement->target_type === 'person') {
            return (int) $announcement->target_id === $user->id;
        }
        if (in_array($announcement->target_type, ['class', 'department', 'grade'], true)) {
            return Student::where('school_id', $schoolId)->where('status', 'active')
                ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('guardian_user_id', $user->id))
                ->whereHas('schoolClasses', function ($q) use ($announcement, $schoolId) {
                    $q->where('school_classes.school_id', $schoolId);
                    $column = match ($announcement->target_type) {
                        'class' => 'school_classes.id',
                        'department' => 'school_classes.department_id',
                        'grade' => 'school_classes.grade',
                    };
                    $q->where($column, $announcement->target_id);
                })->exists();
        }
        return $user->hasRole(match ($announcement->target_type) {
            'teacher' => ['guru', 'wali_kelas'],
            'staff' => ['tendik', 'tu', 'staf_tu', 'bendahara'],
            'student' => ['siswa'],
            'parent' => ['orang_tua', 'wali_murid'],
            default => [],
        });
    }
}
