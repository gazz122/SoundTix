<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class EventDetailResource extends EventResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'organizer' => ['id' => $this->organizer->id, 'name' => $this->organizer->name],
        ];
    }
}
