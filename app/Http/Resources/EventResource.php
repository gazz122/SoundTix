<?php

namespace App\Http\Resources;

use App\Models\Performer;
use App\Models\TicketCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class EventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'banner_path' => $this->banner_path,
            'banner_url' => $this->banner_path !== null ? Storage::disk('public')->url($this->banner_path) : null,
            'venue_name' => $this->venue_name,
            'city' => $this->city,
            'address' => $this->address,
            'event_date' => $this->event_date?->format('Y-m-d'),
            'start_time' => $this->time($this->start_time),
            'end_time' => $this->time($this->end_time),
            'starting_price' => $this->starting_price !== null ? (float) $this->starting_price : null,
            'is_saved' => (bool) ($this->saved_by_users_exists ?? false),
            'performers' => $this->performersPayload(),
            'ticket_categories' => $this->ticketCategoriesPayload(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function performersPayload(): array
    {
        if (! $this->relationLoaded('performers')) {
            return [];
        }

        return $this->performers
            ->map(fn (Performer $performer) => (new PerformerResource($performer))->resolve())
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function ticketCategoriesPayload(): array
    {
        if (! $this->relationLoaded('ticketCategories')) {
            return [];
        }

        return $this->ticketCategories
            ->map(fn (TicketCategory $category) => (new TicketCategoryResource($category))->resolve())
            ->values()
            ->all();
    }

    protected function time(mixed $value): ?string
    {
        return $value === null ? null : substr((string) $value, 0, 5);
    }
}
