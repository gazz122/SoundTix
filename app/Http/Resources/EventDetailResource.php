<?php

namespace App\Http\Resources;

use App\Models\EventDay;
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
            'has_passed' => $this->hasPassed(),
            'event_days' => $this->eventDays
                ->map(fn (EventDay $day) => [
                    'id' => $day->id,
                    'day_number' => $day->day_number,
                    'date' => $day->date?->format('Y-m-d'),
                    'open_gate' => $this->wib($day->open_gate, 'Y-m-d H:i'),
                    'close_gate' => $this->wib($day->close_gate, 'Y-m-d H:i'),
                ])
                ->values()
                ->all(),
        ];
    }

    protected function wib(mixed $value, string $format): ?string
    {
        return $value === null ? null : $value->copy()->setTimezone('Asia/Jakarta')->format($format);
    }
}
