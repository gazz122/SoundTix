<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\Order;
use App\Repositories\TicketRepository;
use Illuminate\Support\Str;

class TicketGenerator
{
    public function __construct(private TicketRepository $tickets) {}

    /**
     * Issue one e-ticket per unit in every order item of a paid order.
     */
    public function issueForPaidOrder(Order $order): void
    {
        foreach ($order->orderItems as $item) {
            for ($i = 0; $i < $item->quantity; $i++) {
                $ticketCode = $this->ticketCode();

                $this->tickets->create([
                    'order_item_id' => $item->id,
                    'ticket_code' => $ticketCode,
                    'qr_payload' => $this->signPayload($ticketCode, $order->id, $item->id),
                    'status' => TicketStatus::Active->value,
                ]);
            }
        }
    }

    private function ticketCode(): string
    {
        do {
            $code = strtoupper(Str::random(12));
        } while ($this->tickets->ticketCodeExists($code));

        return $code;
    }

    private function signPayload(string $ticketCode, int $orderId, int $orderItemId): string
    {
        $payload = "{$ticketCode}.{$orderId}.{$orderItemId}";

        return $payload.'.'.hash_hmac('sha256', $payload, (string) config('app.key'));
    }
}
