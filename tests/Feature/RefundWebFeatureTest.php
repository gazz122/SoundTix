<?php

namespace Tests\Feature;

use App\Enums\RefundStatus;
use App\Enums\TicketStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundWebFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function paidEvent(int $price = 200_000, int $quantity = 2): array
    {
        $event = Event::factory()->published()->create();
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

        return [$customer, $order];
    }

    public function test_customer_can_request_refund_on_paid_order(): void
    {
        [$customer, $order] = $this->paidEvent();

        $this->actingAs($customer)
            ->post("/orders/{$order->order_code}/refund", ['reason' => 'Tidak bisa hadir.'])
            ->assertRedirect("/orders/{$order->order_code}")
            ->assertSessionHas('success');

        $this->assertDatabaseHas('refunds', [
            'order_id' => $order->id,
            'amount' => '400000.00',
            'status' => RefundStatus::Pending->value,
            'reason' => 'Tidak bisa hadir.',
        ]);
    }

    public function test_customer_cannot_request_refund_on_non_paid_order(): void
    {
        $customer = User::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 100_000,
            'grand_total' => 100_000,
        ]);

        $this->actingAs($customer)
            ->post("/orders/{$order->order_code}/refund", ['reason' => 'Tidak jadi.'])
            ->assertRedirect("/orders/{$order->order_code}")
            ->assertSessionHas('error');

        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_customer_cannot_request_duplicate_refund(): void
    {
        [$customer, $order] = $this->paidEvent();
        Refund::factory()->pending()->create(['order_id' => $order->id]);

        $this->actingAs($customer)
            ->post("/orders/{$order->order_code}/refund", ['reason' => 'Lagi-lagi.'])
            ->assertRedirect("/orders/{$order->order_code}")
            ->assertSessionHas('error');

        $this->assertDatabaseCount('refunds', 1);
    }

    public function test_customer_cannot_refund_another_users_order(): void
    {
        [$customer, $order] = $this->paidEvent();
        $other = User::factory()->create();

        $this->actingAs($other)
            ->post("/orders/{$order->order_code}/refund", ['reason' => 'Curi.'])
            ->assertNotFound();

        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_refund_requires_reason(): void
    {
        [$customer, $order] = $this->paidEvent();

        $this->actingAs($customer)
            ->post("/orders/{$order->order_code}/refund", ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_admin_can_view_refunds_page(): void
    {
        [$customer, $order] = $this->paidEvent();
        $refund = Refund::factory()->pending()->create([
            'order_id' => $order->id,
            'amount' => 400_000,
            'reason' => 'Tidak bisa hadir.',
        ]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/refunds')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Refunds/Index')
                ->has('refunds', 1)
                ->where('refunds.0.id', $refund->id)
                ->where('refunds.0.status', 'pending')
                ->where('refunds.0.order_code', $order->order_code));
    }

    public function test_admin_approve_completes_refund_and_cancels_tickets(): void
    {
        [$customer, $order] = $this->paidEvent(quantity: 2);
        $refund = Refund::factory()->pending()->create([
            'order_id' => $order->id,
            'amount' => 400_000,
        ]);
        $admin = User::factory()->admin()->create();
        $item = $order->orderItems()->first();
        $category = $item->ticketCategory;
        $before = $category->fresh()->available_quantity;

        $this->actingAs($admin)
            ->post("/admin/refunds/{$refund->id}/approve")
            ->assertRedirect(route('admin.refunds.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('refunds', [
            'id' => $refund->id,
            'status' => RefundStatus::Completed->value,
        ]);
        $this->assertDatabaseMissing('tickets', [
            'order_item_id' => $item->id,
            'status' => TicketStatus::Active->value,
        ]);
        $this->assertSame($before + 2, $category->fresh()->available_quantity);
        $this->assertSame(2, Ticket::where('order_item_id', $item->id)->where('status', TicketStatus::Cancelled->value)->count());
    }

    public function test_admin_cannot_approve_already_completed_refund(): void
    {
        [$customer, $order] = $this->paidEvent();
        $refund = Refund::factory()->completed()->create([
            'order_id' => $order->id,
            'amount' => 400_000,
        ]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post("/admin/refunds/{$refund->id}/approve")
            ->assertRedirect(route('admin.refunds.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('refunds', [
            'id' => $refund->id,
            'status' => RefundStatus::Completed->value,
        ]);
    }

    public function test_customer_cannot_access_admin_refunds_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/refunds')
            ->assertForbidden();
    }
}
