<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payout>
 */
class PayoutFactory extends Factory
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
            'organizer_id' => User::factory(),
            'gross_amount' => $gross = fake()->numberBetween(5_000_000, 50_000_000),
            'platform_fee' => round($gross * 0.05, 2),
            'refund_deductions' => 0,
            'net_amount' => $gross - round($gross * 0.05, 2),
            'bank_name' => fake()->randomElement(['BCA', 'BRI', 'Mandiri', 'BNI']),
            'account_number' => fake()->numerify('#########'),
            'account_holder_name' => fake()->name(),
            'status' => 'pending',
            'processed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'processed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
        ]);
    }
}
