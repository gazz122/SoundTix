<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\TicketValidationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GatekeeperScannerController extends Controller
{
    public function index(): Response
    {
        $rundowns = Event::query()
            ->published()
            ->upcoming()
            ->with(['rundowns' => fn ($query) => $query->orderBy('start_time')])
            ->where(function ($query) {
                $query->whereHas('rundowns')
                    ->orWhereNotNull('open_gate')
                    ->orWhereNotNull('close_gate');
            })
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->limit(5)
            ->get()
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'event_date' => $event->event_date?->format('d M Y'),
                'open_gate' => $event->open_gate?->setTimezone('Asia/Jakarta')->format('H:i'),
                'close_gate' => $event->close_gate?->setTimezone('Asia/Jakarta')->format('H:i'),
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

        return Inertia::render('Gatekeeper/Scanner', ['rundowns' => $rundowns]);
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
