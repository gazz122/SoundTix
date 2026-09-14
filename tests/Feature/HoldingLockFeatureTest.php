<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HoldingLockFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        return User::factory()->create();
    }

    private function eventWithCategory(int $available = 100): Event
    {
        $event = Event::factory()->published()->create();
        $event->ticketCategories()->create([
            'name' => 'Reguler',
            'price' => 200_000,
            'total_quantity' => $available,
            'available_quantity' => $available,
            'max_per_order' => 5,
        ]);

        return $event;
    }

    private function expiredPendingOrder(User $customer, TicketCategory $category, int $quantity = 3): Order
    {
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'expires_at' => now()->subMinute(),
        ]);
        $order->orderItems()->create([
            'ticket_category_id' => $category->id,
            'quantity' => $quantity,
            'price_per_unit' => $category->price,
            'subtotal' => $category->price * $quantity,
        ]);
        $category->decrement('available_quantity', $quantity);

        return $order;
    }

    public function test_release_expired_order_returns_stock_and_marks_expired(): void
    {
        $event = $this->eventWithCategory();
        $category = $event->ticketCategories->first();
        $this->expiredPendingOrder($this->customer(), $category, 3);

        $this->artisan('orders:release-expired')->assertSuccessful();

        $order = Order::first();
        $this->assertSame(OrderStatus::Expired->value, $order->status);
        $this->assertSame(100, $category->fresh()->available_quantity);
    }

    public function test_pending_order_within_window_is_not_released(): void
    {
        $event = $this->eventWithCategory();
        $category = $event->ticketCategories->first();

        $order = Order::factory()->create(['customer_id' => $this->customer()->id]);
        $order->orderItems()->create([
            'ticket_category_id' => $category->id,
            'quantity' => 2,
            'price_per_unit' => $category->price,
            'subtotal' => $category->price * 2,
        ]);
        $category->decrement('available_quantity', 2);

        $this->artisan('orders:release-expired')->assertSuccessful();

        $this->assertSame(OrderStatus::Pending->value, $order->fresh()->status);
        $this->assertSame(98, $category->fresh()->available_quantity);
    }

    public function test_release_is_idempotent(): void
    {
        $event = $this->eventWithCategory();
        $category = $event->ticketCategories->first();
        $this->expiredPendingOrder($this->customer(), $category, 3);

        $this->artisan('orders:release-expired')->assertSuccessful();
        $this->artisan('orders:release-expired')->assertSuccessful();

        $this->assertSame(100, $category->fresh()->available_quantity);
        $this->assertSame(OrderStatus::Expired->value, Order::first()->status);
    }

    public function test_release_only_touches_pending_orders(): void
    {
        $event = $this->eventWithCategory();
        $category = $event->ticketCategories->first();

        $paid = Order::factory()->paid()->create(['customer_id' => $this->customer()->id]);
        $paid->orderItems()->create([
            'ticket_category_id' => $category->id,
            'quantity' => 1,
            'price_per_unit' => $category->price,
            'subtotal' => $category->price,
        ]);

        $this->artisan('orders:release-expired')->assertSuccessful();

        $this->assertSame(OrderStatus::Paid->value, $paid->fresh()->status);
    }

    public function test_last_remaining_ticket_is_claimed_by_first_customer(): void
    {
        $event = $this->eventWithCategory(1);
        $category = $event->ticketCategories->first();

        $first = $this->customer();
        $this->actingAs($first)
            ->post("/events/{$event->slug}/order", [
                'items' => [['category_id' => $category->id, 'quantity' => 1]],
            ])
            ->assertRedirectContains('/orders/');

        $this->assertSame(1, Order::count());

        $second = $this->customer();
        $this->actingAs($second)
            ->post("/events/{$event->slug}/order", [
                'items' => [['category_id' => $category->id, 'quantity' => 1]],
            ])
            ->assertSessionHasErrors('items.0.quantity');

        $this->assertSame(1, Order::count());
        $this->assertSame(0, $category->fresh()->available_quantity);
    }

    public function test_expired_order_cannot_be_cancelled(): void
    {
        $customer = $this->customer();
        $event = $this->eventWithCategory();
        $category = $event->ticketCategories->first();
        $order = $this->expiredPendingOrder($customer, $category, 2);

        $this->artisan('orders:release-expired')->assertSuccessful();

        $this->actingAs($customer)
            ->post("/orders/{$order->order_code}/cancel")
            ->assertRedirect("/orders/{$order->order_code}");

        $this->assertSame(OrderStatus::Expired->value, $order->fresh()->status);
        $this->assertSame(100, $category->fresh()->available_quantity);
    }
}
