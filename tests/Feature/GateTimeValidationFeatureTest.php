<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GateTimeValidationFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function gatekeeper(): User
    {
        return User::factory()->gatekeeper()->create();
    }

    /**
     * Create an issued ticket for an event whose concert day (today) has the
     * given gate window.
     *
     * @param  array{open: Carbon, close: Carbon}  $gates
     */
    private function activeTicket(array $gates): Ticket
    {
        $customer = User::factory()->create();
        $event = Event::factory()->published()->create();
        $event->eventDays()->create([
            'day_number' => 1,
            'date' => today()->toDateString(),
            'open_gate' => $gates['open'],
            'close_gate' => $gates['close'],
        ]);
        $category = $event->ticketCategories()->create([
            'name' => 'Reguler',
            'price' => 150_000,
            'total_quantity' => 100,
            'available_quantity' => 100,
            'max_per_order' => 5,
        ]);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'expires_at' => now()->addMinutes(15),
            'total_amount' => 150_000,
            'admin_fee' => 0,
            'grand_total' => 150_000,
        ]);
        $order->orderItems()->create([
            'ticket_category_id' => $category->id,
            'quantity' => 1,
            'price_per_unit' => 150_000,
            'subtotal' => 150_000,
        ]);
        $category->decrement('available_quantity');

        $this->actingAs($customer)->post("/orders/{$order->order_code}/pay", ['method' => 'bank_transfer', 'bank' => 'bca']);
        $this->actingAs($customer)->post("/payments/{$order->payments()->first()->id}/check");

        return $order->orderItems->first()->tickets->first();
    }

    public function test_scan_rejected_before_open_gate_with_wib_message(): void
    {
        $ticket = $this->activeTicket([
            'open' => now()->addHour(),
            'close' => now()->addDays(1),
        ]);

        $this->actingAs($this->gatekeeper())
            ->post('/dashboard/scan/validate', ['payload' => $ticket->qr_payload])
            ->assertSessionHas('scan_result.status', 'not_open')
            ->assertSessionHas('scan_result.message', fn (string $value) => str_contains($value, 'Pintu Day 1 belum dibuka'));

        $this->assertSame('active', $ticket->fresh()->status);
    }

    public function test_scan_rejected_after_close_gate_with_wib_message(): void
    {
        $ticket = $this->activeTicket([
            'open' => now()->subDays(1),
            'close' => now()->subHour(),
        ]);

        $this->actingAs($this->gatekeeper())
            ->post('/dashboard/scan/validate', ['payload' => $ticket->qr_payload])
            ->assertSessionHas('scan_result.status', 'closed')
            ->assertSessionHas('scan_result.message', fn (string $value) => str_contains($value, 'Pintu Day 1 sudah ditutup'));

        $this->assertSame('active', $ticket->fresh()->status);
    }

    public function test_scan_succeeds_within_gate_window(): void
    {
        $ticket = $this->activeTicket([
            'open' => now()->subHour(),
            'close' => now()->addHour(),
        ]);

        $this->actingAs($this->gatekeeper())
            ->post('/dashboard/scan/validate', ['payload' => $ticket->qr_payload])
            ->assertSessionHas('scan_result.status', 'success');

        $this->assertSame('active', $ticket->fresh()->status);
        $this->assertSame(1, $ticket->fresh()->ticketScans()->count());
    }

    public function test_api_scan_respects_gate_window(): void
    {
        $closed = $this->activeTicket(['open' => now()->addHour(), 'close' => now()->addDays(1)]);

        $this->actingAs($this->gatekeeper(), 'sanctum')
            ->postJson('/api/v1/tickets/validate', ['payload' => $closed->qr_payload])
            ->assertOk()
            ->assertJsonPath('status', 'not_open')
            ->assertJsonPath('message', fn (string $value) => str_contains($value, 'Pintu Day 1 belum dibuka'));

        $this->assertSame('active', $closed->fresh()->status);

        $open = $this->activeTicket(['open' => now()->subHour(), 'close' => now()->addHour()]);

        $this->actingAs($this->gatekeeper(), 'sanctum')
            ->postJson('/api/v1/tickets/validate', ['payload' => $open->qr_payload])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertSame('active', $open->fresh()->status);
        $this->assertSame(1, $open->fresh()->ticketScans()->count());
    }
}
