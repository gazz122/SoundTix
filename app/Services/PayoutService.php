<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Enums\RefundStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Payout;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayoutService
{
    public function __construct(private EventCompletionService $events) {}

    /**
     * @param  array{bank_name: string, account_number: string, account_holder_name: string}  $bankDetails
     */
    public function requestPayout(int $eventId, int $organizerId, array $bankDetails): Payout
    {
        return DB::transaction(function () use ($eventId, $organizerId, $bankDetails) {
            $this->events->completeIfPast($eventId);

            /** @var Event|null $event */
            $event = Event::query()
                ->whereKey($eventId)
                ->where('organizer_id', $organizerId)
                ->lockForUpdate()
                ->first();

            if ($event === null) {
                throw ValidationException::withMessages([
                    'event' => ['Event tidak ditemukan.'],
                ]);
            }

            if ($event->status !== EventStatus::Completed->value) {
                throw ValidationException::withMessages([
                    'event' => ['Payout hanya bisa diajukan untuk event yang sudah selesai (COMPLETED).'],
                ]);
            }

            $existing = Payout::query()
                ->where('event_id', $eventId)
                ->where('status', '!=', PayoutStatus::Failed->value)
                ->lockForUpdate()
                ->exists();

            if ($existing) {
                throw ValidationException::withMessages([
                    'event' => ['Payout untuk event ini sudah diajukan.'],
                ]);
            }

            $revenue = $this->revenueForEvent($eventId);

            if ($revenue['net_amount'] <= 0) {
                throw ValidationException::withMessages([
                    'event' => ['Tidak ada dana bersih yang bisa dicairkan untuk event ini.'],
                ]);
            }

            return Payout::create([
                'event_id' => $eventId,
                'organizer_id' => $organizerId,
                'gross_amount' => $revenue['gross_amount'],
                'platform_fee' => $revenue['platform_fee'],
                'refund_deductions' => $revenue['refund_deductions'],
                'net_amount' => $revenue['net_amount'],
                'bank_name' => $bankDetails['bank_name'],
                'account_number' => $bankDetails['account_number'],
                'account_holder_name' => $bankDetails['account_holder_name'],
                'status' => PayoutStatus::Pending,
            ]);
        });
    }

    public function approvePayout(int $payoutId, int $adminId): bool
    {
        return DB::transaction(function () use ($payoutId, $adminId) {
            /** @var Payout|null $payout */
            $payout = Payout::query()->lockForUpdate()->find($payoutId);

            if ($payout === null) {
                throw ValidationException::withMessages([
                    'payout' => ['Payout tidak ditemukan.'],
                ]);
            }

            if ($payout->status !== PayoutStatus::Pending) {
                throw ValidationException::withMessages([
                    'payout' => ['Hanya payout berstatus PENDING yang dapat disetujui.'],
                ]);
            }

            if (! $this->triggerDisbursementGateway()) {
                $payout->update([
                    'status' => PayoutStatus::Failed,
                    'processed_at' => now(),
                ]);

                return false;
            }

            $payout->update([
                'status' => PayoutStatus::Completed,
                'processed_at' => now(),
                'processed_by' => $adminId,
            ]);

            return true;
        });
    }

    public function completePayout(int $payoutId, int $adminId, string $transferProofPath, ?string $adminNotes): void
    {
        DB::transaction(function () use ($payoutId, $adminId, $transferProofPath, $adminNotes) {
            /** @var Payout|null $payout */
            $payout = Payout::query()->lockForUpdate()->find($payoutId);

            if ($payout === null) {
                throw ValidationException::withMessages([
                    'payout' => ['Payout tidak ditemukan.'],
                ]);
            }

            if ($payout->status !== PayoutStatus::Pending) {
                throw ValidationException::withMessages([
                    'payout' => ['Hanya payout berstatus PENDING yang dapat ditandai selesai.'],
                ]);
            }

            $payout->update([
                'status' => PayoutStatus::Completed,
                'transfer_proof' => $transferProofPath,
                'admin_notes' => $adminNotes,
                'processed_at' => now(),
                'processed_by' => $adminId,
            ]);
        });
    }

    private function triggerDisbursementGateway(): bool
    {
        return true;
    }

    /**
     * @return array{gross_amount: float, platform_fee: float, refund_deductions: float, net_amount: float}
     */
    public function revenueForEvent(int $eventId): array
    {
        $grossAmount = (float) Order::query()
            ->where('status', OrderStatus::Paid->value)
            ->whereHas('orderItems.ticketCategory', fn ($query) => $query->where('event_id', $eventId))
            ->sum('total_amount');

        $platformFee = round($grossAmount * $this->platformFeeRate(), 2);

        $refundDeductions = (float) Refund::query()
            ->where('status', RefundStatus::Completed->value)
            ->whereHas('order.orderItems.ticketCategory', fn ($query) => $query->where('event_id', $eventId))
            ->sum('amount');

        $netAmount = max(0, $grossAmount - $platformFee - $refundDeductions);

        return [
            'gross_amount' => $grossAmount,
            'platform_fee' => $platformFee,
            'refund_deductions' => $refundDeductions,
            'net_amount' => $netAmount,
        ];
    }

    public function platformFeeRate(): float
    {
        return (float) config('payout.platform_fee_rate', 0.05);
    }
}
