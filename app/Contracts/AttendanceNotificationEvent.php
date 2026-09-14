<?php

namespace App\Contracts;

final readonly class AttendanceNotificationEvent
{
    public function __construct(
        public array $school,
        public array $recipient,
        public array $subject,
        public array $request,
        public array $message,
    ) {}

    public function toArray(): array
    {
        return [
            'school' => $this->school,
            'recipient' => $this->recipient,
            'subject' => $this->subject,
            'request' => $this->request,
            'message' => $this->message,
        ];
    }
}
