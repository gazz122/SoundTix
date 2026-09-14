<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Repositories\EventRepository;
use App\Repositories\PerformerRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrganizerEventController extends Controller
{
    public function __construct(
        private EventRepository $events,
        private PerformerRepository $performers,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $events = $this->events->forOrganizer($user->id);

        return Inertia::render('Organizer/Events/Index', [
            'events' => $events->map(fn (Event $event) => $this->serialize($event))->values()->all(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Organizer/Events/Form', ['event' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $event = $this->events->create([
            ...$data,
            'organizer_id' => $request->user()->id,
            'slug' => $this->uniqueSlug($data['title']),
            'banner_path' => $this->storeBanner($request),
        ]);

        $this->syncPerformers($event, $data['performers'] ?? []);

        return redirect()
            ->route('organizer.events.index')
            ->with('success', 'Event berhasil dibuat.');
    }

    public function edit(Request $request, int $id): Response
    {
        $event = $this->findOwnedEvent($request, $id);

        $event->load('performers:id,name');

        return Inertia::render('Organizer/Events/Form', [
            'event' => $this->serialize($event),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $event = $this->findOwnedEvent($request, $id);

        abort_if(in_array($event->status, [
            EventStatus::Published->value,
            EventStatus::Cancelled->value,
        ], true), 403, 'Event dengan status ini tidak dapat diedit.');

        $data = $this->validated($request);

        $event->update([
            ...$data,
            'slug' => $data['title'] !== $event->title ? $this->uniqueSlug($data['title']) : $event->slug,
            'banner_path' => $this->storeBanner($request, $event->banner_path),
        ]);

        $this->syncPerformers($event, $data['performers'] ?? []);

        return redirect()
            ->route('organizer.events.index')
            ->with('success', 'Perubahan event berhasil disimpan.');
    }

    public function submit(Request $request, int $id): RedirectResponse
    {
        $event = $this->findOwnedEvent($request, $id);

        abort_if($event->status === EventStatus::Published->value, 403, 'Event yang sudah tayang tidak dapat diajukan ulang.');

        $event->update([
            'status' => EventStatus::Pending->value,
            'rejection_reason' => null,
        ]);

        return redirect()
            ->route('organizer.events.index')
            ->with('success', 'Event dikirim untuk verifikasi admin.');
    }

    private function findOwnedEvent(Request $request, int $id): Event
    {
        return $this->events->findOwnedByOrganizer($request->user()->id, $id);
    }

    private function storeBanner(Request $request, ?string $currentPath = null): ?string
    {
        $file = $request->file('banner');

        if (! $file instanceof UploadedFile) {
            return $currentPath;
        }

        $path = $file->store('events', 'public');

        if ($currentPath !== null && $currentPath !== $path) {
            Storage::disk('public')->delete($currentPath);
        }

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'venue_name' => ['required', 'string', 'max:150'],
            'city' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date', 'after:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'status' => ['required', Rule::enum(EventStatus::class)->only([EventStatus::Draft, EventStatus::Pending])],
            'banner' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
            'performers' => ['nullable', 'array'],
            'performers.*' => ['string', 'max:120'],
        ]);

        return [
            'title' => $validated['title'],
            'description' => $validated['description'],
            'venue_name' => $validated['venue_name'],
            'city' => $validated['city'],
            'address' => $validated['address'],
            'event_date' => $validated['event_date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'] ?? null,
            'status' => $validated['status'],
            'performers' => $this->normalizePerformers($validated['performers'] ?? []),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function normalizePerformers(mixed $performers): array
    {
        $names = collect($performers)
            ->map(fn ($name) => trim((string) $name))
            ->reject(fn (string $name) => $name === '')
            ->unique()
            ->values();

        return $names->all();
    }

    /**
     * @param  array<int, string>  $names
     */
    private function syncPerformers(Event $event, array $names): void
    {
        $ids = collect($names)->map(fn (string $name) => $this->performers->firstOrCreateByName($name)->id)->all();

        $event->performers()->sync($ids);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $suffix = 2;

        while ($this->events->slugExists($slug)) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
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
            'description' => $event->description,
            'banner_path' => $event->banner_path,
            'banner_url' => $event->banner_path !== null ? Storage::disk('public')->url($event->banner_path) : null,
            'venue_name' => $event->venue_name,
            'city' => $event->city,
            'address' => $event->address,
            'event_date' => $event->event_date?->format('Y-m-d'),
            'start_time' => $event->start_time,
            'end_time' => $event->end_time,
            'status' => $event->status,
            'status_label' => EventStatus::from($event->status)->label(),
            'rejection_reason' => $event->rejection_reason,
            'performers' => $event->performers->pluck('name')->values()->all(),
            'performers_count' => $event->performers->count(),
            'ticket_categories_count' => $event->ticket_categories_count,
        ];
    }
}
