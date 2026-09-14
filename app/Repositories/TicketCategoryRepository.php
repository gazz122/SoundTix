<?php

namespace App\Repositories;

use App\Models\Event;
use App\Models\TicketCategory;
use Illuminate\Support\Collection;

class TicketCategoryRepository
{
    /**
     * @return array<int, string>
     */
    public function availableNames(): array
    {
        return TicketCategory::query()
            ->distinct()
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

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
}
