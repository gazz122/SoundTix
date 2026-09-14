<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_code' => 'STX-'.now()->format('Ymd').'-'.Str::upper(Str::random(4)),
            'customer_id' => User::factory(),
            'total_amount' => 0,
            'admin_fee' => 0,
            'grand_total' => 0,
            'status' => OrderStatus::Pending->value,
            'expires_at' => now()->addMinutes(15),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Paid->value,
            'paid_at' => now(),
            'expires_at' => now()->subHour(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Cancelled->value,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Expired->value,
            'expires_at' => now()->subMinutes(5),
        ]);
    }
}
