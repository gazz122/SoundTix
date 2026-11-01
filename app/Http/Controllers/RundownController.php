<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Event;
use App\Models\EventRundown;
use App\Repositories\EventRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RundownController extends Controller
{
    public function __construct(private EventRepository $events) {}

    public function store(Request $request, int $event): RedirectResponse
    {
        $event = $this->findEvent($request, $event);

        $event->rundowns()->create($this->validated($request, $event));

        return $this->back($event, 'Sesi rundown berhasil ditambahkan.');
    }

    public function update(Request $request, int $event, int $rundown): RedirectResponse
    {
        $event = $this->findEvent($request, $event);

        /** @var EventRundown $rundown */
        $rundown = $event->rundowns()->findOrFail($rundown);

        $rundown->update($this->validated($request, $event, $rundown));

        return $this->back($event, 'Sesi rundown berhasil diperbarui.');
    }

    public function destroy(Request $request, int $event, int $rundown): RedirectResponse
    {
        $event = $this->findEvent($request, $event);

        $event->rundowns()->findOrFail($rundown)->delete();

        return $this->back($event, 'Sesi rundown dihapus.');
    }

    public function reorder(Request $request, int $event): RedirectResponse
    {
        $event = $this->findEvent($request, $event);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        $rundowns = $event->rundowns()->whereKey($data['ids'])->get()->keyBy('id');

        abort_if($rundowns->count() !== count($data['ids']), 404);

        abort_if(
            $rundowns->pluck('event_day_id')->unique()->count() > 1,
            422,
            'Sesi yang diurutkan harus berasal dari hari yang sama.',
        );

        /** @var Collection<int, EventRundown> $ordered */
        $ordered = collect($data['ids'])->map(fn ($id) => $rundowns[$id]);

        $cursor = $ordered->map(fn (EventRundown $rundown) => $this->minutes($rundown->start_time))->min();

        DB::transaction(function () use ($ordered, $cursor): void {
            foreach ($ordered as $rundown) {
                $end = $cursor + $this->durationInMinutes($rundown);

                $rundown->update([
                    'start_time' => $this->clock($cursor),
                    'end_time' => $this->clock($end),
                ]);

                $cursor = $end;
            }
        });

        return $this->back($event, 'Urutan rundown diperbarui.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Event $event, ?EventRundown $rundown = null): array
    {
        $isMultiDay = $event->duration_type === 'multi_day';

        $allowedPerformers = $event->performers()
            ->pluck('performers.id')
            ->map(fn (int $id) => (string) $id)
            ->all();

        if ($rundown?->performer_id !== null) {
            $allowedPerformers[] = (string) $rundown->performer_id;
        }

        $data = $request->validate([
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'title' => ['nullable', 'string', 'max:150', 'required_without:performer_id'],
            'description' => ['nullable', 'string', 'max:500'],
            'performer_id' => ['nullable', 'integer', Rule::in($allowedPerformers), 'required_without:title'],
            'event_day_id' => $isMultiDay
                ? ['required', 'integer', Rule::in(
                    $event->eventDays()->pluck('id')->map(fn (int $id) => (string) $id)->all()
                )]
                : ['nullable'],
        ]);

        if (! $isMultiDay) {
            $data['event_day_id'] = null;
        }

        return $data;
    }

    private function minutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', substr($time, 0, 5)));

        return $hour * 60 + $minute;
    }

    private function durationInMinutes(EventRundown $rundown): int
    {
        if ($rundown->end_time === null) {
            return 30;
        }

        $duration = $this->minutes((string) $rundown->end_time) - $this->minutes((string) $rundown->start_time);

        return $duration > 0 ? $duration : 30;
    }

    private function clock(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60) % 24, $minutes % 60);
    }

    private function findEvent(Request $request, int $id): Event
    {
        $event = $this->events->findByIdOrFail($id);

        abort_if($request->user()->role !== UserRole::Admin && $event->organizer_id !== $request->user()->id, 404);

        return $event;
    }

    private function back(Event $event, string $message): RedirectResponse
    {
        return redirect()
            ->route('organizer.events.show', $event->id)
            ->with('success', $message);
    }
}
