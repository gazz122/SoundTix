<?php

namespace App\Http\Controllers;

use App\Http\Resources\BannerResource;
use App\Http\Resources\EventDetailResource;
use App\Http\Resources\EventResource;
use App\Models\Banner;
use App\Models\Event;
use App\Repositories\EventRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function __construct(private EventRepository $events) {}

    public function index(Request $request): Response
    {
        $filters = $request->only('q');

        $events = $this->events->publishedUpcomingPaginated($filters, 9, $request->user()?->id)->withQueryString();

        return Inertia::render('Home/Index', [
            'events' => EventResource::collection($events),
            'banners' => $this->banners(),
            'filters' => $filters,
        ]);
    }

    /**
     * Active promotional banners for the homepage carousel, with static
     * placeholders so the hero never renders empty.
     *
     * @return list<array{id: int, title: string, image_url: string, link_url: string}>
     */
    private function banners(): array
    {
        $banners = Banner::query()->active()->get();

        if ($banners->isEmpty()) {
            return [
                [
                    'id' => 0,
                    'title' => 'Tiket konser digital',
                    'image_url' => '/images/banners/tiket-digital.svg',
                    'link_url' => '/',
                ],
                [
                    'id' => 0,
                    'title' => 'Event baru tiap minggu',
                    'image_url' => '/images/banners/jadwal-event.svg',
                    'link_url' => '/',
                ],
                [
                    'id' => 0,
                    'title' => 'Tiket aman, refund mudah',
                    'image_url' => '/images/banners/tiket-aman.svg',
                    'link_url' => '/faq',
                ],
            ];
        }

        return BannerResource::collection($banners)->resolve();
    }

    public function explore(Request $request): Response
    {
        $filters = $request->only('q');

        $events = $this->events->publishedUpcomingPaginated($filters, 12, $request->user()?->id)->withQueryString();

        return Inertia::render('Explore/Index', [
            'events' => EventResource::collection($events),
            'filters' => $filters,
        ]);
    }

    public function saved(Request $request): Response
    {
        $events = $this->events->savedForUser($request->user(), 12)->withQueryString();

        return Inertia::render('SavedEvents/Index', [
            'events' => EventResource::collection($events),
        ]);
    }

    public function save(Request $request, Event $event): RedirectResponse
    {
        $request->user()->savedEvents()->syncWithoutDetaching([$event->id]);

        return redirect()->back();
    }

    public function unsave(Request $request, Event $event): RedirectResponse
    {
        $request->user()->savedEvents()->detach($event->id);

        return redirect()->back();
    }

    public function show(Request $request, string $slug): Response
    {
        $event = $this->events->findPublishedBySlug($slug, $request->user()?->id);

        return Inertia::render('Events/Show', [
            'event' => (new EventDetailResource($event))->resolve(),
        ]);
    }
}
