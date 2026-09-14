<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\TicketCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketCategory>
 */
class TicketCategoryFactory extends Factory
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
            'name' => fake()->randomElement(['Reguler', 'Festival', 'VIP', 'VVIP', 'Presale']),
            'description' => fake()->sentence(),
            'price' => fake()->numberBetween(150_000, 1_500_000),
            'total_quantity' => $quantity = fake()->numberBetween(50, 1_000),
            'available_quantity' => $quantity,
            'max_per_order' => 5,
        ];
    }

    public function soldOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'available_quantity' => 0,
        ]);
    }
}
