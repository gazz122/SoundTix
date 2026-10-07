<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'access_type' => $this->access_type,
            'event_day' => $this->eventDay !== null ? [
                'day_number' => $this->eventDay->day_number,
                'date' => $this->eventDay->date?->format('Y-m-d'),
            ] : null,
            'price' => (float) $this->price,
            'total_quantity' => $this->total_quantity,
            'available_quantity' => $this->available_quantity,
            'max_per_order' => $this->max_per_order,
        ];
    }
}
