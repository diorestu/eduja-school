<?php

namespace App\Services;

use App\Contracts\AttendanceNotificationEvent;
use App\Contracts\AttendanceNotifier;
use Illuminate\Support\Facades\Log;

class LogAttendanceNotifier implements AttendanceNotifier
{
    public function notify(AttendanceNotificationEvent $event): void
    {
        Log::info('EDUJA attendance notification event recorded', ['event' => $event->toArray()]);
    }
}
