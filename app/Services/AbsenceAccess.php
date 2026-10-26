<?php

namespace App\Services;

use App\Models\AttendanceRequest;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AbsenceAccess
{
    public function schoolReviewer(User $user): bool
    {
        return $user->hasRole(['kepsek', 'wakasek', 'tu', 'staf_tu']);
    }

    public function canReview(AttendanceRequest $absence, User $user): bool
    {
        $schoolId = app(SchoolContext::class)->activeSchoolIdFor($user);
        if ((int) $absence->school_id !== $schoolId) return false;
        if ($this->schoolReviewer($user)) return true;
        if (! $user->hasRole('wali_kelas') || $absence->subject_type !== Student::class) return false;
        return app(ActiveEnrollment::class)->assignedClasses($schoolId, $user)
            ->whereHas('students', fn ($q) => $q->where('students.id', $absence->subject_id)->where('students.school_id', $schoolId))->exists();
    }

    public function canView(AttendanceRequest $absence, User $user): bool
    {
        return (int) $absence->school_id === app(SchoolContext::class)->activeSchoolIdFor($user)
            && (in_array($user->id, [$absence->requester_id, $absence->submitted_by], true) || $this->ownsSubject($absence, $user) || $this->canReview($absence, $user));
    }

    private function ownsSubject(AttendanceRequest $absence, User $user): bool
    {
        return $absence->subject_type === Student::class && Student::where('school_id',$absence->school_id)->whereKey($absence->subject_id)
            ->where(fn ($q)=>$q->where('user_id',$user->id)->orWhere('guardian_user_id',$user->id))->exists();
    }

    public function visible(Builder $query, User $user, int $schoolId): Builder
    {
        $query->where('school_id', $schoolId);
        if ($this->schoolReviewer($user)) return $query;
        return $query->where(function ($q) use ($user, $schoolId) {
            $q->where('requester_id', $user->id)->orWhere('submitted_by', $user->id);
            $q->orWhere(fn ($own)=>$own->where('subject_type',Student::class)->whereIn('subject_id',Student::where('school_id',$schoolId)->where(fn ($child)=>$child->where('user_id',$user->id)->orWhere('guardian_user_id',$user->id))->select('students.id')));
            if ($user->hasRole('wali_kelas')) {
                $classes = app(ActiveEnrollment::class)->assignedClasses($schoolId, $user)->pluck('id');
                $q->orWhere(fn ($students) => $students->where('subject_type', Student::class)->whereIn('subject_id',
                    Student::where('school_id', $schoolId)->whereHas('schoolClasses', fn ($c) => $c->whereIn('school_classes.id', $classes))->select('students.id')));
            }
        });
    }
}
