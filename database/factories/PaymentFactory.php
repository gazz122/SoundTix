<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
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
            'transaction_id' => (string) Str::uuid(),
            'midtrans_transaction_id' => null,
            'payment_type' => PaymentType::Qris->value,
            'gross_amount' => 100_000,
            'payment_status' => PaymentStatus::Pending->value,
        ];
    }

    public function settlement(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => PaymentStatus::Settlement->value,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => PaymentStatus::Failure->value,
        ]);
    }
}
