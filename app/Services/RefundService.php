<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\TicketStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
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
     * @return Collection<int, Refund>
     */
    public function cancelEventAndRefund(Event $event, string $reason): Collection
    {
        return DB::transaction(function () use ($event, $reason): Collection {
            $event->update(['status' => EventStatus::Cancelled]);

            $refunds = new Collection;

            foreach ($this->settledOrdersFor($event) as $order) {
                $order->update(['status' => OrderStatus::Cancelled]);

                $refunds->push($order->refunds()->create([
                    'payment_id' => $this->settledPaymentIdFor($order),
                    'amount' => $order->grand_total,
                    'refund_key' => 'rfnd-'.Str::uuid(),
                    'status' => RefundStatus::Pending,
                    'reason' => 'Event dibatalkan: '.$reason,
                ]));

                foreach ($order->orderItems as $item) {
                    $this->categories->incrementAvailable($item->ticket_category_id, $item->quantity);

                    $item->tickets()->update(['status' => TicketStatus::Cancelled]);
                }
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
     * @return Collection<int, Order>
     */
    private function settledOrdersFor(Event $event): Collection
    {
        return Order::query()
            ->where('status', OrderStatus::Paid)
            ->whereHas('orderItems.ticketCategory', fn ($query) => $query->where('event_id', $event->id))
            ->with(['orderItems.tickets', 'customer:id,name,email'])
            ->get();
    }

    private function settledPaymentIdFor(Order $order): ?int
    {
        return $this->settledPaymentFor($order)?->id;
    }

    private function settledPaymentFor(Order $order): ?Payment
    {
        return $order->payments()
            ->where('payment_status', PaymentStatus::Settlement)
            ->latest('id')
            ->first();
    }
}
