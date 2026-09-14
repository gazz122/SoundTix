<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Repositories\EventRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminEventController extends Controller
{
    public function __construct(private EventRepository $events) {}

    public function index(Request $request): Response
    {
        $status = $request->query('status', EventStatus::Pending->value);

        $events = $this->events->paginateForAdmin($status, 10)->withQueryString();

        return Inertia::render('Admin/Events/Index', [
            'events' => $events->through(fn (Event $event) => $this->serialize($event)),
            'current_status' => $status,
        ]);
    }

    public function approve(Request $request, int $id): RedirectResponse
    {
        $event = $this->findPendingEvent($id);

        $event->update(['status' => EventStatus::Published->value]);

        return redirect()
            ->route('admin.events.index', ['status' => EventStatus::Pending->value])
            ->with('success', 'Event "'.$event->title.'" disetujui dan sekarang tayang.');
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        $event = $this->findPendingEvent($id);

        $reason = $request->validate(['rejection_reason' => ['required', 'string', 'max:500']])['rejection_reason'];

        $event->update([
            'status' => EventStatus::Rejected->value,
            'rejection_reason' => $reason,
        ]);

        return redirect()
            ->route('admin.events.index', ['status' => EventStatus::Pending->value])
            ->with('success', 'Event "'.$event->title.'" ditolak.');
    }

    private function findPendingEvent(int $id): Event
    {
        return $this->events->findPending($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Event $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'slug' => $event->slug,
            'venue_name' => $event->venue_name,
            'city' => $event->city,
            'event_date' => $event->event_date?->format('d M Y'),
            'status' => $event->status,
            'status_label' => EventStatus::from($event->status)->label(),
            'rejection_reason' => $event->rejection_reason,
            'organizer' => ['id' => $event->organizer->id, 'name' => $event->organizer->name],
            'performers' => $event->performers->pluck('name')->values()->all(),
            'ticket_categories_count' => $event->ticket_categories_count,
        ];
    }
}
