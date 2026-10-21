<?php

namespace App\Jobs;

use App\Enums\RefundGateway;
use App\Enums\RefundStatus;
use App\Models\Refund;
use App\Services\PaymentGateway\PaymentGatewayInterface;
use App\Services\RefundService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Pushes a settled payment back through the Midtrans refund API.
 */
class ProcessMidtransApiRefundJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public Refund $refund) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(RefundService $refunds, PaymentGatewayInterface $gateway): void
    {
        $payment = $this->refund->payment;

        if ($payment === null) {
            $refunds->fallBackToManual(
                $this->refund,
                'Pembayaran asal tidak ditemukan, refund dialihkan ke transfer manual.',
            );

            return;
        }

        if ($this->refund->status === RefundStatus::Completed) {
            return;
        }

        $refunds->markProcessing($this->refund, RefundGateway::MidtransRefundApi);

        $result = $gateway->refund(
            $payment,
            (int) round((float) $this->refund->amount),
            (string) $this->refund->refund_key,
            (string) $this->refund->reason,
        );

        $referenceId = data_get($result, 'raw.refund_key');

        $refunds->markCompleted(
            $this->refund,
            is_string($referenceId) ? $referenceId : (string) $this->refund->refund_key,
            (array) ($result['raw'] ?? []),
        );
    }

    /**
     * The retries are spent. Rather than leave the money stranded, hand the
     * refund to an admin who can verify the gateway dashboard and transfer it
     * by hand.
     */
    public function failed(Throwable $exception): void
    {
        app(RefundService::class)->fallBackToManual(
            $this->refund,
            'Refund otomatis gagal: '.$exception->getMessage(),
        );
    }
}
