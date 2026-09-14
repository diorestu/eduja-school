<?php

namespace App\Services;

use App\Contracts\AttendanceNotificationEvent;
use App\Contracts\AttendanceNotifier;
use Illuminate\Support\Facades\Log;

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
}
