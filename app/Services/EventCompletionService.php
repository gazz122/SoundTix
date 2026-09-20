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
}
