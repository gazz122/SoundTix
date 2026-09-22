<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Models\Event;

class EventCompletionService
{
    /**
     * Mark published events whose date has passed as completed so the
     * organizer becomes eligible to request a payout.
     */
    public function completePastEvents(): int
    {
        return Event::query()
            ->where('status', EventStatus::Published->value)
            ->whereDate('event_date', '<', now()->toDateString())
            ->update(['status' => EventStatus::Completed->value]);
    }

    /**
     * Lazily complete a single published event if its date has already passed.
     * Ensures payout eligibility does not depend on the scheduled command.
     */
    public function completeIfPast(int $eventId): bool
    {
        return Event::query()
            ->whereKey($eventId)
            ->where('status', EventStatus::Published->value)
            ->whereDate('event_date', '<', now()->toDateString())
            ->update(['status' => EventStatus::Completed->value]) === 1;
    }
}
