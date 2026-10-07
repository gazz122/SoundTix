<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerOrderingFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        return User::factory()->create();
    }

    private function eventWithCategory(array $attributes = []): Event
    {
        $event = Event::factory()->create([
            'status' => 'published',
            ...$attributes,
        ]);
        $event->ticketCategories()->create([
            'name' => 'Reguler',
            'description' => 'Tiket umum',
            'price' => 200_000,
            'total_quantity' => 100,
            'available_quantity' => 100,
            'max_per_order' => 5,
        ]);

        return $event;
    }

    public function test_guest_cannot_create_order(): void
    {
        $event = $this->eventWithCategory();

        $this->post("/events/{$event->slug}/order", [
            'items' => [['category_id' => $event->ticketCategories->first()->id, 'quantity' => 1]],
        ])->assertRedirect('/login');
    }

    public function test_customer_can_create_order_and_stock_is_reduced(): void
    {
        $event = $this->eventWithCategory();
        $category = $event->ticketCategories->first();

        $this->actingAs($this->customer())
            ->post("/events/{$event->slug}/order", [
                'items' => [['category_id' => $category->id, 'quantity' => 2]],
            ])
            ->assertRedirectContains('/orders/');

        $order = Order::first();
        $this->assertNotNull($order);
        $this->assertMatchesRegularExpression('/^STX-\d{8}-[A-Z0-9]{4}$/', $order->order_code);
        $this->assertSame(OrderStatus::Pending->value, $order->status);
        $this->assertEqualsWithDelta(now()->addMinutes(15)->timestamp, $order->expires_at->timestamp, 5);
        $this->assertSame('400000.00', $order->total_amount);
        $this->assertSame('400000.00', $order->grand_total);
        $this->assertSame(0.0, (float) $order->admin_fee);

        $this->assertSame(98, $category->fresh()->available_quantity);
        $this->assertSame(1, $order->orderItems()->count());
        $this->assertSame(2, $order->orderItems()->first()->quantity);
    }

    public function test_order_code_is_unique(): void
    {
        $event = $this->eventWithCategory();
        $category = $event->ticketCategories->first();

        $this->actingAs($this->customer())
            ->post("/events/{$event->slug}/order", [
                'items' => [['category_id' => $category->id, 'quantity' => 1]],
            ]);

        $this->assertSame(1, Order::count());
    }

    public function test_total_quantity_capped_at_five(): void
    {
        $event = Event::factory()->published()->create();
        $first = $event->ticketCategories()->create([
            'name' => 'Reguler',
            'price' => 100_000,
            'total_quantity' => 50,
            'available_quantity' => 50,
            'max_per_order' => 5,
        ]);
        $second = $event->ticketCategories()->create([
            'name' => 'VIP',
            'price' => 300_000,
            'total_quantity' => 20,
            'available_quantity' => 20,
            'max_per_order' => 5,
        ]);

        $this->actingAs($this->customer())
            ->post("/events/{$event->slug}/order", [
                'items' => [
                    ['category_id' => $first->id, 'quantity' => 3],
                    ['category_id' => $second->id, 'quantity' => 3],
                ],
            ])
            ->assertSessionHasErrors('items');

        $this->assertSame(0, Order::count());
        $this->assertSame(50, $first->fresh()->available_quantity);
    }

    public function test_quantity_over_max_per_order_is_rejected(): void
    {
        $event = $this->eventWithCategory();
        $category = $event->ticketCategories->first();
        $category->update(['max_per_order' => 2]);

        $this->actingAs($this->customer())
            ->post("/events/{$event->slug}/order", [
                'items' => [['category_id' => $category->id, 'quantity' => 3]],
            ])
            ->assertSessionHasErrors('items.0.quantity');

        $this->assertSame(0, Order::count());
        $this->assertSame(100, $category->fresh()->available_quantity);
    }

    public function test_quantity_exceeding_available_stock_is_rejected(): void
    {
        $event = $this->eventWithCategory();
        $category = $event->ticketCategories->first();
        $category->update(['available_quantity' => 4, 'total_quantity' => 4]);

        $this->actingAs($this->customer())
            ->post("/events/{$event->slug}/order", [
                'items' => [['category_id' => $category->id, 'quantity' => 5]],
            ])
            ->assertSessionHasErrors('items.0.quantity');

        $this->assertSame(0, Order::count());
    }

    public function test_empty_items_is_rejected(): void
    {
        $event = $this->eventWithCategory();

        $this->actingAs($this->customer())
            ->post("/events/{$event->slug}/order", ['items' => []])
            ->assertSessionHasErrors('items');

        $this->assertSame(0, Order::count());
    }

    public function test_category_must_belong_to_event(): void
    {
        $event = $this->eventWithCategory();
        $foreign = $this->eventWithCategory();
        $foreignCategory = $foreign->ticketCategories->first();

        $this->actingAs($this->customer())
            ->post("/events/{$event->slug}/order", [
                'items' => [['category_id' => $foreignCategory->id, 'quantity' => 1]],
            ])
            ->assertSessionHasErrors('items.invalid');

        $this->assertSame(0, Order::count());
    }

    public function test_draft_event_cannot_be_ordered(): void
    {
        $event = Event::factory()->draft()->create();
        $category = $event->ticketCategories()->create([
            'name' => 'Reguler',
            'price' => 100_000,
            'total_quantity' => 10,
            'available_quantity' => 10,
            'max_per_order' => 5,
        ]);

        $this->actingAs($this->customer())
            ->post("/events/{$event->slug}/order", [
                'items' => [['category_id' => $category->id, 'quantity' => 1]],
            ])
            ->assertNotFound();

        $this->assertSame(0, Order::count());
    }

    public function test_non_customer_role_cannot_access_orders(): void
    {
        $principal = User::factory()->organizer()->create();

        $this->actingAs($principal)
            ->get('/orders')
            ->assertForbidden();

        $this->actingAs($principal)
            ->post('/events/whatever/order', [
                'items' => [['category_id' => 1, 'quantity' => 1]],
            ])
            ->assertForbidden();
    }

    public function test_customer_sees_only_their_orders(): void
    {
        $customer = $this->customer();
        $other = $this->customer();

        $event = $this->eventWithCategory();
        Order::factory()->create(['customer_id' => $customer->id]);
        Order::factory()->create(['customer_id' => $other->id]);

        $this->actingAs($customer)
            ->get('/orders')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Orders/Index')
                    ->has('orders', 1),
            );
    }

    public function test_multi_day_ticket_shows_its_selected_day_and_date(): void
    {
        $event = Event::factory()->published()->create([
            'event_date' => '2026-10-07',
            'duration_type' => 'multi_day',
            'total_days' => 3,
        ]);
        $event->eventDays()->createMany([
            ['day_number' => 1, 'date' => '2026-10-07'],
            ['day_number' => 2, 'date' => '2026-10-08'],
            [
                'day_number' => 3,
                'date' => '2026-10-09',
                'open_gate' => '2026-10-09 09:00:00',
                'close_gate' => '2026-10-09 15:00:00',
            ],
        ]);
        $day = $event->eventDays()->where('day_number', 3)->firstOrFail();
        $category = $event->ticketCategories()->create([
            'name' => 'Reguler',
            'price' => 200_000,
            'total_quantity' => 20,
            'available_quantity' => 20,
            'max_per_order' => 5,
            'access_type' => 'SINGLE_DAY',
            'event_day_id' => $day->id,
        ]);

        $this->get("/events/{$event->slug}")
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Events/Show')
                    ->where('event.event_days.2.date', '2026-10-09')
                    ->where('event.event_days.2.open_gate', '2026-10-09 16:00')
                    ->where('event.event_days.2.close_gate', '2026-10-09 22:00')
                    ->where('event.ticket_categories.0.access_type', 'SINGLE_DAY')
                    ->where('event.ticket_categories.0.event_day.day_number', 3)
                    ->where('event.ticket_categories.0.event_day.date', '2026-10-09'),
            );
    }

    public function test_multi_day_order_uses_selected_day_date_in_order_details(): void
    {
        $customer = $this->customer();
        $event = Event::factory()->published()->create([
            'event_date' => '2026-10-07',
            'duration_type' => 'multi_day',
            'total_days' => 3,
        ]);
        $event->eventDays()->createMany([
            ['day_number' => 1, 'date' => '2026-10-07'],
            ['day_number' => 2, 'date' => '2026-10-08'],
            ['day_number' => 3, 'date' => '2026-10-09'],
        ]);
        $day = $event->eventDays()->where('day_number', 3)->firstOrFail();
        $category = $event->ticketCategories()->create([
            'name' => 'Reguler',
            'price' => 200_000,
            'total_quantity' => 20,
            'available_quantity' => 20,
            'max_per_order' => 5,
            'access_type' => 'SINGLE_DAY',
            'event_day_id' => $day->id,
        ]);

        $this->actingAs($customer)
            ->post("/events/{$event->slug}/order", [
                'items' => [['category_id' => $category->id, 'quantity' => 1]],
            ])
            ->assertRedirectContains('/orders/');

        $order = Order::query()->firstOrFail();

        $this->actingAs($customer)
            ->get("/orders/{$order->order_code}")
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Orders/Show')
                    ->where('order.items.0.event.event_date', '2026-10-09')
                    ->where('order.items.0.event.ticket_day_label', 'Day 3'),
            );
    }

    public function test_order_show_renders_items_and_total(): void
    {
        $customer = $this->customer();
        $event = $this->eventWithCategory();
        $category = $event->ticketCategories->first();

        $order = Order::factory()->create(['customer_id' => $customer->id, 'grand_total' => 400_000]);
        $order->orderItems()->create([
            'ticket_category_id' => $category->id,
            'quantity' => 2,
            'price_per_unit' => 200_000,
            'subtotal' => 400_000,
        ]);

        $this->actingAs($customer)
            ->get("/orders/{$order->order_code}")
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Orders/Show')
                    ->where('order.order_code', $order->order_code)
                    ->has('order.items', 1)
                    ->where('order.items.0.category', 'Reguler')
                    ->where('order.grand_total', 400_000)
                    ->where('order.items.0.event.title', $event->title),
            );
    }

    public function test_order_of_another_customer_returns_404(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($this->customer())
            ->get("/orders/{$order->order_code}")
            ->assertNotFound();
    }

    public function test_pending_order_can_be_cancelled_and_stock_is_returned(): void
    {
        $customer = $this->customer();
        $event = $this->eventWithCategory();
        $category = $event->ticketCategories->first();

        $order = Order::factory()->create(['customer_id' => $customer->id]);
        $order->orderItems()->create([
            'ticket_category_id' => $category->id,
            'quantity' => 3,
            'price_per_unit' => 200_000,
            'subtotal' => 600_000,
        ]);
        $category->decrement('available_quantity', 3);

        $this->actingAs($customer)
            ->post("/orders/{$order->order_code}/cancel")
            ->assertRedirect("/orders/{$order->order_code}");

        $this->assertSame(OrderStatus::Cancelled->value, $order->fresh()->status);
        $this->assertSame(100, $category->fresh()->available_quantity);
    }

    public function test_paid_order_cannot_be_cancelled(): void
    {
        $customer = $this->customer();
        $event = $this->eventWithCategory();
        $category = $event->ticketCategories->first();

        $order = Order::factory()->paid()->create(['customer_id' => $customer->id]);
        $order->orderItems()->create([
            'ticket_category_id' => $category->id,
            'quantity' => 1,
            'price_per_unit' => 200_000,
            'subtotal' => 200_000,
        ]);

        $this->actingAs($customer)
            ->post("/orders/{$order->order_code}/cancel")
            ->assertRedirect("/orders/{$order->order_code}");

        $this->assertSame(OrderStatus::Paid->value, $order->fresh()->status);
    }

    public function test_cancelling_order_of_another_customer_returns_404(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($this->customer())
            ->post("/orders/{$order->order_code}/cancel")
            ->assertNotFound();
    }
}
