<?php

namespace App\Contracts;

interface AttendanceNotifier
{
    public function notify(AttendanceNotificationEvent $event): void;
}
