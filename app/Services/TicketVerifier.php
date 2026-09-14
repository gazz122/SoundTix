<?php

namespace App\Services;

class TicketVerifier
{
    /**
     * Validate the HMAC signature of a QR payload.
     *
     * Payload format: {ticket_code}.{order_id}.{order_item_id}.{hmac_sha256}
     */
    public function isSignatureValid(string $payload): bool
    {
        $parts = explode('.', $payload);

        if ($parts === [] || count($parts) !== 4) {
            return false;
        }

        $hmac = array_pop($parts);
        $data = implode('.', $parts);

        return hash_equals($hmac, hash_hmac('sha256', $data, (string) config('app.key')));
    }
}
