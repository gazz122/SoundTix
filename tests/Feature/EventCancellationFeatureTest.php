<?php

namespace Tests\Feature;

use App\Enums\RefundStatus;
use App\Enums\TicketStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventCancellationFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizer_can_request_event_cancellation(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($organizer)
            ->post("/organizer/events/{$event->id}/cancel", ['reason' => 'Artis berhalangan hadir.'])
            ->assertRedirect(route('organizer.events.show', $event->id))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'status' => 'pending_cancellation',
            'cancellation_reason' => 'Artis berhalangan hadir.',
        ]);

        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame('event_cancellation_requested', $admin->notifications()->first()->data['type']);
    }

    public function test_organizer_cannot_cancel_non_published_event(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->pending()->create(['organizer_id' => $organizer->id]);

        $this->actingAs($organizer)
            ->post("/organizer/events/{$event->id}/cancel", ['reason' => 'Alasan.'])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'pending']);
    }

    public function test_organizer_cannot_cancel_past_event(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create([
            'organizer_id' => $organizer->id,
            'event_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($organizer)
            ->post("/organizer/events/{$event->id}/cancel", ['reason' => 'Alasan.'])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'published']);
    }

    public function test_cancellation_requires_reason(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

        $this->actingAs($organizer)
            ->post("/organizer/events/{$event->id}/cancel", ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'published']);
    }

    public function test_organizer_cannot_cancel_another_organizer_event(): void
    {
        $event = Event::factory()->published()->create();
        $intruder = User::factory()->organizer()->create();

        $this->actingAs($intruder)
            ->post("/organizer/events/{$event->id}/cancel", ['reason' => 'Alasan.'])
            ->assertNotFound();
    }

    public function test_organizer_can_view_event_show_page_with_cancel_action(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

        $this->actingAs($organizer)->get("/organizer/events/{$event->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Organizer/Events/Show')
                ->where('event.id', $event->id)
                ->where('event.cancellable', true));
    }

    public function test_admin_approve_cancellation_refunds_all_paid_orders(): void
    {
        [$organizer, $event, $customer, $order, $category, $item] = $this->paidOrderForEvent();
        $event->update([
            'status' => 'pending_cancellation',
            'cancellation_reason' => 'Artis berhalangan hadir.',
        ]);
        $admin = User::factory()->admin()->create();
        $stockBefore = $category->fresh()->available_quantity;

        $this->actingAs($admin)
            ->post("/admin/refunds/events/{$event->id}/approve-cancellation")
            ->assertRedirect(route('admin.refunds.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('refunds', [
            'order_id' => $order->id,
            'amount' => 400_000,
            'reason' => 'Event dibatalkan: Artis berhalangan hadir.',
            'status' => RefundStatus::Completed->value,
        ]);
        $this->assertDatabaseHas('tickets', [
            'order_item_id' => $item->id,
            'status' => TicketStatus::Cancelled->value,
        ]);
        $this->assertSame($stockBefore + 2, $category->fresh()->available_quantity);
        $this->assertSame(1, $customer->notifications()->count());
    }

    public function test_admin_can_reject_cancellation_request(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create([
            'organizer_id' => $organizer->id,
            'status' => 'pending_cancellation',
            'cancellation_reason' => 'Artis berhalangan hadir.',
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->post("/admin/refunds/events/{$event->id}/reject-cancellation")
            ->assertRedirect(route('admin.refunds.index'));

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'status' => 'published',
            'cancellation_reason' => null,
        ]);
    }

    public function test_admin_cannot_approve_non_pending_cancellation(): void
    {
        $event = Event::factory()->published()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post("/admin/refunds/events/{$event->id}/approve-cancellation")
            ->assertNotFound();
    }

    public function test_organizer_can_view_refunds_page(): void
    {
        [$organizer, $event, , $order] = $this->paidOrderForEvent();
        $refund = Refund::factory()->pending()->create([
            'order_id' => $order->id,
            'amount' => 400_000,
            'reason' => 'Tidak jadi hadir.',
        ]);

        $this->actingAs($organizer)->get('/organizer/refunds')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Organizer/Refunds/Index')
                ->has('refunds', 1)
                ->where('refunds.0.id', $refund->id)
                ->where('refunds.0.order_code', $order->order_code));
    }

    public function test_customer_cannot_access_organizer_refund_pages(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/organizer/refunds')
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: Event, 2: User, 3: Order, 4: TicketCategory, 5: OrderItem}
     */
    private function paidOrderForEvent(int $price = 200_000, int $quantity = 2): array
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $category = TicketCategory::factory()->create([
            'event_id' => $event->id,
            'price' => $price,
            'total_quantity' => 100,
            'available_quantity' => 100,
        ]);
        $customer = User::factory()->create();
        $order = Order::factory()->paid()->create([
            'customer_id' => $customer->id,
            'total_amount' => $price * $quantity,
            'grand_total' => $price * $quantity,
        ]);
        $item = $order->orderItems()->create([
            'ticket_category_id' => $category->id,
            'quantity' => $quantity,
            'price_per_unit' => $price,
            'subtotal' => $price * $quantity,
        ]);

        for ($i = 0; $i < $quantity; $i++) {
            Ticket::create([
                'order_item_id' => $item->id,
                'ticket_code' => "TICKET-{$item->id}-{$i}",
                'qr_payload' => "TICKET-{$item->id}-{$i}.{$order->id}.{$item->id}.hmac",
                'status' => TicketStatus::Active->value,
            ]);
        }

        $category->decrement('available_quantity', $quantity);

        return [$organizer, $event, $customer, $order, $category, $item];
    }
}
