<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundGateway;
use App\Enums\RefundStatus;
use App\Enums\TicketStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Ticket;
use App\Repositories\TicketCategoryRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RefundService
{
    public function __construct(private TicketCategoryRepository $categories) {}

    /**
     * Cancel an event, revoke its tickets, and open a full refund for every
     * settled order.
     *
     * The refund is only opened here. No money moves until an admin transfers it
     * by hand and records the destination account, which keeps the transfer and
     * the record from drifting apart.
     *
     * One slice is handled per call because a cancelled event can carry thousands
     * of orders. Every slice only picks orders that are still paid, so a crash is
     * safe to retry and never opens a second refund for the same order.
     *
     * @return Collection<int, Refund>
     */
    public function cancelEventAndRefund(Event $event, string $reason, int $limit = 250): Collection
    {
        return DB::transaction(function () use ($event, $reason, $limit): Collection {
            $event->update(['status' => EventStatus::Cancelled]);

            $refunds = new Collection;

            foreach ($this->settledOrdersFor($event, $limit) as $order) {
                $order->update(['status' => OrderStatus::Cancelled]);

                $payment = $this->settledPaymentFor($order);

                $refund = $order->refunds()->create([
                    'payment_id' => $payment?->id,
                    'amount' => $order->grand_total,
                    'refund_key' => 'rfnd-'.Str::uuid(),
                    'refund_gateway' => RefundGateway::Manual,
                    'status' => RefundStatus::Pending,
                    'reason' => 'Event dibatalkan: '.$reason,
                ]);

                // Attached now so the routing step does not lazy-load the same
                // order and payment again for every single refund.
                $refund->setRelation('order', $order);
                $refund->setRelation('payment', $payment);

                foreach ($order->orderItems as $item) {
                    $this->categories->incrementAvailable($item->ticket_category_id, $item->quantity);
                }

                Ticket::query()
                    ->whereIn('order_item_id', $order->orderItems->pluck('id'))
                    ->update(['status' => TicketStatus::Cancelled]);

                $refunds->push($refund);
            }

            return $refunds;
        });
    }

    /**
     * Settle one refund that an admin has transferred by hand.
     *
     * @param  array{bank_name: string, account_number: string, account_holder_name: string, notes?: ?string, proof_of_transfer?: ?string}  $attributes
     */
    public function completeManually(Refund $refund, array $attributes): Refund
    {
        $refund->update([
            ...$attributes,
            'status' => RefundStatus::Completed,
            'processed_at' => now(),
        ]);

        return $refund;
    }

    /**
     * Hand a refund to a gateway worker.
     *
     * The gateway calls are retried, so a refund may be claimed more than once.
     * Duplicate protection lives with the gateway itself: both Midtrans refunds
     * and Iris payouts are keyed by refund_key, so replaying a claim cannot move
     * money twice.
     */
    public function markProcessing(Refund $refund, RefundGateway $gateway): Refund
    {
        $refund->update([
            'status' => RefundStatus::Processing,
            'refund_gateway' => $gateway,
            'failure_reason' => null,
        ]);

        return $refund;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public function markCompleted(Refund $refund, ?string $gatewayReferenceId, array $response = []): Refund
    {
        $refund->update([
            'status' => RefundStatus::Completed,
            'gateway_reference_id' => $gatewayReferenceId,
            'gateway_response' => $response,
            'processed_at' => now(),
        ]);

        return $refund;
    }

    /**
     * Park a refund until the buyer says where to send the money.
     */
    public function requestBankDetails(Refund $refund, RefundGateway $gateway): Refund
    {
        $refund->update([
            'status' => RefundStatus::WaitingBankDetails,
            'refund_gateway' => $gateway,
        ]);

        return $refund;
    }

    /**
     * Automation gave up. Hand the refund back to the manual queue so an admin
     * can finish it by hand instead of the money getting stuck.
     */
    public function fallBackToManual(Refund $refund, string $reason): Refund
    {
        $refund->update([
            'status' => RefundStatus::Pending,
            'refund_gateway' => RefundGateway::Manual,
            'failure_reason' => $reason,
        ]);

        return $refund;
    }

    /**
     * Settle a batch that an admin transferred as one bank file upload.
     *
     * Nothing per-refund is written: the destinations were already recorded by
     * the buyers, and the bank file itself is the proof of transfer. Refunds a
     * gateway is still working on are skipped so a batch cannot pay twice.
     *
     * @param  iterable<int, Refund>  $refunds
     * @return int how many were actually settled
     */
    public function completeBatch(iterable $refunds): int
    {
        $completed = 0;

        foreach ($refunds as $refund) {
            if (! $refund->canBeCompleted() || ! $refund->hasBankDetails()) {
                continue;
            }

            $refund->update([
                'status' => RefundStatus::Completed,
                'processed_at' => now(),
            ]);

            $completed++;
        }

        return $completed;
    }

    /**
     * @return Collection<int, Order>
     */
    private function settledOrdersFor(Event $event, int $limit): Collection
    {
        return Order::query()
            ->where('status', OrderStatus::Paid)
            ->whereHas('orderItems.ticketCategory', fn ($query) => $query->where('event_id', $event->id))
            ->with(['orderItems', 'customer:id,name,email'])
            ->orderBy('id')
            ->limit($limit)
            ->lockForUpdate()
            ->get();
    }

    private function settledPaymentFor(Order $order): ?Payment
    {
        return $order->payments()
            ->where('payment_status', PaymentStatus::Settlement)
            ->latest('id')
            ->first();
    }
}
