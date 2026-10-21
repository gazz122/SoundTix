<?php

namespace App\Jobs;

use App\Enums\RefundGateway;
use App\Enums\RefundStatus;
use App\Models\Refund;
use App\Services\PaymentGateway\MidtransIrisPayoutGateway;
use App\Services\RefundService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Sends a bank-transfer refund out as a Midtrans Iris payout. Only runs once the
 * buyer has told us which account to send the money to.
 */
class ProcessMidtransIrisPayoutJob implements ShouldQueue
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

    public function handle(RefundService $refunds, MidtransIrisPayoutGateway $iris): void
    {
        if ($this->refund->status === RefundStatus::Completed) {
            return;
        }

        if (! $this->refund->hasBankDetails()) {
            $refunds->requestBankDetails($this->refund, RefundGateway::MidtransIrisPayout);

            return;
        }

        if (! $iris->isConfigured()) {
            $refunds->fallBackToManual(
                $this->refund,
                'Akun Midtrans Iris belum aktif, refund dialihkan ke transfer manual.',
            );

            return;
        }

        $refunds->markProcessing($this->refund, RefundGateway::MidtransIrisPayout);

        $payout = $iris->payout($this->refund, (string) $this->refund->refund_key);

        $refunds->markCompleted($this->refund, (string) $payout['reference_no'], $payout);
    }

    public function failed(Throwable $exception): void
    {
        app(RefundService::class)->fallBackToManual(
            $this->refund,
            'Payout otomatis gagal: '.$exception->getMessage(),
        );
    }
}
