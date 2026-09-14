<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Repositories\TicketRepository;

class TicketValidationService
{
    public function __construct(private TicketRepository $tickets) {}

    /**
     * Validate a QR payload and consume the ticket if it is still active.
     *
     * @return array{status: string, message: string, ticket: array<string, mixed>|null}
     */
    public function validate(string $payload, User $validator): array
    {
        if (count(explode('.', $payload)) !== 4 || ! (new TicketVerifier)->isSignatureValid($payload)) {
            return $this->result('invalid', 'QR code tidak dikenali atau telah diubah.');
        }

        [$ticketCode, $orderId, $orderItemId] = explode('.', $payload);

        $ticket = $this->tickets->findForValidation($ticketCode, (int) $orderItemId, (int) $orderId);

        if (! $ticket) {
            return $this->result('invalid', 'Tiket tidak ditemukan di sistem SoundTix.');
        }

        if ($ticket->status !== TicketStatus::Active->value) {
            return $this->rejected($ticket->refresh());
        }

        $consumed = $this->tickets->markUsedGuarded($ticket->id, $validator->id);

        if ($consumed !== 1) {
            return $this->rejected($ticket->refresh());
        }

        return $this->result('success', 'Tiket valid. Silakan dipersilakan masuk.', $ticket->refresh());
    }

    /**
     * @return array{status: string, message: string, ticket: array<string, mixed>|null}
     */
    private function rejected(Ticket $ticket): array
    {
        return $this->result(
            $ticket->status,
            'Tiket berstatus '.TicketStatus::from($ticket->status)->label().' dan tidak dapat digunakan.',
            $ticket,
        );
    }

    /**
     * @return array{status: string, message: string, ticket: array<string, mixed>|null}
     */
    private function result(string $status, string $message, ?Ticket $ticket = null): array
    {
        return [
            'status' => $status,
            'message' => $message,
            'ticket' => $ticket ? [
                'ticket_code' => $ticket->ticket_code,
                'status_label' => TicketStatus::from($ticket->status)->label(),
                'category' => $ticket->orderItem?->ticketCategory?->name,
                'event_title' => $ticket->orderItem?->ticketCategory?->event?->title,
                'event_date' => $ticket->orderItem?->ticketCategory?->event?->event_date,
                'order_code' => $ticket->orderItem?->order?->order_code,
                'used_at' => $ticket->used_at?->toIso8601String(),
            ] : null,
        ];
    }
}
