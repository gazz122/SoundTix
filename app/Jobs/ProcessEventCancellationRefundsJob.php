<?php

namespace App\Jobs;

use App\Enums\PaymentType;
use App\Enums\RefundGateway;
use App\Models\Event;
use App\Models\Refund;
use App\Notifications\SoundTixNotification;
use App\Services\PaymentGateway\MidtransIrisPayoutGateway;
use App\Services\RefundService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Fans a cancelled event out into one refund per settled order, then hands each
 * refund to whichever lane can actually move that money.
 *
 * A cancelled event can carry thousands of orders, so each run settles one slice
 * and queues the next. That keeps every run inside the worker timeout and turns
 * a crash into redoing one slice instead of the whole event.
 */
class ProcessEventCancellationRefundsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public Event $event) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(RefundService $refunds, MidtransIrisPayoutGateway $iris): void
    {
        $slice = max(1, (int) config('refund.cancellation_slice'));

        $created = $refunds->cancelEventAndRefund(
            $this->event,
            (string) $this->event->cancellation_reason,
            $slice,
        );

        // A retry finds no paid orders left, which is the natural idempotency
        // guard: never re-notify, never open a second refund for the same order.
        if ($created->isEmpty()) {
            return;
        }

        foreach ($created as $refund) {
            $this->notifyCancellation($refund);
            $this->route($refund, $refunds, $iris);
        }

        if ($created->count() === $slice) {
            self::dispatch($this->event);
        }
    }

    private function route(Refund $refund, RefundService $refunds, MidtransIrisPayoutGateway $iris): void
    {
        // QRIS is the one payment type Midtrans can push money back to on its own,
        // and only once automation is switched on.
        if (config('refund.automation_enabled') && $this->paymentTypeFor($refund) === PaymentType::Qris) {
            ProcessMidtransApiRefundJob::dispatch($refund);

            return;
        }

        // Everything else is paid out by hand, or by Iris once the merchant has an
        // account for it. Both need a destination only the buyer knows, so the
        // refund waits for those details before anyone can move the money.
        $refunds->requestBankDetails(
            $refund,
            config('refund.automation_enabled') && $iris->isConfigured()
                ? RefundGateway::MidtransIrisPayout
                : RefundGateway::Manual,
        );

        $this->notifyBankDetailsNeeded($refund);
    }

    private function paymentTypeFor(Refund $refund): ?PaymentType
    {
        $type = $refund->payment?->payment_type;

        return is_string($type) ? PaymentType::tryFrom($type) : null;
    }

    private function notifyCancellation(Refund $refund): void
    {
        $order = $refund->order;

        $order?->customer?->notify(new SoundTixNotification([
            'title' => 'Event dibatalkan',
            'message' => "Event \"{$this->event->title}\" dibatalkan. Dana pesanan {$order->order_code} akan dikembalikan.",
            'type' => 'event_cancelled',
            'action_url' => route('orders.show', $order->order_code),
            'action_label' => 'Lihat pesanan',
        ]));
    }

    private function notifyBankDetailsNeeded(Refund $refund): void
    {
        $customer = $refund->order?->customer;

        $customer?->notify(new SoundTixNotification([
            'title' => 'Lengkapi data rekening',
            'message' => "Event dibatalkan. Isi nama bank dan nomor rekening supaya dana pesanan {$refund->order?->order_code} bisa dikembalikan.",
            'type' => 'refund_bank_details',
            'action_url' => route('refunds.account.show', $refund->id),
            'action_label' => 'Isi data rekening',
        ]));
    }
}
