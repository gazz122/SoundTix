<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TicketValidationApiFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function gatekeeper(): User
    {
        return User::factory()->gatekeeper()->create();
    }

    private function paidTicketFor(Event $event): Ticket
    {
        $customer = User::factory()->create();
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
        $this->actingAs($customer)->post("/payments/{$order->payments()->first()->id}/settle");

        return Ticket::firstOrFail();
    }

    private function activeTicket(): Ticket
    {
        $event = Event::factory()->published()->create();
        $event->eventDays()->create([
            'day_number' => 1,
            'date' => today()->toDateString(),
            'open_gate' => now()->subHour(),
            'close_gate' => now()->addHour(),
        ]);

        return $this->paidTicketFor($event);
    }

    public function test_gatekeeper_can_validate_active_ticket_via_api(): void
    {
        $ticket = $this->activeTicket();
        $gatekeeper = $this->gatekeeper();

        $this->actingAs($gatekeeper, 'sanctum')
            ->postJson('/api/v1/tickets/validate', ['payload' => $ticket->qr_payload])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('ticket.ticket_code', $ticket->ticket_code);

        $this->assertSame('active', $ticket->fresh()->status);
        $this->assertSame(1, $ticket->fresh()->ticketScans()->count());
        $this->assertSame($gatekeeper->id, $ticket->fresh()->ticketScans()->first()->gatekeeper_id);
    }

    public function test_organizer_can_validate_ticket_via_api(): void
    {
        $ticket = $this->activeTicket();

        $this->actingAs(User::factory()->organizer()->create(), 'sanctum')
            ->postJson('/api/v1/tickets/validate', ['payload' => $ticket->qr_payload])
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_customer_cannot_validate_ticket_via_api(): void
    {
        $ticket = $this->activeTicket();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/v1/tickets/validate', ['payload' => $ticket->qr_payload])
            ->assertForbidden();

        $this->assertSame('active', $ticket->fresh()->status);
    }

    public function test_guest_cannot_validate_ticket_via_api(): void
    {
        $this->postJson('/api/v1/tickets/validate', ['payload' => 'tidak.terautentikasi'])
            ->assertUnauthorized();
    }

    public function test_tampered_payload_is_rejected_via_api(): void
    {
        $ticket = $this->activeTicket();
        [$ticketCode, $orderId, $orderItemId, $hmac] = explode('.', $ticket->qr_payload);
        $tampered = "FAKECODE.{$orderId}.{$orderItemId}.{$hmac}";

        $this->actingAs($this->gatekeeper(), 'sanctum')
            ->postJson('/api/v1/tickets/validate', ['payload' => $tampered])
            ->assertOk()
            ->assertJsonPath('status', 'invalid');

        $this->assertSame('active', $ticket->fresh()->status);
    }

    public function test_payload_is_required_via_api(): void
    {
        $this->actingAs($this->gatekeeper(), 'sanctum')
            ->postJson('/api/v1/tickets/validate')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payload');
    }

    public function test_scan_endpoint_rejects_same_day_duplicate(): void
    {
        $ticket = $this->activeTicket();
        $gatekeeper = $this->gatekeeper();

        $this->actingAs($gatekeeper, 'sanctum')
            ->postJson('/api/v1/gatekeeper/scan', ['payload' => $ticket->qr_payload])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->actingAs($gatekeeper, 'sanctum')
            ->postJson('/api/v1/gatekeeper/scan', ['payload' => $ticket->qr_payload])
            ->assertOk()
            ->assertJsonPath('status', 'already_scanned');
    }

    public function test_scan_without_event_day_for_today_is_rejected(): void
    {
        $event = Event::factory()->published()->create();
        $ticket = $this->paidTicketFor($event);

        $this->actingAs($this->gatekeeper(), 'sanctum')
            ->postJson('/api/v1/gatekeeper/scan', ['payload' => $ticket->qr_payload])
            ->assertOk()
            ->assertJsonPath('status', 'no_event_day')
            ->assertJsonPath('message', 'Tidak ada jadwal event untuk hari ini.');
    }

    public function test_the_same_ticket_can_be_scanned_once_on_every_event_day(): void
    {
        $today = today()->toDateString();
        $tomorrow = today()->addDay()->toDateString();
        $event = Event::factory()->published()->create();
        $event->eventDays()->create(['day_number' => 1, 'date' => $today]);
        $event->eventDays()->create(['day_number' => 2, 'date' => $tomorrow]);
        $ticket = $this->paidTicketFor($event);
        $gatekeeper = $this->gatekeeper();

        $this->actingAs($gatekeeper, 'sanctum')
            ->postJson('/api/v1/gatekeeper/scan', ['payload' => $ticket->qr_payload])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Tiket Valid! Silakan masuk untuk Day 1.');

        Carbon::setTestNow(Carbon::parse($tomorrow)->startOfDay());

        try {
            $this->actingAs($gatekeeper, 'sanctum')
                ->postJson('/api/v1/gatekeeper/scan', ['payload' => $ticket->qr_payload])
                ->assertOk()
                ->assertJsonPath('status', 'success')
                ->assertJsonPath('message', 'Tiket Valid! Silakan masuk untuk Day 2.');
        } finally {
            Carbon::setTestNow(null);
        }

        $this->assertSame('active', $ticket->fresh()->status);
        $this->assertSame(2, $ticket->fresh()->ticketScans()->count());
    }
}
