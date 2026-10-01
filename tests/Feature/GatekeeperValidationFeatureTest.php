<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GatekeeperValidationFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function gatekeeper(): User
    {
        return User::factory()->gatekeeper()->create();
    }

    private function activeTicket(): Ticket
    {
        $customer = User::factory()->create();
        $event = Event::factory()->published()->create();
        $event->eventDays()->create([
            'day_number' => 1,
            'date' => today()->toDateString(),
            'open_gate' => now()->subHour(),
            'close_gate' => now()->addHour(),
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
        $this->actingAs($customer)->post("/payments/{$order->payments()->first()->id}/settle");

        return Ticket::firstOrFail();
    }

    private function signedPayload(string $ticketCode, int $orderId, int $orderItemId): string
    {
        $data = "{$ticketCode}.{$orderId}.{$orderItemId}";

        return $data.'.'.hash_hmac('sha256', $data, (string) config('app.key'));
    }

    public function test_gatekeeper_can_open_scanner_page(): void
    {
        $this->actingAs($this->gatekeeper())
            ->get('/dashboard/scan')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Gatekeeper/Scanner'));
    }

    public function test_scanner_page_reports_scanned_and_capacity_counts(): void
    {
        $ticket = $this->activeTicket();
        $gatekeeper = $this->gatekeeper();

        $this->actingAs($gatekeeper)
            ->get('/dashboard/scan')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Gatekeeper/Scanner')
                ->where('scanStats.capacity', 1)
                ->where('scanStats.scanned', 0));

        $this->actingAs($gatekeeper)
            ->post('/dashboard/scan/validate', ['payload' => $ticket->qr_payload])
            ->assertSessionHas('scan_result.status', 'success');

        $this->actingAs($gatekeeper)
            ->get('/dashboard/scan')
            ->assertInertia(fn (Assert $page) => $page->where('scanStats.scanned', 1));
    }

    public function test_organizer_can_open_scanner_page(): void
    {
        $this->actingAs(User::factory()->organizer()->create())
            ->get('/dashboard/scan')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Gatekeeper/Scanner'));
    }

    public function test_admin_cannot_open_scanner_page(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/dashboard/scan')
            ->assertForbidden();
    }

    public function test_customer_cannot_open_scanner_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard/scan')
            ->assertForbidden();
    }

    public function test_guest_redirected_to_login_when_opening_scanner(): void
    {
        $this->get('/dashboard/scan')->assertRedirect('/login');
    }

    public function test_validating_active_ticket_records_daily_scan(): void
    {
        $ticket = $this->activeTicket();
        $gatekeeper = $this->gatekeeper();

        $this->actingAs($gatekeeper)
            ->post('/dashboard/scan/validate', ['payload' => $ticket->qr_payload])
            ->assertRedirect()
            ->assertSessionHas('scan_result.status', 'success');

        $this->assertSame('active', $ticket->fresh()->status);
        $this->assertSame(1, $ticket->ticketScans()->count());
        $this->assertSame($gatekeeper->id, $ticket->ticketScans()->first()->gatekeeper_id);
    }

    public function test_same_day_rescan_is_rejected_and_keeps_single_record(): void
    {
        $ticket = $this->activeTicket();
        $gatekeeper = $this->gatekeeper();

        $this->actingAs($gatekeeper)->post('/dashboard/scan/validate', ['payload' => $ticket->qr_payload]);
        $scannedAt = $ticket->ticketScans()->first()->scanned_at;

        $this->actingAs($gatekeeper)
            ->post('/dashboard/scan/validate', ['payload' => $ticket->qr_payload])
            ->assertSessionHas('scan_result.status', 'already_scanned')
            ->assertSessionHas('scan_result.message', fn (string $value) => str_contains($value, 'sudah pernah di-scan'));

        $this->assertSame('active', $ticket->fresh()->status);
        $this->assertSame(1, $ticket->ticketScans()->count());
        $this->assertTrue($ticket->ticketScans()->first()->scanned_at->equalTo($scannedAt));
    }

    public function test_tampered_payload_is_rejected_and_ticket_stays_active(): void
    {
        $ticket = $this->activeTicket();
        [$ticketCode, $orderId, $orderItemId, $hmac] = explode('.', $ticket->qr_payload);
        $tampered = "FAKECODE.{$orderId}.{$orderItemId}.{$hmac}";

        $this->actingAs($this->gatekeeper())
            ->post('/dashboard/scan/validate', ['payload' => $tampered])
            ->assertSessionHas('scan_result.status', 'invalid');

        $this->assertSame('active', $ticket->fresh()->status);
    }

    public function test_signed_payload_with_unknown_ticket_is_rejected(): void
    {
        $event = Event::factory()->published()->create();
        $order = Order::factory()->create(['customer_id' => User::factory()->create()->id]);
        $unknown = $order->orderItems()->create([
            'ticket_category_id' => $event->ticketCategories()->create([
                'name' => 'Reguler',
                'price' => 150_000,
                'total_quantity' => 100,
                'available_quantity' => 100,
                'max_per_order' => 5,
            ])->id,
            'quantity' => 1,
            'price_per_unit' => 150_000,
            'subtotal' => 150_000,
        ]);

        $payload = $this->signedPayload('UNKNOWNCODE', $order->id, $unknown->id);

        $this->actingAs($this->gatekeeper())
            ->post('/dashboard/scan/validate', ['payload' => $payload])
            ->assertSessionHas('scan_result.status', 'invalid');

        $this->assertSame(0, Ticket::count());
        $this->assertSame(OrderStatus::Pending->value, $order->fresh()->status);
    }

    public function test_payload_is_required_and_must_be_string(): void
    {
        $this->actingAs($this->gatekeeper())
            ->post('/dashboard/scan/validate', ['payload' => ''])
            ->assertSessionHasErrors('payload');

        $this->actingAs($this->gatekeeper())
            ->post('/dashboard/scan/validate')
            ->assertSessionHasErrors('payload');
    }
}
