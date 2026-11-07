<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventDay;
use App\Models\EventRundown;
use App\Models\Order;

class RundownPayload
{
    /**
     * Build a signed payload string for a paid order and event.
     *
     * Payload format: rundown.{order_id}.{event_id}.{hmac_sha256}
     */
    public function payload(Order $order, Event $event): string
    {
        $data = "rundown.{$order->id}.{$event->id}";

        return $data.'.'.hash_hmac('sha256', $data, (string) config('app.key'));
    }

    /**
     * Verify the signature and return the referenced order and event ids.
     *
     * @return array{order_id: int, event_id: int}|null
     */
    public function parse(string $payload): ?array
    {
        $parts = explode('.', $payload);

        if (count($parts) !== 4 || $parts[0] !== 'rundown') {
            return null;
        }

        $hmac = array_pop($parts);
        $data = implode('.', $parts);

        if (! hash_equals(hash_hmac('sha256', $data, (string) config('app.key')), $hmac)) {
            return null;
        }

        return ['order_id' => (int) $parts[1], 'event_id' => (int) $parts[2]];
    }

    /**
     * Build a static viewer URL whose fragment contains the whole rundown.
     *
     * The QR only needs an http(s) URL for a phone camera to open it, so the
     * snapshot travels in the fragment and is rendered by public/rundown-viewer.html.
     */
    public function viewerUrl(Event $event): string
    {
        return url('/rundown-viewer.html').'#'.$this->encode($event);
    }

    private function encode(Event $event): string
    {
        $data = [
            't' => $event->title,
            'v' => $event->venue_name,
            'c' => $event->city,
            'd' => $event->event_date?->format('Y-m-d'),
            'm' => $event->duration_type === 'multi_day',
            'days' => $event->eventDays
                ->map(fn (EventDay $day) => [
                    'i' => $day->id,
                    'n' => $day->day_number,
                    'd' => $day->date?->format('Y-m-d'),
                ])
                ->values()
                ->all(),
            'r' => $event->rundowns
                ->sortBy('start_time')
                ->map(fn (EventRundown $rundown) => [
                    's' => substr((string) $rundown->start_time, 0, 5),
                    'e' => $rundown->end_time !== null ? substr((string) $rundown->end_time, 0, 5) : null,
                    't' => $rundown->title,
                    'p' => $rundown->performer?->name,
                    'g' => $rundown->performer?->genre,
                    'day' => $rundown->event_day_id,
                ])
                ->values()
                ->all(),
        ];

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return rtrim(strtr(base64_encode((string) $json), '+/', '-_'), '=');
    }
}
