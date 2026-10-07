<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\TicketStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\PaymentGateway\MidtransPaymentDriver;
use App\Services\PaymentGateway\PaymentGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RefundWebFeatureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Order, 2: Payment}
     */
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

        $payment = Payment::factory()->settlement()->create([
            'order_id' => $order->id,
            'gross_amount' => $price * $quantity,
        ]);

        $category->decrement('available_quantity', $quantity);

        return [$customer, $order, $payment];
    }

    /**
     * The default testing gateway is the mock driver, which never talks HTTP.
     * Refund semantics live in the Midtrans driver, so bind the real one.
     */
    private function useMidtransGateway(array $patterns): void
    {
        config(['services.midtrans.server_key' => 'test-server-key']);
        $this->app->instance(PaymentGatewayInterface::class, $this->app->make(MidtransPaymentDriver::class));
        Http::fake($patterns);
    }

    public function test_customer_can_no_longer_request_a_refund(): void
    {
        [$customer, $order] = $this->paidEvent();

        $this->actingAs($customer)
            ->post("/orders/{$order->order_code}/refund", ['reason' => 'Tidak bisa hadir.'])
            ->assertNotFound();

        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_admin_can_view_refunds_page(): void
    {
        [$customer, $order] = $this->paidEvent();
        $refund = Refund::factory()->pending()->create([
            'order_id' => $order->id,
            'amount' => 400_000,
            'reason' => 'Event dibatalkan.',
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

    public function test_pending_cancellation_exposes_the_refundable_total(): void
    {
        [$customer, $order] = $this->paidEvent(price: 200_000, quantity: 2);
        $event = $order->orderItems->first()->ticketCategory->event;
        $admin = User::factory()->admin()->create();

        $event->update([
            'status' => EventStatus::PendingCancellation->value,
            'cancellation_reason' => 'Hujan deras.',
        ]);

        $this->actingAs($admin)->get('/admin/refunds')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Refunds/Index')
                ->has('cancellations', 1)
                ->where('cancellations.0.id', $event->id)
                ->where('cancellations.0.paid_orders_count', 1)
                ->where('cancellations.0.refund_total', 400_000));
    }

    public function test_refunds_page_survives_a_soft_deleted_customer(): void
    {
        [$customer, $order] = $this->paidEvent();
        Refund::factory()->pending()->create(['order_id' => $order->id, 'amount' => 400_000]);
        $customer->delete();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/refunds')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Refunds/Index')
                ->has('refunds', 1)
                ->where('refunds.0.customer_name', $customer->name)
                ->where('refunds.0.customer_email', $customer->email));
    }

    public function test_admin_can_complete_a_refund_with_the_destination_account(): void
    {
        [$customer, $order, $payment] = $this->paidEvent();
        $refund = Refund::factory()->pending()->create([
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'amount' => 400_000,
        ]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post("/admin/refunds/{$refund->id}/complete", [
                'bank_name' => 'BCA',
                'account_number' => '1234567890',
                'account_holder_name' => 'Budi Santoso',
                'notes' => 'Transfer via BCA mobile banking.',
            ])
            ->assertRedirect(route('admin.refunds.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('refunds', [
            'id' => $refund->id,
            'status' => RefundStatus::Completed->value,
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder_name' => 'Budi Santoso',
            'notes' => 'Transfer via BCA mobile banking.',
        ]);
        $this->assertNotNull($refund->fresh()->processed_at);
    }

    public function test_admin_can_attach_a_proof_of_transfer_when_completing(): void
    {
        Storage::fake('public');

        [$customer, $order, $payment] = $this->paidEvent();
        $refund = Refund::factory()->pending()->create([
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'amount' => 400_000,
        ]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post("/admin/refunds/{$refund->id}/complete", [
                'bank_name' => 'Mandiri',
                'account_number' => '9876543210',
                'account_holder_name' => 'Siti Aminah',
                'proof_of_transfer' => UploadedFile::fake()->image('bukti.jpg'),
            ])
            ->assertRedirect(route('admin.refunds.index'))
            ->assertSessionHas('success');

        $path = (string) $refund->fresh()->proof_of_transfer;

        $this->assertNotSame('', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_completing_a_refund_requires_the_destination_account(): void
    {
        [$customer, $order, $payment] = $this->paidEvent();
        $refund = Refund::factory()->pending()->create([
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'amount' => 400_000,
        ]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post("/admin/refunds/{$refund->id}/complete", [])
            ->assertSessionHasErrors(['bank_name', 'account_number', 'account_holder_name']);

        $this->assertDatabaseHas('refunds', [
            'id' => $refund->id,
            'status' => RefundStatus::Pending->value,
        ]);
    }

    public function test_completing_a_refund_rejects_a_non_numeric_account_number(): void
    {
        [$customer, $order, $payment] = $this->paidEvent();
        $refund = Refund::factory()->pending()->create([
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'amount' => 400_000,
        ]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post("/admin/refunds/{$refund->id}/complete", [
                'bank_name' => 'BRI',
                'account_number' => '1234-ABCD',
                'account_holder_name' => 'Andi',
            ])
            ->assertSessionHasErrors('account_number');

        $this->assertDatabaseHas('refunds', [
            'id' => $refund->id,
            'status' => RefundStatus::Pending->value,
        ]);
    }

    public function test_customer_cannot_complete_a_refund(): void
    {
        [$customer, $order, $payment] = $this->paidEvent();
        $refund = Refund::factory()->pending()->create([
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'amount' => 400_000,
        ]);

        $this->actingAs($customer)
            ->post("/admin/refunds/{$refund->id}/complete", [
                'bank_name' => 'BCA',
                'account_number' => '1234567890',
                'account_holder_name' => 'Budi Santoso',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('refunds', [
            'id' => $refund->id,
            'status' => RefundStatus::Pending->value,
        ]);
    }

    public function test_admin_cannot_complete_an_already_completed_refund(): void
    {
        [$customer, $order, $payment] = $this->paidEvent();
        $refund = Refund::factory()->completed()->create([
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'amount' => 400_000,
            'account_number' => '1111111111',
        ]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post("/admin/refunds/{$refund->id}/complete", [
                'bank_name' => 'Mandiri',
                'account_number' => '2222222222',
                'account_holder_name' => 'Siti Aminah',
            ])
            ->assertRedirect(route('admin.refunds.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('refunds', [
            'id' => $refund->id,
            'account_number' => '1111111111',
        ]);
    }

    public function test_refunds_page_exposes_the_recorded_destination_account(): void
    {
        [$customer, $order, $payment] = $this->paidEvent();
        $refund = Refund::factory()->completed()->create([
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'amount' => 400_000,
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder_name' => 'Budi Santoso',
        ]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/refunds')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('refunds.0.bank_name', 'BCA')
                ->where('refunds.0.account_number', '1234567890')
                ->where('refunds.0.account_holder_name', 'Budi Santoso')
                ->where('refunds.0.can_complete', false)
            );
    }

    public function test_customer_cannot_access_admin_refunds_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/refunds')
            ->assertForbidden();
    }

    public function test_webhook_persists_the_midtrans_transaction_id(): void
    {
        [$customer, $order, $payment] = $this->paidEvent();

        $this->useMidtransGateway([
            'https://api.sandbox.midtrans.com/v2/*' => Http::response(['transaction_status' => 'settlement'], 200),
        ]);

        $signature = hash('sha512', $payment->transaction_id.'200'.'400000.00'.'test-server-key');

        $this->postJson('/api/midtrans/webhook', [
            'order_id' => $payment->transaction_id,
            'transaction_id' => 'midtrans-abc-123',
            'status_code' => '200',
            'gross_amount' => '400000.00',
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'signature_key' => $signature,
        ])->assertOk();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'midtrans_transaction_id' => 'midtrans-abc-123',
            'payment_status' => PaymentStatus::Settlement->value,
        ]);
    }
}
