<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Order;

class RundownPayload
{
    /**
     * Build a signed payload string for a paid order and event.
     *
     * Payload format: rundown.{order_id}.{event_id}.{hmac_sha256}
     */
    public function payload(Order $order, Event $event): string
    {
        $data = "rundown.{$order->id}.{$event->id}";

        return $data.'.'.hash_hmac('sha256', $data, (string) config('app.key'));
    }

    /**
     * Verify the signature and return the referenced order and event ids.
     *
     * @return array{order_id: int, event_id: int}|null
     */
    public function parse(string $payload): ?array
    {
        $parts = explode('.', $payload);

        if (count($parts) !== 4 || $parts[0] !== 'rundown') {
            return null;
        }

        $hmac = array_pop($parts);
        $data = implode('.', $parts);

        if (! hash_equals(hash_hmac('sha256', $data, (string) config('app.key')), $hmac)) {
            return null;
        }

        return ['order_id' => (int) $parts[1], 'event_id' => (int) $parts[2]];
    }
}
