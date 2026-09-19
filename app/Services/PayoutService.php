<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Models\Event;
use App\Models\OrderItem;
use App\Models\Payout;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayoutService
{
    /**
     * @param  array{bank_name: string, account_number: string, account_holder_name: string}  $bankDetails
     */
    public function requestPayout(int $eventId, int $organizerId, array $bankDetails): Payout
    {
        return DB::transaction(function () use ($eventId, $organizerId, $bankDetails) {
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

            $grossAmount = (float) OrderItem::query()
                ->whereHas('order', fn ($query) => $query->where('status', OrderStatus::Paid->value))
                ->whereHas('ticketCategory', fn ($query) => $query->where('event_id', $eventId))
                ->sum('subtotal');

            $platformFee = round($grossAmount * 0.05, 2);
            $netAmount = (float) $grossAmount - $platformFee;

            return Payout::create([
                'event_id' => $eventId,
                'organizer_id' => $organizerId,
                'gross_amount' => $grossAmount,
                'platform_fee' => $platformFee,
                'net_amount' => $netAmount,
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

    private function triggerDisbursementGateway(): bool
    {
        return true;
    }
}
