<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\EventDay;
use App\Models\Ticket;
use App\Models\User;
use App\Repositories\TicketRepository;

class TicketValidationService
{
    public function __construct(private TicketRepository $tickets) {}

    /**
     * Validate a QR payload for the concert day matching today's date. A single
     * ticket stays valid for every day of the event; each day is recorded in
     * `ticket_scans` instead of consuming the ticket.
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

        $eventDay = $this->todayEventDay($ticket);

        if ($eventDay === null) {
            return $this->result('no_event_day', 'Tidak ada jadwal event untuk hari ini.', $ticket);
        }

        $gate = $this->gateResult($eventDay);

        if ($gate !== null) {
            return $this->result($gate['status'], $gate['message'], $ticket);
        }

        if ($ticket->status !== TicketStatus::Active->value) {
            return $this->rejected($ticket);
        }

        $recorded = $this->tickets->recordScanGuarded($ticket->id, $eventDay->id, $validator->id);

        if ($recorded) {
            return $this->result(
                'success',
                sprintf('Tiket Valid! Silakan masuk untuk Day %d.', $eventDay->day_number),
                $ticket->refresh(),
            );
        }

        $existing = $ticket->ticketScans()->where('event_day_id', $eventDay->id)->first();

        return $this->result(
            'already_scanned',
            sprintf(
                'Tiket untuk Day %d sudah pernah di-scan hari ini pada jam %s WIB.',
                $eventDay->day_number,
                $this->wib($existing?->scanned_at ?? now()),
            ),
            $ticket->refresh(),
        );
    }

    private function todayEventDay(Ticket $ticket): ?EventDay
    {
        return $ticket->orderItem?->ticketCategory?->event
            ?->eventDays()
            ->whereDate('date', now()->toDateString())
            ->first();
    }

    /**
     * Check whether the gate for the concert day is open yet or already closed.
     *
     * @return array{status: string, message: string}|null
     */
    private function gateResult(EventDay $eventDay): ?array
    {
        if ($eventDay->open_gate !== null && now()->lt($eventDay->open_gate)) {
            return [
                'status' => 'not_open',
                'message' => sprintf(
                    'Pintu Day %d belum dibuka. Scan dimulai jam %s WIB.',
                    $eventDay->day_number,
                    $this->wib($eventDay->open_gate),
                ),
            ];
        }

        if ($eventDay->close_gate !== null && now()->gt($eventDay->close_gate)) {
            return [
                'status' => 'closed',
                'message' => sprintf(
                    'Pintu Day %d sudah ditutup sejak jam %s WIB.',
                    $eventDay->day_number,
                    $this->wib($eventDay->close_gate),
                ),
            ];
        }

        return null;
    }

    private function wib(mixed $value): string
    {
        return $value->copy()->setTimezone('Asia/Jakarta')->format('H:i');
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
