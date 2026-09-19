<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Performer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = implode(' ', fake()->words(3));

        return [
            'organizer_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(4)),
            'description' => fake()->paragraphs(3, true),
            'banner_path' => null,
            'venue_name' => fake()->company().' Hall',
            'city' => fake()->randomElement(['Jakarta', 'Bandung', 'Surabaya', 'Yogyakarta', 'Bali', 'Makassar']),
            'address' => fake()->streetAddress(),
            'event_date' => now()->addDays(fake()->numberBetween(3, 60))->toDateString(),
            'start_time' => '19:00',
            'end_time' => '22:00',
            'status' => 'published',
        ];
    }

    /**
     * Attach a set of performers to the event after creation.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Event $event) {
            $performers = Performer::factory()->count(2)->create();
            $event->performers()->attach($performers->pluck('id'));
        });
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'event_date' => now()->subDays(1)->toDateString(),
        ]);
    }
}
