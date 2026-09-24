<?php

namespace App\Services;

use App\Mail\ETicketEmail;
use App\Models\Order;
use Illuminate\Support\Facades\Mail;

class ETicketMailer
{
    public function __construct(private QrCodeRenderer $qr) {}

    /**
     * Send one e-ticket email (with embedded QR image) per ticket of a paid order.
     */
    public function sendForOrder(Order $order): void
    {
        $order->load('orderItems.ticketCategory.event', 'orderItems.tickets', 'customer');

        foreach ($order->orderItems as $item) {
            $event = $item->ticketCategory?->event;

            foreach ($item->tickets as $ticket) {
                Mail::to($order->customer)->send(new ETicketEmail(
                    ticket: [
                        'ticket_code' => $ticket->ticket_code,
                        'qr_payload' => $ticket->qr_payload,
                        'category' => $item->ticketCategory?->name,
                        'event_title' => $event?->title,
                        'event_date' => $event?->event_date?->format('d M Y'),
                        'start_time' => $event?->start_time !== null ? substr((string) $event->start_time, 0, 5) : null,
                        'venue' => $event !== null ? sprintf('%s, %s', $event->venue_name, $event->city) : null,
                    ],
                    order: [
                        'order_code' => $order->order_code,
                        'customer_name' => $order->customer?->name,
                    ],
                    qrPng: $this->qr->png($ticket->qr_payload),
                ));
            }
        }
    }
}
