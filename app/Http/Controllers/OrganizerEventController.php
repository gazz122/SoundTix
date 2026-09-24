<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Refund;
use App\Repositories\EventRepository;
use App\Repositories\PerformerRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
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

    public function show(Request $request, int $id): Response
    {
        $event = $this->events->findOwnedByOrganizer($request->user()->id, $id);
        $event->loadCount('ticketCategories')->load(['performers:id,name', 'rundowns']);

        $refunds = Refund::query()
            ->whereHas('order.orderItems.ticketCategory.event', fn ($query) => $query->whereKey($id))
            ->with('order.customer:id,name,email')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Refund $refund) => [
                'id' => $refund->id,
                'amount' => (float) $refund->amount,
                'status' => $refund->status->value,
                'status_label' => $refund->status->label(),
                'reason' => $refund->reason,
                'created_at' => $refund->created_at?->format('d M Y H:i'),
                'order_code' => $refund->order->order_code,
                'customer_name' => $refund->order->customer?->name,
            ])
            ->values()
            ->all();

        $data = $this->serialize($event);
        $data['cancellable'] = $event->status === EventStatus::Published->value
            && ($event->event_date === null || ! $event->event_date->isPast());
        $data['cancellation_reason'] = $event->cancellation_reason;
        $data['refunds'] = $refunds;

        return Inertia::render('Organizer/Events/Show', ['event' => $data]);
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
            'open_gate' => ['nullable', 'date'],
            'close_gate' => ['nullable', 'date', 'after:open_gate'],
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
            'open_gate' => $this->toUtcWib($validated['open_gate'] ?? null),
            'close_gate' => $this->toUtcWib($validated['close_gate'] ?? null),
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

    /**
     * Convert a WIB datetime-local input to an application-timezone (UTC) timestamp.
     */
    private function toUtcWib(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value, 'Asia/Jakarta')
            ->setTimezone('UTC')
            ->format('Y-m-d H:i:s');
    }

    private function wibInput(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value->copy()->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i');
    }

    private function wibLabel(mixed $value): ?string
    {
        return $value === null ? null : $value->copy()->setTimezone('Asia/Jakarta')->format('d M Y, H:i');
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
            'open_gate' => $this->wibInput($event->open_gate),
            'close_gate' => $this->wibInput($event->close_gate),
            'open_gate_label' => $this->wibLabel($event->open_gate),
            'close_gate_label' => $this->wibLabel($event->close_gate),
            'rundowns' => $event->rundowns
                ->map(fn ($rundown) => [
                    'id' => $rundown->id,
                    'start_time' => substr((string) $rundown->start_time, 0, 5),
                    'end_time' => $rundown->end_time !== null ? substr((string) $rundown->end_time, 0, 5) : null,
                    'title' => $rundown->title,
                    'description' => $rundown->description,
                ])
                ->values()
                ->all(),
            'status' => $event->status,
            'status_label' => EventStatus::from($event->status)->label(),
            'rejection_reason' => $event->rejection_reason,
            'performers' => $event->performers->pluck('name')->values()->all(),
            'performers_count' => $event->performers->count(),
            'ticket_categories_count' => $event->ticket_categories_count,
        ];
    }
}
