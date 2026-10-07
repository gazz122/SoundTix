<?php

namespace App\Http\Resources;

use App\Models\EventDay;
use App\Models\EventRundown;
use Illuminate\Http\Request;

class EventDetailResource extends EventResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'organizer' => ['id' => $this->organizer->id, 'name' => $this->organizer->name],
            'open_gate' => $this->wib($this->open_gate, 'Y-m-d H:i'),
            'close_gate' => $this->wib($this->close_gate, 'Y-m-d H:i'),
            'duration_type' => $this->duration_type,
            'event_days' => $this->eventDays
                ->map(fn (EventDay $day) => [
                    'day_number' => $day->day_number,
                    'date' => $day->date?->format('Y-m-d'),
                    'open_gate' => $this->wib($day->open_gate, 'Y-m-d H:i'),
                    'close_gate' => $this->wib($day->close_gate, 'Y-m-d H:i'),
                ])
                ->values()
                ->all(),
            'rundowns' => $this->rundownsPayload(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rundownsPayload(): array
    {
        if (! $this->relationLoaded('rundowns')) {
            return [];
        }

        return $this->rundowns
            ->map(fn (EventRundown $rundown) => [
                'id' => $rundown->id,
                'start_time' => substr((string) $rundown->start_time, 0, 5),
                'end_time' => $rundown->end_time !== null ? substr((string) $rundown->end_time, 0, 5) : null,
                'title' => $rundown->title,
                'description' => $rundown->description,
            ])
            ->values()
            ->all();
    }

    protected function wib(mixed $value, string $format): ?string
    {
        return $value === null ? null : $value->copy()->setTimezone('Asia/Jakarta')->format($format);
    }
}
