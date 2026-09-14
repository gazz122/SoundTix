<?php

namespace App\Repositories;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Support\Carbon;

class TicketRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Ticket
    {
        return Ticket::create($attributes);
    }

    public function ticketCodeExists(string $code): bool
    {
        return Ticket::where('ticket_code', $code)->exists();
    }

    public function findForValidation(string $ticketCode, int $orderItemId, int $orderId): ?Ticket
    {
        return Ticket::with(['orderItem.ticketCategory.event', 'orderItem.order'])
            ->where('ticket_code', $ticketCode)
            ->whereHas('orderItem', fn ($query) => $query->whereKey($orderItemId)->where('order_id', $orderId))
            ->first();
    }

    public function markUsedGuarded(int $ticketId, int $validatorId): int
    {
        return Ticket::whereKey($ticketId)
            ->where('status', TicketStatus::Active->value)
            ->update([
                'status' => TicketStatus::Used->value,
                'used_at' => now(),
                'validated_by' => $validatorId,
            ]);
    }

    public function countValidatedBySince(int $validatorId, Carbon $date): int
    {
        return Ticket::where('validated_by', $validatorId)
            ->whereDate('used_at', $date)
            ->count();
    }

    public function countValidatedBy(int $validatorId): int
    {
        return Ticket::where('validated_by', $validatorId)->count();
    }

    public function countActive(): int
    {
        return Ticket::where('status', TicketStatus::Active->value)->count();
    }

    public function countActiveByCustomer(int $customerId): int
    {
        return Ticket::whereHas('orderItem.order', fn ($query) => $query->where('customer_id', $customerId))
            ->where('status', TicketStatus::Active->value)
            ->count();
    }
}
