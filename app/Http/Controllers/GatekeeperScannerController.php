<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketScan;
use App\Services\TicketValidationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GatekeeperScannerController extends Controller
{
    public function index(): Response
    {
        $events = Event::query()
            ->published()
            ->upcoming()
            ->with([
                'rundowns' => fn ($query) => $query->orderBy('start_time'),
                'eventDays',
            ])
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->get();

        $eventIds = $events->pluck('id');

        $gateEvents = $events->filter(fn (Event $event) => $event->rundowns->isNotEmpty()
            || $event->open_gate !== null
            || $event->close_gate !== null
            || $event->eventDays->contains(fn ($day) => $day->open_gate !== null || $day->close_gate !== null))
            ->take(5)
            ->values();

        $rundowns = $gateEvents->map(fn (Event $event) => [
            'id' => $event->id,
            'title' => $event->title,
            'event_date' => $event->event_date?->format('d M Y'),
            'duration_type' => $event->duration_type,
            'open_gate' => $event->open_gate?->setTimezone('Asia/Jakarta')->format('H:i'),
            'close_gate' => $event->close_gate?->setTimezone('Asia/Jakarta')->format('H:i'),
            'event_days' => $event->duration_type === 'multi_day' ? $event->eventDays
                ->map(fn ($day) => [
                    'day_number' => $day->day_number,
                    'date' => $day->date?->format('d M Y'),
                    'open_gate' => $day->open_gate?->setTimezone('Asia/Jakarta')->format('H:i'),
                    'close_gate' => $day->close_gate?->setTimezone('Asia/Jakarta')->format('H:i'),
                ])
                ->values()
                ->all() : [],
            'rundowns' => $event->rundowns
                ->map(fn ($rundown) => [
                    'start_time' => substr((string) $rundown->start_time, 0, 5),
                    'end_time' => $rundown->end_time !== null ? substr((string) $rundown->end_time, 0, 5) : null,
                    'title' => $rundown->title,
                    'description' => $rundown->description,
                ])
                ->values()
                ->all(),
        ])
            ->values()
            ->all();

        $scanStats = [
            'scanned' => (int) TicketScan::query()
                ->whereHas('eventDay', fn ($query) => $query->whereIn('event_id', $eventIds))
                ->count(),
            'capacity' => (int) Ticket::query()
                ->whereHas('orderItem.ticketCategory', fn ($query) => $query->whereIn('event_id', $eventIds))
                ->count(),
        ];

        return Inertia::render('Gatekeeper/Scanner', [
            'rundowns' => $rundowns,
            'scanStats' => $scanStats,
        ]);
    }

    public function validate(Request $request, TicketValidationService $service): RedirectResponse
    {
        $payload = $request->validate([
            'payload' => ['required', 'string', 'max:512'],
        ])['payload'];

        $result = $service->validate($payload, $request->user());

        return redirect()->back()->with('scan_result', $result);
    }
}
