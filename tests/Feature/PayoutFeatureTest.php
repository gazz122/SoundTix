<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payout;
use App\Models\Refund;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PayoutFeatureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Event}
     */
    private function completedEventWithRevenue(float $subtotal = 1_000_000): array
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->completed()->create(['organizer_id' => $organizer->id]);
        $category = TicketCategory::factory()->create(['event_id' => $event->id, 'price' => $subtotal]);

        $order = Order::factory()->paid()->create([
            'total_amount' => $subtotal,
            'grand_total' => $subtotal,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'ticket_category_id' => $category->id,
            'quantity' => 1,
            'price_per_unit' => $subtotal,
            'subtotal' => $subtotal,
        ]);

        return [$organizer, $event];
    }

    private function bankPayload(): array
    {
        return [
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder_name' => 'PT Soundtix Promoter',
        ];
    }

    public function test_organizer_can_request_payout_and_fee_is_five_percent(): void
    {
        [$organizer, $event] = $this->completedEventWithRevenue();

        Sanctum::actingAs($organizer);

        $this->postJson("/api/v1/organizer/events/{$event->id}/payout", $this->bankPayload())
            ->assertCreated()
            ->assertJsonPath('payout.gross_amount', 1_000_000)
            ->assertJsonPath('payout.platform_fee', 50_000)
            ->assertJsonPath('payout.net_amount', 950_000)
            ->assertJsonPath('payout.status', 'pending');

        $this->assertDatabaseHas('payouts', [
            'event_id' => $event->id,
            'organizer_id' => $organizer->id,
            'status' => 'pending',
        ]);
    }

    public function test_payout_only_calculates_paid_orders(): void
    {
        [$organizer, $event] = $this->completedEventWithRevenue(1_000_000);
        $category = $event->ticketCategories()->first();

        $pendingOrder = Order::factory()->create([
            'status' => OrderStatus::Pending->value,
            'total_amount' => 5_000_000,
            'grand_total' => 5_000_000,
        ]);
        OrderItem::create([
            'order_id' => $pendingOrder->id,
            'ticket_category_id' => $category->id,
            'quantity' => 1,
            'price_per_unit' => 5_000_000,
            'subtotal' => 5_000_000,
        ]);

        Sanctum::actingAs($organizer);

        $this->postJson("/api/v1/organizer/events/{$event->id}/payout", $this->bankPayload())
            ->assertCreated()
            ->assertJsonPath('payout.gross_amount', 1_000_000);
    }

    public function test_payout_is_rejected_when_event_is_not_completed(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

        Sanctum::actingAs($organizer);

        $this->postJson("/api/v1/organizer/events/{$event->id}/payout", $this->bankPayload())
            ->assertStatus(422);

        $this->assertDatabaseCount('payouts', 0);
    }

    public function test_past_published_event_is_completed_lazily_on_payout(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create([
            'organizer_id' => $organizer->id,
            'event_date' => now()->subDay()->toDateString(),
        ]);
        $category = TicketCategory::factory()->create(['event_id' => $event->id, 'price' => 500_000]);
        $order = Order::factory()->paid()->create([
            'total_amount' => 500_000,
            'grand_total' => 500_000,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'ticket_category_id' => $category->id,
            'quantity' => 1,
            'price_per_unit' => 500_000,
            'subtotal' => 500_000,
        ]);

        Sanctum::actingAs($organizer);

        $this->postJson("/api/v1/organizer/events/{$event->id}/payout", $this->bankPayload())
            ->assertCreated()
            ->assertJsonPath('payout.gross_amount', 500_000);

        $this->assertSame('completed', $event->fresh()->status);
    }

    public function test_organizer_cannot_request_payout_twice(): void
    {
        [$organizer, $event] = $this->completedEventWithRevenue();
        Payout::factory()->create(['event_id' => $event->id, 'organizer_id' => $organizer->id, 'status' => 'pending']);

        Sanctum::actingAs($organizer);

        $this->postJson("/api/v1/organizer/events/{$event->id}/payout", $this->bankPayload())
            ->assertStatus(422);

        $this->assertDatabaseCount('payouts', 1);
    }

    public function test_organizer_cannot_request_payout_for_another_event(): void
    {
        [, $event] = $this->completedEventWithRevenue();
        $intruder = User::factory()->organizer()->create();

        Sanctum::actingAs($intruder);

        $this->postJson("/api/v1/organizer/events/{$event->id}/payout", $this->bankPayload())
            ->assertStatus(422);

        $this->assertDatabaseCount('payouts', 0);
    }

    public function test_customer_cannot_request_payout(): void
    {
        [, $event] = $this->completedEventWithRevenue();

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/organizer/events/{$event->id}/payout", $this->bankPayload())
            ->assertForbidden();
    }

    public function test_admin_can_list_and_approve_payout(): void
    {
        $admin = User::factory()->admin()->create();
        [$organizer, $event] = $this->completedEventWithRevenue();
        $payout = Payout::factory()->create(['event_id' => $event->id, 'organizer_id' => $organizer->id]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/payouts')
            ->assertOk()
            ->assertJsonCount(1, 'payouts')
            ->assertJsonPath('payouts.0.id', $payout->id);

        $this->postJson("/api/v1/admin/payouts/{$payout->id}/approve")
            ->assertOk()
            ->assertJsonPath('approved', true)
            ->assertJsonPath('payout.status', 'completed');

        $this->assertDatabaseHas('payouts', [
            'id' => $payout->id,
            'status' => 'completed',
            'processed_by' => $admin->id,
        ]);
        $this->assertNotNull($payout->fresh()->processed_at);
    }

    public function test_payout_is_available_from_event_and_organizer_relationships(): void
    {
        [$organizer, $event] = $this->completedEventWithRevenue();
        $payout = Payout::factory()->create(['event_id' => $event->id, 'organizer_id' => $organizer->id]);

        $this->assertTrue($event->payouts->contains($payout));
        $this->assertTrue($organizer->payouts->contains($payout));
    }

    public function test_admin_cannot_approve_non_pending_payout(): void
    {
        $admin = User::factory()->admin()->create();
        [$organizer, $event] = $this->completedEventWithRevenue();
        $payout = Payout::factory()->completed()->create(['event_id' => $event->id, 'organizer_id' => $organizer->id]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/payouts/{$payout->id}/approve")
            ->assertStatus(422);
    }

    public function test_organizer_cannot_access_admin_payouts(): void
    {
        Sanctum::actingAs(User::factory()->organizer()->create());

        $this->getJson('/api/v1/admin/payouts')->assertForbidden();
    }

    public function test_completed_refunds_are_deducted_from_payout(): void
    {
        [$organizer, $event] = $this->completedEventWithRevenue(1_000_000);
        $order = $event->ticketCategories()->first()->orderItems()->first()->order;
        Refund::factory()->completed()->create(['order_id' => $order->id, 'amount' => 200_000]);

        Sanctum::actingAs($organizer);

        $this->postJson("/api/v1/organizer/events/{$event->id}/payout", $this->bankPayload())
            ->assertCreated()
            ->assertJsonPath('payout.gross_amount', 1_000_000)
            ->assertJsonPath('payout.platform_fee', 50_000)
            ->assertJsonPath('payout.refund_deductions', 200_000)
            ->assertJsonPath('payout.net_amount', 750_000);

        $this->assertDatabaseHas('payouts', [
            'event_id' => $event->id,
            'refund_deductions' => 200_000,
            'net_amount' => 750_000,
        ]);
    }

    public function test_non_completed_refunds_are_not_deducted(): void
    {
        [$organizer, $event] = $this->completedEventWithRevenue(1_000_000);
        $order = $event->ticketCategories()->first()->orderItems()->first()->order;
        Refund::factory()->pending()->create(['order_id' => $order->id, 'amount' => 200_000]);

        Sanctum::actingAs($organizer);

        $this->postJson("/api/v1/organizer/events/{$event->id}/payout", $this->bankPayload())
            ->assertCreated()
            ->assertJsonPath('payout.refund_deductions', 0)
            ->assertJsonPath('payout.net_amount', 950_000);
    }

    public function test_payout_is_rejected_when_net_is_zero(): void
    {
        [$organizer, $event] = $this->completedEventWithRevenue(1_000_000);
        $order = $event->ticketCategories()->first()->orderItems()->first()->order;
        Refund::factory()->completed()->create(['order_id' => $order->id, 'amount' => 2_000_000]);

        Sanctum::actingAs($organizer);

        $this->postJson("/api/v1/organizer/events/{$event->id}/payout", $this->bankPayload())
            ->assertStatus(422);

        $this->assertDatabaseCount('payouts', 0);
    }

    public function test_platform_fee_rate_is_configurable(): void
    {
        [$organizer, $event] = $this->completedEventWithRevenue(1_000_000);

        config(['payout.platform_fee_rate' => 0.10]);

        Sanctum::actingAs($organizer);

        $this->postJson("/api/v1/organizer/events/{$event->id}/payout", $this->bankPayload())
            ->assertCreated()
            ->assertJsonPath('payout.platform_fee', 100_000)
            ->assertJsonPath('payout.net_amount', 900_000);
    }
}
