<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventDetailResource;
use App\Http\Resources\EventResource;
use App\Repositories\EventRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventController extends Controller
{
    public function __construct(private EventRepository $events) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return EventResource::collection(
            $this->events->publishedUpcomingPaginated($request->only(['q', 'city', 'date', 'category']), 12),
        );
    }

    public function show(string $slug): EventDetailResource
    {
        return new EventDetailResource($this->events->findPublishedBySlug($slug));
    }
}
