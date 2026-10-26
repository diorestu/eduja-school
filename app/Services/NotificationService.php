<?php

namespace App\Services;

use App\Contracts\AttendanceNotificationEvent;
use App\Contracts\AttendanceNotifier;
use Illuminate\Support\Facades\Log;
use App\Models\SchoolNotification;
use App\Models\User;
use Illuminate\Support\Str;

class NotificationService
{
    public function __construct(private readonly ?AttendanceNotifier $attendanceNotifier = null) {}

    public function send(string $channel, string $recipient, string $message): void
    {
        Log::info('EDUJA notification queued', compact('channel', 'recipient', 'message'));
    }

    public function sendAttendance(AttendanceNotificationEvent $event): void
    {
        ($this->attendanceNotifier ?? new LogAttendanceNotifier)->notify($event);
    }

    public function notifyUsers(int $schoolId, iterable $userIds, string $type, string $title, string $url, array $data = [], ?string $eventKey = null): void
    {
        $eventKey ??= (string) Str::uuid();
        $recipients = User::whereIn('id', collect($userIds)->unique()->values())
            ->whereHas('schoolRoles', fn ($query) => $query->where('school_id', $schoolId)
                ->where('is_active', true)->where('membership_status', 'active'))->pluck('id');
        foreach ($recipients as $userId) {
            SchoolNotification::firstOrCreate(['school_id' => $schoolId, 'user_id' => $userId, 'event_key' => $eventKey],
                ['type' => $type, 'title' => $title, 'url' => $url, 'data' => $data]);
        }
    }
}
