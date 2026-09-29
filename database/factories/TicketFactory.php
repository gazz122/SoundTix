<?php

namespace Database\Factories;

use App\Enums\TicketStatus;
use App\Models\OrderItem;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_item_id' => OrderItem::factory(),
            'ticket_code' => 'STX'.strtoupper(Str::random(12)),
            'qr_payload' => implode('.', [fake()->uuid(), fake()->randomNumber(5), fake()->randomNumber(5), hash('sha256', fake()->uuid())]),
            'status' => TicketStatus::Active->value,
            'used_at' => null,
            'validated_by' => null,
        ];
    }

    public function used(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::Used->value,
            'used_at' => now(),
        ]);
    }
}
