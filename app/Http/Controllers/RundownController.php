<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Event;
use App\Models\EventRundown;
use App\Repositories\EventRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
