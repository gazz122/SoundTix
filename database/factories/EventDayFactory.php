<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventDay>
 */
class EventDayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'day_number' => 1,
            'date' => now()->toDateString(),
            'open_gate' => null,
            'close_gate' => null,
        ];
    }

    public function day(int $number): static
    {
        return $this->state(fn (array $attributes) => [
            'day_number' => $number,
        ]);
    }
}
