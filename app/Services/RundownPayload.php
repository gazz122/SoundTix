<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Order;

class RundownPayload
{
    public function token(Order $order, Event $event): string
    {
        return hash_hmac('sha256', "rundown:{$order->id}:{$event->id}", (string) config('app.key'));
    }

    public function url(Order $order, Event $event): string
    {
        return url("/rundown/{$order->order_code}/{$event->slug}/{$this->token($order, $event)}");
    }

    public function matches(Order $order, Event $event, string $token): bool
    {
        return hash_equals($this->token($order, $event), $token);
    }
}
