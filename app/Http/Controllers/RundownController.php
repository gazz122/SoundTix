<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Event;
use App\Models\EventRundown;
use App\Repositories\EventRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RundownController extends Controller
{
    public function __construct(private EventRepository $events) {}

    public function store(Request $request, int $event): RedirectResponse
    {
        $event = $this->findEvent($request, $event);

        $event->rundowns()->create($this->validated($request));

        return $this->back($event, 'Sesi rundown berhasil ditambahkan.');
    }

    public function update(Request $request, int $event, int $rundown): RedirectResponse
    {
        $event = $this->findEvent($request, $event);

        /** @var EventRundown $rundown */
        $rundown = $event->rundowns()->findOrFail($rundown);

        $rundown->update($this->validated($request));

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
    private function validated(Request $request): array
    {
        return $request->validate([
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
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
