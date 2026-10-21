<?php

namespace Tests\Feature;

use App\Enums\PaymentType;
use App\Enums\RefundGateway;
use App\Enums\RefundStatus;
use App\Enums\TicketStatus;
use App\Jobs\ProcessEventCancellationRefundsJob;
use App\Jobs\ProcessMidtransApiRefundJob;
use App\Jobs\ProcessMidtransIrisPayoutJob;
use App\Models\Event;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\PaymentGateway\MidtransIrisPayoutGateway;
use App\Services\PaymentGateway\PaymentGatewayInterface;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class AutomatedRefundFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_approve_cancellation_dispatches_the_orchestrator_job(): void
    {
        Queue::fake();

        $event = $this->cancellableEvent();

        $this->actingAs(User::factory()->admin()->create())
            ->post("/admin/refunds/events/{$event->id}/approve-cancellation")
            ->assertRedirect(route('admin.refunds.index'))
            ->assertSessionHas('success');

        Queue::assertPushed(ProcessEventCancellationRefundsJob::class, fn ($job) => $job->event->id === $event->id);
    }

    public function test_automation_off_still_asks_the_buyer_where_to_send_the_money(): void
    {
        config(['refund.automation_enabled' => false]);
        Queue::fake();

        $event = $this->cancellableEvent(PaymentType::Qris);

        $this->runOrchestrator($event);

        Queue::assertNothingPushed();

        $refund = Refund::query()->firstOrFail();

        // Automation is off, so nothing may call a payment API - but an admin still
        // cannot transfer money to an account only the buyer knows.
        $this->assertSame(RefundStatus::WaitingBankDetails, $refund->status);
        $this->assertSame(RefundGateway::Manual, $refund->refund_gateway);

        $owner = User::query()->findOrFail($refund->order->customer_id);
        $this->assertSame(1, $owner->notifications()->where('data->type', 'refund_bank_details')->count());
    }

    public function test_a_large_cancellation_is_split_across_queue_runs(): void
    {
        config(['refund.cancellation_slice' => 2]);
        $this->enableAutomation();
        Queue::fake();

        $event = $this->cancellableEvent(PaymentType::BankTransfer);
        $category = TicketCategory::query()->where('event_id', $event->id)->firstOrFail();
        $customer = User::factory()->create();

        foreach ([1, 2] as $index) {
            $order = Order::factory()->paid()->create([
                'customer_id' => $customer->id,
                'total_amount' => 200_000,
                'grand_total' => 200_000,
            ]);

            $item = $order->orderItems()->create([
                'ticket_category_id' => $category->id,
                'quantity' => 1,
                'price_per_unit' => 200_000,
                'subtotal' => 200_000,
            ]);

            Ticket::create([
                'order_item_id' => $item->id,
                'ticket_code' => "EXTRA-{$index}",
                'qr_payload' => "EXTRA-{$index}.{$order->id}.{$item->id}.hmac",
                'status' => TicketStatus::Active->value,
            ]);

            Payment::factory()->settlement()->create([
                'order_id' => $order->id,
                'gross_amount' => 200_000,
            ]);
        }

        $this->runOrchestrator($event);

        // One slice per run, so the first run settles two of the three orders and
        // queues itself for the rest instead of opening one enormous transaction.
        $this->assertSame(2, Refund::query()->count());
        Queue::assertPushed(ProcessEventCancellationRefundsJob::class, 1);

        $this->runOrchestrator($event);

        $this->assertSame(3, Refund::query()->count());
        $this->assertSame(0, Order::query()->where('status', 'paid')->count());

        // The last slice was short, so the chain stops here.
        Queue::assertPushed(ProcessEventCancellationRefundsJob::class, 1);
    }

    public function test_qris_refund_is_routed_to_the_midtrans_refund_api(): void
    {
        $this->enableAutomation();
        Queue::fake();

        $event = $this->cancellableEvent(PaymentType::Qris);

        $this->runOrchestrator($event);

        $refund = Refund::query()->firstOrFail();

        Queue::assertPushed(
            ProcessMidtransApiRefundJob::class,
            fn ($job) => $job->refund->is($refund),
        );
    }

    public function test_qris_refund_is_settled_by_the_refund_job(): void
    {
        $this->enableAutomation();

        $event = $this->cancellableEvent(PaymentType::Qris);

        $this->runOrchestrator($event);

        $refund = Refund::query()->firstOrFail();

        $this->assertSame(RefundStatus::Completed, $refund->status);
        $this->assertSame(RefundGateway::MidtransRefundApi, $refund->refund_gateway);
        $this->assertNotNull($refund->processed_at);
        $this->assertSame($refund->refund_key, $refund->gateway_reference_id);

        // The order stays cancelled: money went back, the sale did not return.
        $this->assertDatabaseHas('orders', ['id' => $refund->order_id, 'status' => 'cancelled']);
    }

    public function test_refund_job_does_not_call_the_gateway_twice_for_a_settled_refund(): void
    {
        $order = Order::factory()->paid()->create([
            'total_amount' => 400_000,
            'grand_total' => 400_000,
        ]);
        $payment = Payment::factory()->settlement()->create(['order_id' => $order->id]);

        $refund = Refund::factory()->completed()->create([
            'order_id' => $order->id,
            'payment_id' => $payment->id,
        ]);

        $this->mock(PaymentGatewayInterface::class)
            ->shouldReceive('refund')
            ->never();

        (new ProcessMidtransApiRefundJob($refund))->handle(app(RefundService::class), app(PaymentGatewayInterface::class));

        $this->assertSame(RefundStatus::Completed, $refund->fresh()->status);
    }

    public function test_failed_automated_refund_falls_back_to_manual_completion(): void
    {
        $refund = Refund::factory()->pending()->create([
            'refund_gateway' => RefundGateway::MidtransRefundApi,
        ]);

        (new ProcessMidtransApiRefundJob($refund))->failed(new RuntimeException('Midtrans tidak merespons.'));

        $refund->refresh();

        $this->assertSame(RefundStatus::Pending, $refund->status);
        $this->assertSame(RefundGateway::Manual, $refund->refund_gateway);
        $this->assertStringContainsString('Midtrans tidak merespons.', (string) $refund->failure_reason);
        $this->assertTrue($refund->canBeCompleted());
    }

    public function test_bank_transfer_refund_waits_for_bank_details_when_iris_is_configured(): void
    {
        $this->enableAutomation();
        $this->enableIris();
        Queue::fake();

        $event = $this->cancellableEvent(PaymentType::BankTransfer);

        $this->runOrchestrator($event);

        $refund = Refund::query()->firstOrFail();

        $this->assertSame(RefundStatus::WaitingBankDetails, $refund->status);
        $this->assertSame(RefundGateway::MidtransIrisPayout, $refund->refund_gateway);
        $this->assertTrue($refund->requiresBankDetails());

        $owner = User::query()->findOrFail($refund->order->customer_id);
        $this->assertSame(1, $owner->notifications()->where('data->type', 'refund_bank_details')->count());

        Queue::assertNotPushed(ProcessMidtransApiRefundJob::class);
    }

    public function test_bank_transfer_refund_without_iris_still_asks_the_buyer_for_an_account(): void
    {
        $this->enableAutomation();
        Queue::fake();

        $event = $this->cancellableEvent(PaymentType::BankTransfer);

        $this->runOrchestrator($event);

        $refund = Refund::query()->firstOrFail();

        $this->assertSame(RefundStatus::WaitingBankDetails, $refund->status);
        $this->assertSame(RefundGateway::Manual, $refund->refund_gateway);

        $owner = User::query()->findOrFail($refund->order->customer_id);
        $this->assertSame(1, $owner->notifications()->where('data->type', 'refund_bank_details')->count());

        Queue::assertNotPushed(ProcessMidtransIrisPayoutJob::class);
    }

    public function test_iris_payout_job_sends_the_payout_and_records_the_reference(): void
    {
        $this->enableIris();
        Http::fake([
            '*/iris/api/v1/payouts' => Http::response([
                'payouts' => [['reference_no' => 'IRIS-REF-1', 'status' => 'queued']],
            ]),
        ]);

        $refund = $this->refundWithBankDetails();

        (new ProcessMidtransIrisPayoutJob($refund))->handle(app(RefundService::class), app(MidtransIrisPayoutGateway::class));

        $refund->refresh();

        $this->assertSame(RefundStatus::Completed, $refund->status);
        $this->assertSame('IRIS-REF-1', $refund->gateway_reference_id);
        $this->assertSame('queued', $refund->gateway_response['status']);

        Http::assertSent(function ($request) use ($refund): bool {
            $payout = $request['payouts'][0];

            // Iris reads the idempotency handle from the header; an undocumented
            // body field would be rejected, and without the header a retry after a
            // timeout would pay the buyer twice.
            return str_contains($request->url(), '/iris/api/v1/payouts')
                && $request->hasHeader('X-Idempotency-Key', $refund->refund_key)
                && ! isset($payout['reference'])
                && $payout['beneficiary_account'] === $refund->account_number
                // Monetary values must carry the .0 fraction Iris documents.
                && $payout['amount'] === '400000.00'
                // Iris allows only letters, digits and spaces in a bank note.
                && $payout['notes'] === str_replace('-', '', 'Refund '.$refund->order->order_code);
        });
    }

    public function test_iris_payout_omits_an_empty_beneficiary_email(): void
    {
        $this->enableIris();
        Http::fake(['*/iris/api/v1/payouts' => Http::response([
            'payouts' => [['reference_no' => 'IRIS-REF-2', 'status' => 'queued']],
        ])]);

        $refund = $this->refundWithBankDetails();
        $refund->order->customer->update(['email' => '']);

        (new ProcessMidtransIrisPayoutJob($refund->refresh()))->handle(app(RefundService::class), app(MidtransIrisPayoutGateway::class));

        Http::assertSent(fn ($request): bool => ! isset($request['payouts'][0]['beneficiary_email']));
    }

    public function test_iris_payout_job_falls_back_to_manual_when_iris_is_not_configured(): void
    {
        $refund = $this->refundWithBankDetails();
        $refund->update(['refund_gateway' => RefundGateway::MidtransIrisPayout]);

        (new ProcessMidtransIrisPayoutJob($refund))->handle(app(RefundService::class), app(MidtransIrisPayoutGateway::class));

        $refund->refresh();

        $this->assertSame(RefundStatus::Pending, $refund->status);
        $this->assertSame(RefundGateway::Manual, $refund->refund_gateway);
    }

    public function test_customer_can_view_and_submit_their_refund_account(): void
    {
        [$customer, $refund] = $this->refundForCustomer();

        $this->actingAs($customer)
            ->get("/refunds/{$refund->id}/account")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Refunds/Account')
                ->where('refund.id', $refund->id)
                ->where('refund.can_submit', true));

        $this->actingAs($customer)
            ->post("/refunds/{$refund->id}/account", [
                'bank_name' => 'BCA',
                'account_number' => '1234567890',
                'account_holder_name' => 'Budi Santoso',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('refunds', [
            'id' => $refund->id,
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder_name' => 'Budi Santoso',
        ]);
    }

    public function test_customer_cannot_touch_someone_elses_refund(): void
    {
        [, $refund] = $this->refundForCustomer();

        $this->actingAs(User::factory()->create())
            ->get("/refunds/{$refund->id}/account")
            ->assertNotFound();

        $this->actingAs(User::factory()->create())
            ->post("/refunds/{$refund->id}/account", [
                'bank_name' => 'BCA',
                'account_number' => '1234567890',
                'account_holder_name' => 'Penyusup',
            ])
            ->assertNotFound();

        $this->assertNull($refund->fresh()->bank_name);
    }

    public function test_submitting_bank_details_dispatches_the_iris_payout_job(): void
    {
        [$customer, $refund] = $this->refundForCustomer([
            'status' => RefundStatus::WaitingBankDetails,
            'refund_gateway' => RefundGateway::MidtransIrisPayout,
        ]);

        Queue::fake();

        $this->actingAs($customer)
            ->post("/refunds/{$refund->id}/account", [
                'bank_name' => 'BCA',
                'account_number' => '1234567890',
                'account_holder_name' => 'Budi Santoso',
            ])
            ->assertSessionHas('success');

        Queue::assertPushed(ProcessMidtransIrisPayoutJob::class, fn ($job) => $job->refund->id === $refund->id);
    }

    public function test_submitting_bank_details_for_a_manual_refund_returns_it_to_the_transfer_queue(): void
    {
        [$customer, $refund] = $this->refundForCustomer([
            'status' => RefundStatus::WaitingBankDetails,
            'refund_gateway' => RefundGateway::Manual,
        ]);

        Queue::fake();

        $this->actingAs($customer)
            ->post("/refunds/{$refund->id}/account", [
                'bank_name' => 'BCA',
                'account_number' => '1234567890',
                'account_holder_name' => 'Budi Santoso',
            ])
            ->assertSessionHas('success');

        $refund->refresh();

        // Nothing automated picks this up, so the refund becomes the admin's to-do
        // again instead of looking like it is still waiting on the buyer.
        $this->assertSame(RefundStatus::Pending, $refund->status);
        $this->assertSame('1234567890', $refund->account_number);
        $this->assertTrue($refund->canBeCompleted());

        Queue::assertNothingPushed();
    }

    public function test_a_refund_in_flight_cannot_be_completed_by_hand(): void
    {
        $refund = Refund::factory()->pending()->create(['status' => RefundStatus::Processing]);

        $this->assertFalse($refund->canBeCompleted());
    }

    private function enableAutomation(): void
    {
        config(['refund.automation_enabled' => true]);
    }

    /**
     * Running the orchestrator through the queue would be faked away by
     * Queue::fake() (it intercepts dispatchSync too), so the job is invoked
     * directly and its own dispatches still land on the fake.
     */
    private function runOrchestrator(Event $event): void
    {
        app()->call([new ProcessEventCancellationRefundsJob($event), 'handle']);
    }

    private function enableIris(): void
    {
        config([
            'services.midtrans.iris_enabled' => true,
            'services.midtrans.iris_api_key' => 'iris-test-key',
        ]);
    }

    private function cancellableEvent(PaymentType $type = PaymentType::Qris): Event
    {
        $event = Event::factory()->published()->create([
            'status' => 'pending_cancellation',
            'cancellation_reason' => 'Artis berhalangan hadir.',
        ]);

        $category = TicketCategory::factory()->create([
            'event_id' => $event->id,
            'price' => 200_000,
            'total_quantity' => 100,
            'available_quantity' => 100,
        ]);

        $order = Order::factory()->paid()->create([
            'customer_id' => User::factory()->create()->id,
            'total_amount' => 400_000,
            'grand_total' => 400_000,
        ]);

        $item = $order->orderItems()->create([
            'ticket_category_id' => $category->id,
            'quantity' => 2,
            'price_per_unit' => 200_000,
            'subtotal' => 400_000,
        ]);

        for ($i = 0; $i < 2; $i++) {
            Ticket::create([
                'order_item_id' => $item->id,
                'ticket_code' => "TICKET-{$item->id}-{$i}",
                'qr_payload' => "TICKET-{$item->id}-{$i}.{$order->id}.{$item->id}.hmac",
                'status' => TicketStatus::Active->value,
            ]);
        }

        Payment::factory()->settlement()->create([
            'order_id' => $order->id,
            'gross_amount' => 400_000,
            'payment_type' => $type->value,
        ]);

        return $event;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{0: User, 1: Refund}
     */
    private function refundForCustomer(array $attributes = []): array
    {
        $customer = User::factory()->create();
        $order = Order::factory()->paid()->create([
            'customer_id' => $customer->id,
            'total_amount' => 400_000,
            'grand_total' => 400_000,
        ]);

        $refund = Refund::factory()->pending()->create([
            'order_id' => $order->id,
            ...$attributes,
        ]);

        return [$customer, $refund];
    }

    private function refundWithBankDetails(): Refund
    {
        return Refund::factory()->pending()->create([
            'amount' => 400_000,
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder_name' => 'Budi Santoso',
        ]);
    }
}
