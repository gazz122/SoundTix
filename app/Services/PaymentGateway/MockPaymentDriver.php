<?php

namespace App\Services\PaymentGateway;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Order;
use App\Models\Payment;
use App\Notifications\SoundTixNotification;
use App\Repositories\PaymentRepository;
use App\Services\OrderExpirationService;
use App\Services\TicketGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MockPaymentDriver implements PaymentGatewayInterface
{
    public function __construct(
        private TicketGenerator $tickets,
        private OrderExpirationService $expiration,
        private PaymentRepository $payments,
    ) {}

    public function createPayment(Order $order, PaymentType $method): Payment
    {
        return $this->payments->create([
            'order_id' => $order->id,
            'transaction_id' => 'MOCK-'.Str::upper(Str::random(10)),
            'payment_type' => $method->value,
            'gross_amount' => $order->grand_total,
            'payment_status' => PaymentStatus::Pending->value,
            'raw_response' => ['simulated' => true, 'method' => $method->value],
        ]);
    }

    public function process(Payment $payment, PaymentStatus $result): void
    {
        DB::transaction(function () use ($payment, $result) {
            /** @var Payment|null $locked */
            $locked = $this->payments->findLocked($payment->id);

            if ($locked === null || $locked->payment_status !== PaymentStatus::Pending->value) {
                return;
            }

            /** @var Order $order */
            $order = $locked->order()->with('orderItems')->first();

            if ($order->status !== OrderStatus::Pending->value) {
                $locked->update(['payment_status' => PaymentStatus::Failure->value]);

                return;
            }

            if ($result === PaymentStatus::Settlement) {
                $locked->update([
                    'payment_status' => PaymentStatus::Settlement->value,
                    'raw_response' => [...($locked->raw_response ?? []), 'settled_at' => now()->toIso8601String()],
                ]);
                $order->update([
                    'status' => OrderStatus::Paid->value,
                    'paid_at' => now(),
                ]);

                $this->tickets->issueForPaidOrder($order);
                $order->customer->notify(new SoundTixNotification([
                    'title' => 'Pembayaran berhasil',
                    'message' => "Pembayaran untuk pesanan {$order->order_code} berhasil. E-tiket Anda siap digunakan.",
                    'type' => 'payment_settled',
                    'action_url' => route('orders.show', $order->order_code),
                    'action_label' => 'Lihat e-tiket',
                ]));
            } elseif ($result === PaymentStatus::Expire) {
                $locked->update(['payment_status' => PaymentStatus::Expire->value]);
                $this->expiration->expire($order);
            }
        });
    }
}
