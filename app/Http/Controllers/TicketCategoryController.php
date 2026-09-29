<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Event;
use App\Models\EventDay;
use App\Models\TicketCategory;
use App\Repositories\EventRepository;
use App\Repositories\TicketCategoryRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TicketCategoryController extends Controller
{
    public function __construct(
        private EventRepository $events,
        private TicketCategoryRepository $categories,
    ) {}

    public function index(Request $request, int $event): Response
    {
        $event = $this->findEvent($request, $event);

        $categories = $this->categories->forEventOrderedByPrice($event)
            ->map(fn (TicketCategory $category) => $this->serialize($category))
            ->values()
            ->all();

        return Inertia::render('Organizer/Events/Categories', [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'slug' => $event->slug,
                'status' => $event->status,
                'city' => $event->city,
                'event_date' => $event->event_date?->format('Y-m-d'),
                'duration_type' => $event->duration_type,
            ],
            'event_days' => $event->eventDays()->get(['id', 'day_number', 'date'])
                ->map(fn (EventDay $day) => [
                    'id' => $day->id,
                    'day_number' => $day->day_number,
                    'date' => $day->date->format('Y-m-d'),
                ])
                ->values()
                ->all(),
            'categories' => $categories,
            'can_manage' => $this->canManage($request, $event),
        ]);
    }

    public function store(Request $request, int $event): RedirectResponse
    {
        $event = $this->findEvent($request, $event);

        $data = $this->validatedCreation($request, $event);

        $this->categories->createForEvent($event, [
            ...$data,
            'available_quantity' => $data['total_quantity'],
        ]);

        return redirect()
            ->route('organizer.events.categories', $event->id)
            ->with('success', 'Kategori tiket berhasil ditambahkan.');
    }

    public function update(Request $request, int $event, int $category): RedirectResponse
    {
        $event = $this->findEvent($request, $event);

        /** @var TicketCategory $category */
        $category = $this->categories->findInEvent($event, $category);

        $data = $this->validatedCreation($request, $event);

        $sold = $category->total_quantity - $category->available_quantity;
        $available = $data['total_quantity'] - $sold;

        abort_if($available < 0, 422, 'Total stok tidak boleh kurang dari jumlah tiket yang sudah terjual.');

        $category->update([...$data, 'available_quantity' => $available]);

        return redirect()
            ->route('organizer.events.categories', $event->id)
            ->with('success', 'Kategori tiket berhasil diperbarui.');
    }

    public function destroy(Request $request, int $event, int $category): RedirectResponse
    {
        $event = $this->findEvent($request, $event);

        /** @var TicketCategory $category */
        $category = $this->categories->findInEvent($event, $category);

        abort_if($category->available_quantity < $category->total_quantity, 422,
            'Kategori tidak dapat dihapus karena masih ada tiket yang terjual.');

        $category->delete();

        return redirect()
            ->route('organizer.events.categories', $event->id)
            ->with('success', 'Kategori tiket dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedCreation(Request $request, Event $event): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'min:0'],
            'total_quantity' => ['required', 'integer', 'min:1'],
            'max_per_order' => ['required', 'integer', 'min:1', 'max:5'],
            'access_type' => ['sometimes', 'required', Rule::in(['ALL_DAYS', 'SINGLE_DAY'])],
            'event_day_id' => ['nullable', 'integer'],
        ]);

        $accessType = $validated['access_type'] ?? 'ALL_DAYS';
        $eventDayId = $validated['event_day_id'] ?? null;

        if ($accessType === 'SINGLE_DAY') {
            abort_if($eventDayId === null, 422, 'Pilih hari untuk kategori tiket per hari.');
            abort_if(! EventDay::whereKey($eventDayId)->where('event_id', $event->id)->exists(), 422,
                'Hari yang dipilih bukan bagian dari event ini.');
        }

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'total_quantity' => $validated['total_quantity'],
            'max_per_order' => $validated['max_per_order'],
            'access_type' => $accessType,
            'event_day_id' => $accessType === 'SINGLE_DAY' ? $eventDayId : null,
        ];
    }

    private function findEvent(Request $request, int $id): Event
    {
        $event = $this->events->findByIdOrFail($id);

        if (! $this->canManage($request, $event)) {
            abort(404);
        }

        return $event;
    }

    private function canManage(Request $request, Event $event): bool
    {
        return $request->user()->role === UserRole::Admin
            || $event->organizer_id === $request->user()->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(TicketCategory $category): array
    {
        $sold = $category->total_quantity - $category->available_quantity;

        return [
            'id' => $category->id,
            'name' => $category->name,
            'description' => $category->description,
            'access_type' => $category->access_type,
            'event_day_id' => $category->event_day_id,
            'event_day_label' => $category->eventDay !== null
                ? sprintf('Day %d · %s', $category->eventDay->day_number, $category->eventDay->date->format('d M Y'))
                : null,
            'price' => (float) $category->price,
            'total_quantity' => $category->total_quantity,
            'available_quantity' => $category->available_quantity,
            'max_per_order' => $category->max_per_order,
            'sold' => $sold,
            'sold_out' => $category->available_quantity === 0,
        ];
    }
}
