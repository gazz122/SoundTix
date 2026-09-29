<?php

namespace Database\Factories;

use App\Models\EventDay;
use App\Models\Ticket;
use App\Models\TicketScan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketScan>
 */
class TicketScanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'event_day_id' => EventDay::factory(),
            'scanned_at' => now(),
            'gatekeeper_id' => User::factory(),
        ];
    }
}
