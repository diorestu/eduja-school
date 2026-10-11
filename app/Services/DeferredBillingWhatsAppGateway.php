<?php

namespace App\Services;

use App\Contracts\BillingWhatsAppGateway;

class DeferredBillingWhatsAppGateway implements BillingWhatsAppGateway
{
    public function configured(): bool
    {
        return false;
    }

    public function send(string $recipient, string $message, string $idempotencyKey): string
    {
        throw new \LogicException('Integrasi provider WhatsApp ditunda.');
    }
}
