<?php

namespace Database\Factories;

use App\Enums\RefundStatus;
use App\Models\Order;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'amount' => fake()->numberBetween(10_000, 500_000),
            'refund_key' => 'rfnd-'.Str::uuid(),
            'status' => RefundStatus::Completed,
            'reason' => fake()->sentence(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RefundStatus::Completed,
            'processed_at' => now(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RefundStatus::Pending,
            'processed_at' => null,
            'failure_reason' => null,
        ]);
    }

    public function failed(?string $failureReason = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RefundStatus::Failed,
            'failure_reason' => $failureReason ?? 'Midtrans menolak refund.',
            'processed_at' => now(),
        ]);
    }
}
