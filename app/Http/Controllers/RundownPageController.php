<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\EventDay;
use App\Models\EventRundown;
use App\Models\Order;
use App\Services\RundownPayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RundownPageController extends Controller
{
    public function __construct(private RundownPayload $payload) {}

    public function scan(Request $request): Response|RedirectResponse
    {
        $payload = (string) $request->query('payload', '');

        if ($payload === '') {
            return Inertia::render('Rundown/Scan');
        }

        $data = $this->payload->parse($payload);

        if ($data === null) {
            return redirect()
                ->route('rundown.scan')
                ->withErrors(['payload' => 'Payload QR tidak valid.']);
        }

        $order = Order::find($data['order_id']);
        $event = Event::find($data['event_id']);

        if ($order === null || $event === null || $order->status !== OrderStatus::Paid->value) {
            return redirect()
                ->route('rundown.scan')
                ->withErrors(['payload' => 'Payload QR tidak berlaku untuk pesanan ini.']);
        }

        abort_unless(
            $order->orderItems()
                ->whereHas('ticketCategory', fn ($query) => $query->where('event_id', $event->id))
                ->exists(),
            404,
        );

        $event->load([
            'eventDays',
            'rundowns.performer:id,name,genre',
        ]);

        return Inertia::render('Rundown/Show', [
            'payload' => $payload,
            'event' => [
                'title' => $event->title,
                'venue_name' => $event->venue_name,
                'city' => $event->city,
                'event_date' => $event->event_date?->format('Y-m-d'),
                'duration_type' => $event->duration_type,
                'event_days' => $event->eventDays
                    ->map(fn (EventDay $day) => [
                        'id' => $day->id,
                        'day_number' => $day->day_number,
                        'date' => $day->date?->format('Y-m-d'),
                    ])
                    ->values()
                    ->all(),
                'rundowns' => $event->rundowns
                    ->map(fn (EventRundown $rundown) => [
                        'id' => $rundown->id,
                        'start_time' => substr((string) $rundown->start_time, 0, 5),
                        'end_time' => $rundown->end_time !== null ? substr((string) $rundown->end_time, 0, 5) : null,
                        'title' => $rundown->title,
                        'description' => $rundown->description,
                        'event_day_id' => $rundown->event_day_id,
                        'performer' => $rundown->performer === null ? null : [
                            'id' => $rundown->performer->id,
                            'name' => $rundown->performer->name,
                            'genre' => $rundown->performer->genre,
                        ],
                        'updated_at' => $rundown->updated_at?->toIso8601String(),
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }
}
