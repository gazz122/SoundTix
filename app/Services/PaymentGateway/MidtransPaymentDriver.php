<?php

namespace App\Services\PaymentGateway;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Order;
use App\Models\Payment;
use App\Repositories\PaymentRepository;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use UnexpectedValueException;

class MidtransPaymentDriver implements PaymentGatewayInterface
{
    public function __construct(
        private PaymentRepository $payments,
        private PaymentProcessor $processor,
    ) {}

    public function createPayment(Order $order, PaymentType $method, ?string $bank = null): Payment
    {
        $transactionId = (string) Str::uuid();
        $params = [
            'transaction_details' => [
                'order_id' => $transactionId,
                'gross_amount' => (int) round((float) $order->grand_total),
            ],
            'customer_details' => [
                'first_name' => $order->customer?->name ?? 'SoundTix Customer',
                'email' => $order->customer?->email,
            ],
            'callbacks' => [
                'finish' => route('orders.show', $order->order_code),
                'unfinish' => route('orders.show', $order->order_code),
                'error' => route('orders.show', $order->order_code),
            ],
            'enabled_payments' => $method === PaymentType::Qris ? ['qris'] : ['bank_transfer'],
        ];

        if ($method === PaymentType::BankTransfer && $bank !== null) {
            $params['bank_transfer'] = ['bank' => $bank];
        }

        $webhookUrl = config('services.midtrans.webhook_url');
        if (is_string($webhookUrl) && trim($webhookUrl) !== '') {
            $params['override_notification_urls'] = [$webhookUrl];
        }

        $response = $this->client()
            ->post($this->baseUrl().'/snap/v1/transactions', $params)
            ->throw()
            ->json();

        if (! is_array($response)
            || ! is_string($response['token'] ?? null)
            || ! is_string($response['redirect_url'] ?? null)) {
            throw new UnexpectedValueException('Midtrans did not return a Snap token and redirect URL.');
        }

        return $this->payments->create([
            'order_id' => $order->id,
            'transaction_id' => $transactionId,
            'payment_type' => $method->value,
            'gross_amount' => $order->grand_total,
            'payment_status' => PaymentStatus::Pending->value,
            'raw_response' => [...$response, 'order_id' => $transactionId],
        ]);
    }

    public function checkStatus(Payment $payment): PaymentStatus
    {
        $response = $this->client()
            ->get($this->baseUrl().'/v2/'.rawurlencode($payment->transaction_id).'/status')
            ->throw()
            ->json();

        if (! is_array($response)) {
            throw new UnexpectedValueException('Midtrans returned an invalid transaction status response.');
        }

        return match ($response['transaction_status'] ?? null) {
            'settlement' => PaymentStatus::Settlement,
            'capture' => match ($response['fraud_status'] ?? null) {
                'accept' => PaymentStatus::Settlement,
                'deny' => PaymentStatus::Failure,
                default => PaymentStatus::Pending,
            },
            'pending' => PaymentStatus::Pending,
            'deny', 'cancel' => PaymentStatus::Failure,
            'expire' => PaymentStatus::Expire,
            default => PaymentStatus::from($payment->payment_status),
        };
    }

    public function process(Payment $payment, PaymentStatus $result): void
    {
        $this->processor->process($payment, $result);
    }

    private function client(): PendingRequest
    {
        $serverKey = config('services.midtrans.server_key');
        if (! is_string($serverKey) || $serverKey === '') {
            throw new UnexpectedValueException('The Midtrans server key is not configured.');
        }

        return Http::withBasicAuth($serverKey, '')
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15);
    }

    private function baseUrl(): string
    {
        return filter_var(config('services.midtrans.is_production'), FILTER_VALIDATE_BOOL)
            ? 'https://app.midtrans.com'
            : 'https://app.sandbox.midtrans.com';
    }
}
