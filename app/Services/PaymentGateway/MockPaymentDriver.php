<?php

namespace App\Services\PaymentGateway;

use App\Enums\Bank;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Order;
use App\Models\Payment;
use App\Repositories\PaymentRepository;
use Illuminate\Support\Str;

class MockPaymentDriver implements PaymentGatewayInterface
{
    public function __construct(
        private PaymentRepository $payments,
        private PaymentProcessor $processor,
    ) {}

    public function createPayment(Order $order, PaymentType $method, ?string $bank = null): Payment
    {
        $raw = ['simulated' => true, 'method' => $method->value];

        if ($method === PaymentType::BankTransfer && $bank !== null) {
            $raw['bank'] = $bank;
            $raw['va_number'] = $this->vaNumber($order, $bank);
        } elseif ($method === PaymentType::Qris) {
            // ponytail: mock QRIS reference string, not a real QRIS payload; replace when a real driver lands.
            $raw['qr_string'] = 'STX-'.strtoupper($order->order_code.'-'.$order->id);
        }

        return $this->payments->create([
            'order_id' => $order->id,
            'transaction_id' => 'MOCK-'.Str::upper(Str::random(10)),
            'payment_type' => $method->value,
            'gross_amount' => $order->grand_total,
            'payment_status' => PaymentStatus::Pending->value,
            'raw_response' => $raw,
        ]);
    }

    public function checkStatus(Payment $payment): PaymentStatus
    {
        // ponytail: no real gateway to query, so a pending mock payment is
        // reported as settled (the stand-in for the bank/e-wallet callback).
        return $payment->payment_status === PaymentStatus::Pending->value
            ? PaymentStatus::Settlement
            : PaymentStatus::from($payment->payment_status);
    }

    private function vaNumber(Order $order, string $bank): string
    {
        return Bank::from($bank)->vaPrefix().str_pad((string) $order->id, 10, '0', STR_PAD_LEFT);
    }

    public function process(Payment $payment, PaymentStatus $result): void
    {
        $this->processor->process($payment, $result);
    }
}
