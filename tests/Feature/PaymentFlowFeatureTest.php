<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaymentFlowFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        return User::factory()->create();
    }

    private function pendingOrderWithItem(User $customer, int $quantity = 2, int $available = 100): Order
    {
        $event = Event::factory()->published()->create();
        $category = $event->ticketCategories()->create([
            'name' => 'Reguler',
            'price' => 200_000,
            'total_quantity' => $available,
            'available_quantity' => $available,
            'max_per_order' => 5,
        ]);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'expires_at' => now()->addMinutes(15),
            'total_amount' => 200_000 * $quantity,
            'admin_fee' => 0,
            'grand_total' => 200_000 * $quantity,
        ]);
        $order->orderItems()->create([
            'ticket_category_id' => $category->id,
            'quantity' => $quantity,
            'price_per_unit' => 200_000,
            'subtotal' => 200_000 * $quantity,
        ]);
        $category->decrement('available_quantity', $quantity);

        return $order;
    }

    private function pay(Order $order, string $method = 'bank_transfer')
    {
        return $this->post("/orders/{$order->order_code}/pay", ['method' => $method]);
    }

    public function test_customer_can_create_pending_payment(): void
    {
        $customer = $this->customer();
        $order = $this->pendingOrderWithItem($customer);

        $this->actingAs($customer)
            ->post("/orders/{$order->order_code}/pay", ['method' => 'bank_transfer'])
            ->assertRedirect("/orders/{$order->order_code}");

        $payment = $order->payments()->first();
        $this->assertNotNull($payment);
        $this->assertStringStartsWith('MOCK-', $payment->transaction_id);
        $this->assertSame(PaymentType::BankTransfer->value, $payment->payment_type);
        $this->assertSame(PaymentStatus::Pending->value, $payment->payment_status);
        $this->assertSame('400000.00', $payment->gross_amount);
        $this->assertSame(OrderStatus::Pending->value, $order->fresh()->status);
    }

    public function test_customer_can_pay_with_qris(): void
    {
        $customer = $this->customer();
        $order = $this->pendingOrderWithItem($customer);

        $this->actingAs($customer)->post("/orders/{$order->order_code}/pay", ['method' => 'qris'])->assertRedirect();

        $this->assertSame(PaymentType::Qris->value, $order->payments()->first()->payment_type);
    }

    public function test_pay_requires_valid_method(): void
    {
        $customer = $this->customer();
        $order = $this->pendingOrderWithItem($customer);

        $this->actingAs($customer)
            ->post("/orders/{$order->order_code}/pay", ['method' => 'cash'])
            ->assertSessionHasErrors('method');

        $this->assertSame(0, $order->payments()->count());
    }

    public function test_pay_rejected_for_non_pending_order(): void
    {
        $customer = $this->customer();
        $order = $this->pendingOrderWithItem($customer);
        $order->update(['status' => OrderStatus::Cancelled->value]);

        $this->actingAs($customer)
            ->post("/orders/{$order->order_code}/pay", ['method' => 'qris'])
            ->assertRedirect("/orders/{$order->order_code}")
            ->assertSessionHas('error');

        $this->assertSame(0, $order->payments()->count());
    }

    public function test_settle_marks_order_paid_and_issues_tickets(): void
    {
        $customer = $this->customer();
        $order = $this->pendingOrderWithItem($customer, quantity: 3);
        $category = $order->orderItems->first()->ticketCategory;
        $this->actingAs($customer)->pay($order);

        $payment = $order->payments()->first();

        $this->actingAs($customer)
            ->post("/payments/{$payment->id}/settle")
            ->assertRedirect("/orders/{$order->order_code}")
            ->assertSessionHas('success');

        $this->assertSame(PaymentStatus::Settlement->value, $payment->fresh()->payment_status);
        $this->assertSame(OrderStatus::Paid->value, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->paid_at);
        $this->assertSame(97, $category->fresh()->available_quantity);

        $tickets = Ticket::whereIn('order_item_id', $order->orderItems->pluck('id'))->get();
        $this->assertCount(3, $tickets);
        $this->assertCount(3, $tickets->pluck('ticket_code')->unique());
        $this->assertSame(3, $tickets->pluck('qr_payload')->unique()->count());

        $verifier = new TicketVerifier;

        foreach ($tickets as $ticket) {
            $parts = explode('.', $ticket->qr_payload);
            $this->assertCount(4, $parts);
            $this->assertSame($ticket->ticket_code, $parts[0]);
            $this->assertSame((string) $order->id, $parts[1]);
            $this->assertTrue($verifier->isSignatureValid($ticket->qr_payload));
        }
    }

    public function test_tampered_or_malformed_qr_payload_is_rejected(): void
    {
        $customer = $this->customer();
        $order = $this->pendingOrderWithItem($customer, quantity: 1);
        $this->actingAs($customer)->pay($order);
        $this->actingAs($customer)->post("/payments/{$order->payments()->first()->id}/settle");

        $ticket = Ticket::firstOrFail();
        [$ticketCode, $orderId, $orderItemId, $hmac] = explode('.', $ticket->qr_payload);

        $tampered = "AAAAFAKECODE.{$orderId}.{$orderItemId}.{$hmac}";

        $this->assertNotSame($ticketCode, $tampered);
        $this->assertFalse((new TicketVerifier)->isSignatureValid($tampered));
        $this->assertFalse((new TicketVerifier)->isSignatureValid('foo.bar'));
        $this->assertFalse((new TicketVerifier)->isSignatureValid(''));
    }

    public function test_paid_order_page_lists_e_tickets(): void
    {
        $customer = $this->customer();
        $order = $this->pendingOrderWithItem($customer, quantity: 2);
        $this->actingAs($customer)->pay($order);
        $this->actingAs($customer)->post("/payments/{$order->payments()->first()->id}/settle");

        $ticketCodes = Ticket::orderBy('id')->pluck('ticket_code');

        $this->actingAs($customer)
            ->get("/orders/{$order->order_code}")
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('order.tickets', 2)
                    ->where('order.tickets.0.ticket_code', $ticketCodes[0])
                    ->where('order.tickets.1.ticket_code', $ticketCodes[1])
                    ->where('order.tickets.0.status', 'active'),
            );
    }

    public function test_pending_order_page_exposes_no_tickets(): void
    {
        $customer = $this->customer();
        $order = $this->pendingOrderWithItem($customer, quantity: 2);

        $this->actingAs($customer)
            ->get("/orders/{$order->order_code}")
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page->where('order.tickets', []),
            );
    }

    public function test_settle_cannot_be_replayed(): void
    {
        $customer = $this->customer();
        $order = $this->pendingOrderWithItem($customer, quantity: 2);
        $this->actingAs($customer)->pay($order);
        $payment = $order->payments()->first();

        $this->actingAs($customer)
            ->post("/payments/{$payment->id}/settle")
            ->assertRedirect();

        $this->actingAs($customer)
            ->post("/payments/{$payment->id}/settle")
            ->assertRedirect("/orders/{$order->order_code}")
            ->assertSessionHas('error');

        $this->assertSame(2, Ticket::count());
    }

    public function test_expire_releases_stock_and_marks_order_expired(): void
    {
        $customer = $this->customer();
        $order = $this->pendingOrderWithItem($customer, quantity: 4);
        $category = $order->orderItems->first()->ticketCategory;
        $this->actingAs($customer)->pay($order);
        $payment = $order->payments()->first();

        $this->actingAs($customer)
            ->post("/payments/{$payment->id}/expire")
            ->assertRedirect("/orders/{$order->order_code}")
            ->assertSessionHas('success');

        $this->assertSame(PaymentStatus::Expire->value, $payment->fresh()->payment_status);
        $this->assertSame(OrderStatus::Expired->value, $order->fresh()->status);
        $this->assertSame(100, $category->fresh()->available_quantity);
        $this->assertSame(0, Ticket::count());
    }

    public function test_expire_cannot_double_release(): void
    {
        $customer = $this->customer();
        $order = $this->pendingOrderWithItem($customer, quantity: 4);
        $category = $order->orderItems->first()->ticketCategory;
        $this->actingAs($customer)->pay($order);
        $payment = $order->payments()->first();

        $this->actingAs($customer)
            ->post("/payments/{$payment->id}/expire")
            ->assertRedirect();

        $this->actingAs($customer)
            ->post("/payments/{$payment->id}/expire")
            ->assertRedirect("/orders/{$order->order_code}");

        $this->assertSame(100, $category->fresh()->available_quantity);
    }

    public function test_guest_cannot_pay(): void
    {
        $customer = $this->customer();
        $order = $this->pendingOrderWithItem($customer);

        $this->post("/orders/{$order->order_code}/pay", ['method' => 'qris'])->assertRedirect('/login');
    }

    public function test_payment_of_another_customer_returns_404(): void
    {
        $owner = $this->customer();
        $order = $this->pendingOrderWithItem($owner);
        $this->actingAs($owner)->pay($order);
        $payment = $order->payments()->first();

        $this->actingAs($this->customer())
            ->post("/payments/{$payment->id}/settle")
            ->assertNotFound();
    }

    public function test_non_customer_role_cannot_pay(): void
    {
        $customer = $this->customer();
        $order = $this->pendingOrderWithItem($customer);

        $this->actingAs(User::factory()->organizer()->create())
            ->post("/orders/{$order->order_code}/pay", ['method' => 'qris'])
            ->assertForbidden();
    }
}
