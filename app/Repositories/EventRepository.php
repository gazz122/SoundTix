<?php

namespace App\Repositories;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EventRepository
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function publishedUpcomingPaginated(array $filters, int $perPage, ?int $userId = null): LengthAwarePaginator
    {
        return Event::query()
            ->published()
            ->upcoming()
            ->filterable($filters)
            ->withMin('ticketCategories as starting_price', 'price')
            ->with('performers')
            ->when($userId !== null, fn ($query) => $query->withExists(['savedByUsers' => fn ($query) => $query->where('saved_events.user_id', $userId)]))
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->paginate($perPage);
    }

    public function savedForUser(User $user, int $perPage): LengthAwarePaginator
    {
        return $user->savedEvents()
            ->published()
            ->upcoming()
            ->with('performers')
            ->withMin('ticketCategories as starting_price', 'price')
            ->withExists(['savedByUsers' => fn ($query) => $query->where('saved_events.user_id', $user->id)])
            ->orderByPivot('updated_at', 'desc')
            ->paginate($perPage);
    }

    /** Latest published upcoming events for the homepage hero carousel. */
    public function featuredPublishedUpcoming(int $limit = 4): Collection
    {
        return Event::query()
            ->published()
            ->upcoming()
            ->with('performers')
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->limit($limit)
            ->get();
    }

    public function findPublishedBySlug(string $slug, ?int $userId = null): Event
    {
        return Event::query()
            ->published()
            ->with(['performers', 'ticketCategories', 'organizer', 'rundowns'])
            ->when($userId !== null, fn ($query) => $query->withExists(['savedByUsers' => fn ($query) => $query->where('saved_events.user_id', $userId)]))
            ->where('slug', $slug)
            ->firstOrFail();
    }

    public function forOrganizer(int $organizerId): Collection
    {
        return Event::with('performers:id,name', 'eventDays')
            ->withCount('ticketCategories')
            ->where('organizer_id', $organizerId)
            ->orderByDesc('updated_at')
            ->get();
    }

    public function findOwnedByOrganizer(int $organizerId, int $id): Event
    {
        return Event::where('organizer_id', $organizerId)->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Event
    {
        return Event::create($attributes);
    }

    public function slugExists(string $slug): bool
    {
        return Event::where('slug', $slug)->exists();
    }

    public function paginateForAdmin(string $status, int $perPage): LengthAwarePaginator
    {
        return Event::with('organizer:id,name', 'performers:id,name')
            ->withCount('ticketCategories')
            ->when(in_array($status, [...array_column(EventStatus::cases(), 'value'), 'all'], true) && $status !== 'all',
                fn ($query) => $query->where('status', $status))
            ->orderByDesc('updated_at')
            ->paginate($perPage);
    }

    public function findPending(int $id): Event
    {
        return Event::where('status', EventStatus::Pending->value)->findOrFail($id);
    }

    public function pendingForAdmin(int $limit = 5): Collection
    {
        return Event::with('organizer:id,name')
            ->where('status', EventStatus::Pending->value)
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();
    }

    public function findByIdOrFail(int $id): Event
    {
        return Event::findOrFail($id);
    }

    public function countAll(): int
    {
        return Event::count();
    }

    public function countByStatus(string $status): int
    {
        return Event::where('status', $status)->count();
    }

    public function countForOrganizer(int $organizerId): int
    {
        return Event::where('organizer_id', $organizerId)->count();
    }

    public function countForOrganizerByStatus(int $organizerId, string $status): int
    {
        return Event::where('organizer_id', $organizerId)->where('status', $status)->count();
    }
}
