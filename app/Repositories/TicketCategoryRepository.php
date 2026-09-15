<?php

namespace App\Repositories;

use App\Models\Event;
use App\Models\TicketCategory;
use Illuminate\Support\Collection;

class TicketCategoryRepository
{
    public function forEventOrderedByPrice(Event $event): Collection
    {
        return $event->ticketCategories()
            ->orderBy('price')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createForEvent(Event $event, array $attributes): TicketCategory
    {
        return $event->ticketCategories()->create($attributes);
    }

    public function optionalInEvent(Event $event, int $id): ?TicketCategory
    {
        return $event->ticketCategories()->find($id);
    }

    public function findInEvent(Event $event, int $id): TicketCategory
    {
        return $event->ticketCategories()->findOrFail($id);
    }

    /**
     * @param  array<int, int>  $ids
     */
    public function lockForUpdateByIds(Event $event, array $ids): Collection
    {
        return $event->ticketCategories()
            ->whereKey($ids)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    public function incrementAvailable(int $categoryId, int $quantity): void
    {
        TicketCategory::query()
            ->whereKey($categoryId)
            ->increment('available_quantity', $quantity);
    }

    /**
     * Quota fill percentage per category for an organizer's events.
     *
     * @return Collection<int, array{event_title: string, category_name: string, price: float, total: int, sold: int, percentage: float}>
     */
    public function quotaFillForOrganizer(int $organizerId): Collection
    {
        return TicketCategory::query()
            ->selectRaw('events.title as event_title, ticket_categories.name as category_name, ticket_categories.price, ticket_categories.total_quantity as total, (ticket_categories.total_quantity - ticket_categories.available_quantity) as sold, ROUND((ticket_categories.total_quantity - ticket_categories.available_quantity) / ticket_categories.total_quantity * 100, 1) as percentage')
            ->join('events', 'ticket_categories.event_id', '=', 'events.id')
            ->where('events.organizer_id', $organizerId)
            ->where('ticket_categories.total_quantity', '>', 0)
            ->orderByDesc('events.event_date')
            ->orderBy('ticket_categories.price')
            ->get();
    }
}
