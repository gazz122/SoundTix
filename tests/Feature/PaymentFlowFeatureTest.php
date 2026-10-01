<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Mail\ETicketEmail;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use App\Services\PaymentGateway\MidtransPaymentDriver;
use App\Services\PaymentGateway\PaymentGatewayInterface;
use App\Services\TicketVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
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

    private function pay(Order $order, string $method = 'bank_transfer', string $bank = 'bca')
    {
        return $this->post("/orders/{$order->order_code}/pay", ['method' => $method, 'bank' => $bank]);
    }

    private function midtransPayload(Order $order, string $transactionStatus, string $fraudStatus = 'accept'): array
    {
        $serverKey = 'test-server-key';
        config(['services.midtrans.server_key' => $serverKey]);

        $grossAmount = (string) $order->grand_total;
        $payload = [
            'order_id' => $order->order_code,
            'status_code' => '200',
            'gross_amount' => $grossAmount,
            'transaction_status' => $transactionStatus,
            'fraud_status' => $fraudStatus,
        ];
        $payload['signature_key'] = hash('sha512', $payload['order_id'].$payload['status_code'].$grossAmount.$serverKey);

        return $payload;
    }

    public function test_midtrans_webhook_rejects_invalid_signature(): void
    {
        $customer = $this->customer();
        $order = $this->pendingOrderWithItem($customer);
        $this->actingAs($customer)->pay($order);
        $payload = $this->midtransPayload($order, 'settlement');
        $payload['signature_key'] = 'invalid';

        $this->postJson('/api/midtrans/webhook', $payload)->assertForbidden();

        $this->assertSame(PaymentStatus::Pending->value, $order->payments()->first()->fresh()->payment_status);
        $this->assertSame(OrderStatus::Pending->value, $order->fresh()->status);
    }

    public function test_midtrans_webhook_accepts_numeric_gross_amount(): void
    {
        $customer = $this->customer();
        $order = $this->pendingOrderWithItem($customer, quantity: 1);
        $this->actingAs($customer)->pay($order);
        $payload = $this->midtransPayload($order, 'settlement');
        $payload['gross_amount'] = (int) $order->grand_total;
        $payload['signature_key'] = hash(
            'sha512',
            $order->order_code.'200'.(string) $payload['gross_amount'].'test-server-key',
        );

        $this->postJson('/api/midtrans/webhook', $payload)->assertOk();

        $this->assertSame(PaymentStatus::Settlement->value, $order->payments()->first()->fresh()->payment_status);
        $this->assertSame(OrderStatus::Paid->value, $order->fresh()->status);
    }

    public function test_midtrans_snap_request_includes_notification_url_override(): void
    {
        $customer = $this->customer();
        $order = $this->pendingOrderWithItem($customer, quantity: 1);
        $webhookUrl = 'https://soundtix.ngrok-free.app/api/midtrans/webhook';
        config([
            'services.midtrans.server_key' => 'sandbox-server-key',
            'services.midtrans.is_production' => false,
            'services.midtrans.webhook_url' => $webhookUrl,
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'snap-token',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token',
            ], 201),
        ]);

        $payment = app(MidtransPaymentDriver::class)->createPayment($order, PaymentType::BankTransfer, 'bca');
        $returnUrl = route('orders.show', $order->order_code);

        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://app.sandbox.midtrans.com/snap/v1/transactions'
            && $request->data()['override_notification_urls'] === [$webhookUrl]
            && $request->data()['callbacks'] === [
                'finish' => $returnUrl,
                'unfinish' => $returnUrl,
                'error' => $returnUrl,
            ]
            && $request->data()['transaction_details']['order_id'] === $payment->transaction_id);
        $this->assertSame(PaymentStatus::Pending->value, $payment->payment_status);
        $this->assertSame('snap-token', $payment->raw_response['token']);
    }

    public function test_inertia_payment_request_redirects_to_midtrans_snap(): void
    {
        $customer = $this->customer();
        $order = $this->pendingOrderWithItem($customer, quantity: 1);
        $redirectUrl = 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token';
        config([
            'services.midtrans.server_key' => 'sandbox-server-key',
            'services.midtrans.is_production' => false,
            'services.midtrans.webhook_url' => 'https://soundtix.ngrok-free.app/api/midtrans/webhook',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'snap-token',
                'redirect_url' => $redirectUrl,
            ], 201),
        ]);
        $this->app->bind(PaymentGatewayInterface::class, MidtransPaymentDriver::class);

        $this->actingAs($customer)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'text/html, application/xhtml+xml',
            ])
            ->post("/orders/{$order->order_code}/pay", ['method' => 'bank_transfer', 'bank' => 'bca'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', $redirectUrl);
    }

    public function test_midtrans_settlement_webhook_issues_tickets_and_sends_email(): void
    {
        Mail::fake();

        $customer = $this->customer();
        $order = $this->pendingOrderWithItem($customer, quantity: 2);
        $this->actingAs($customer)->pay($order);

        $this->postJson('/api/midtrans/webhook', $this->midtransPayload($order, 'settlement'))
            ->assertOk()
            ->assertExactJson(['message' => 'Success']);

        $this->assertSame(PaymentStatus::Settlement->value, $order->payments()->first()->fresh()->payment_status);
        $this->assertSame(OrderStatus::Paid->value, $order->fresh()->status);
        $this->assertSame(2, Ticket::count());
        Mail::assertSent(ETicketEmail::class, 2);
    }

    public function test_midtrans_accepted_capture_webhook_settles_payment(): void
    {
        $customer = $this->customer();
        $order = $this->pendingOrderWithItem($customer, quantity: 1);
        $this->actingAs($customer)->pay($order);

        $this->postJson('/api/midtrans/webhook', $this->midtransPayload($order, 'capture', 'accept'))->assertOk();

        $this->assertSame(PaymentStatus::Settlement->value, $order->payments()->first()->fresh()->payment_status);
        $this->assertSame(OrderStatus::Paid->value, $order->fresh()->status);
        $this->assertSame(1, Ticket::count());
    }

    public function test_midtrans_pending_webhook_leaves_payment_pending(): void
    {
        $customer = $this->customer();
        $order = $this->pendingOrderWithItem($customer);
        $this->actingAs($customer)->pay($order);

        $this->postJson('/api/midtrans/webhook', $this->midtransPayload($order, 'pending'))->assertOk();

        $this->assertSame(PaymentStatus::Pending->value, $order->payments()->first()->fresh()->payment_status);
        $this->assertSame(OrderStatus::Pending->value, $order->fresh()->status);
    }

    public function test_midtrans_denial_webhook_marks_payment_as_failed(): void
    {
        $customer = $this->customer();
        $order = $this->pendingOrderWithItem($customer);
        $this->actingAs($customer)->pay($order);

        $this->postJson('/api/midtrans/webhook', $this->midtransPayload($order, 'deny'))->assertOk();

        $this->assertSame(PaymentStatus::Failure->value, $order->payments()->first()->fresh()->payment_status);
        $this->assertSame(OrderStatus::Pending->value, $order->fresh()->status);
    }

    public function test_midtrans_expiration_webhook_expires_order_and_releases_stock(): void
    {
        $customer = $this->customer();
        $order = $this->pendingOrderWithItem($customer, quantity: 4);
        $category = $order->orderItems->first()->ticketCategory;
        $this->actingAs($customer)->pay($order);

        $this->postJson('/api/midtrans/webhook', $this->midtransPayload($order, 'expire'))->assertOk();

        $this->assertSame(PaymentStatus::Expire->value, $order->payments()->first()->fresh()->payment_status);
        $this->assertSame(OrderStatus::Expired->value, $order->fresh()->status);
        $this->assertSame(100, $category->fresh()->available_quantity);
    }

    public function test_midtrans_capture_challenge_does_not_issue_tickets(): void
    {
        $customer = $this->customer();
        $order = $this->pendingOrderWithItem($customer);
        $this->actingAs($customer)->pay($order);

        $this->postJson('/api/midtrans/webhook', $this->midtransPayload($order, 'capture', 'challenge'))->assertOk();

        $this->assertSame(PaymentStatus::Pending->value, $order->payments()->first()->fresh()->payment_status);
        $this->assertSame(OrderStatus::Pending->value, $order->fresh()->status);
        $this->assertSame(0, Ticket::count());
    }

    public function test_customer_can_create_pending_payment(): void
    {
        $customer = $this->customer();
        $order = $this->pendingOrderWithItem($customer);

        $this->actingAs($customer)
            ->post("/orders/{$order->order_code}/pay", ['method' => 'bank_transfer', 'bank' => 'bca'])
            ->assertRedirect("/orders/{$order->order_code}");

        $payment = $order->payments()->first();
        $this->assertNotNull($payment);
        $this->assertStringStartsWith('MOCK-', $payment->transaction_id);
        $this->assertSame(PaymentType::BankTransfer->value, $payment->payment_type);
        $this->assertSame(PaymentStatus::Pending->value, $payment->payment_status);
        $this->assertSame('400000.00', $payment->gross_amount);
        $this->assertSame(OrderStatus::Pending->value, $order->fresh()->status);
        $this->assertSame('bca', $payment->raw_response['bank']);
        $this->assertSame('8008'.str_pad((string) $order->id, 10, '0', STR_PAD_LEFT), $payment->raw_response['va_number']);
    }

    public function test_bank_transfer_requires_a_selected_bank(): void
    {
        $customer = $this->customer();
        $order = $this->pendingOrderWithItem($customer);

        $this->actingAs($customer)
            ->post("/orders/{$order->order_code}/pay", ['method' => 'bank_transfer'])
            ->assertSessionHasErrors('bank');

        $this->assertSame(0, $order->payments()->count());
    }

    public function test_bank_transfer_rejects_unknown_bank(): void
    {
        $customer = $this->customer();
        $order = $this->pendingOrderWithItem($customer);

        $this->actingAs($customer)
            ->post("/orders/{$order->order_code}/pay", ['method' => 'bank_transfer', 'bank' => 'bank-keliling'])
            ->assertSessionHasErrors('bank');

        $this->assertSame(0, $order->payments()->count());
    }

    public function test_customer_can_pay_with_qris(): void
    {
        $customer = $this->customer();
        $order = $this->pendingOrderWithItem($customer);

        $this->actingAs($customer)->post("/orders/{$order->order_code}/pay", ['method' => 'qris'])->assertRedirect();

        $payment = $order->payments()->first();
        $this->assertSame(PaymentType::Qris->value, $payment->payment_type);
        $this->assertSame('STX-'.strtoupper($order->order_code.'-'.$order->id), $payment->raw_response['qr_string']);
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

    public function test_customer_can_check_status_and_settle_payment(): void
    {
        $customer = $this->customer();
        $order = $this->pendingOrderWithItem($customer, quantity: 2);
        $this->actingAs($customer)->pay($order);
        $payment = $order->payments()->first();

        $this->actingAs($customer)
            ->post("/payments/{$payment->id}/check")
            ->assertRedirect("/orders/{$order->order_code}")
            ->assertSessionHas('success');

        $this->assertSame(PaymentStatus::Settlement->value, $payment->fresh()->payment_status);
        $this->assertSame(OrderStatus::Paid->value, $order->fresh()->status);
        $this->assertSame(2, Ticket::count());
    }

    public function test_check_status_cannot_be_replayed(): void
    {
        $customer = $this->customer();
        $order = $this->pendingOrderWithItem($customer, quantity: 2);
        $this->actingAs($customer)->pay($order);
        $payment = $order->payments()->first();

        $this->actingAs($customer)->post("/payments/{$payment->id}/check")->assertRedirect();

        $this->actingAs($customer)
            ->post("/payments/{$payment->id}/check")
            ->assertRedirect("/orders/{$order->order_code}")
            ->assertSessionHas('error');

        $this->assertSame(2, Ticket::count());
    }

    public function test_check_status_of_another_customer_returns_404(): void
    {
        $owner = $this->customer();
        $order = $this->pendingOrderWithItem($owner);
        $this->actingAs($owner)->pay($order);
        $payment = $order->payments()->first();

        $this->actingAs($this->customer())
            ->post("/payments/{$payment->id}/check")
            ->assertNotFound();
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
