<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventDetailResource;
use App\Http\Resources\EventResource;
use App\Repositories\EventRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function __construct(private EventRepository $events) {}

    public function index(Request $request): Response
    {
        $filters = $request->only('q');

        $events = $this->events->publishedUpcomingPaginated($filters, 9)->withQueryString();

        return Inertia::render('Home/Index', [
            'events' => EventResource::collection($events),
            'featured' => EventResource::collection($this->events->featuredPublishedUpcoming(4)),
            'filters' => $filters,
        ]);
    }

    public function show(string $slug): Response
    {
        $event = $this->events->findPublishedBySlug($slug);

        return Inertia::render('Events/Show', [
            'event' => (new EventDetailResource($event))->resolve(),
        ]);
    }
}
