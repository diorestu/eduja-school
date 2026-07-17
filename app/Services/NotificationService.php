<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function send(string $channel, string $recipient, string $message): void
    {
        Log::info('EDUJA notification queued', compact('channel', 'recipient', 'message'));
    }
}
