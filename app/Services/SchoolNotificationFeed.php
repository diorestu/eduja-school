<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Support\Collection;

class SchoolNotificationFeed
{
    public function forUser(User $user): Collection
    {
        $context = app(SchoolContext::class);
        $schoolId = $context->activeSchoolId();
        if (! $schoolId || ! $context->availableSchools($user)->contains('id', $schoolId)
            || $user->hasRole(['dinas', 'yayasan'])) {
            return collect();
        }

        $items = collect();
        foreach (ApprovalRequest::where('school_id', $schoolId)->where('type', 'attendance')->where('status', 'pending')->latest()->get() as $approval) {
            if (app(AttendanceApprovalService::class)->canReview($approval, $user)) {
                $items->push(['title' => 'Permohonan izin menunggu keputusan', 'url' => route('attendance.requests', ['status' => 'pending']), 'date' => $approval->created_at]);
            }
        }
        foreach (Announcement::where('school_id', $schoolId)->whereNotNull('published_at')->where('published_at', '<=', now())
            ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user->id)->whereNotNull('read_at'))->latest()->get() as $announcement) {
            if (app(AnnouncementAudience::class)->allows($announcement, $user)) {
                $items->push(['title' => $announcement->title, 'url' => route('announcements.show', $announcement), 'date' => $announcement->published_at]);
            }
        }
        return $items->sortByDesc('date')->take(10)->values();
    }
}
