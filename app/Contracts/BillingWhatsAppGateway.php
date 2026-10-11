<?php

namespace App\Contracts;

interface BillingWhatsAppGateway
{
    public function configured(): bool;

    /** Return the provider's accepted-dispatch reference, not a delivery receipt. */
    public function send(string $recipient, string $message, string $idempotencyKey): string;
}
