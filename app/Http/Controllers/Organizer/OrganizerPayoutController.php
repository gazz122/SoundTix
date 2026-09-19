<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Services\PayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrganizerPayoutController extends Controller
{
    public function __construct(private PayoutService $payouts) {}

    public function store(Request $request, int $eventId): JsonResponse
    {
        $data = $request->validate([
            'bank_name' => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'max:50'],
            'account_holder_name' => ['required', 'string', 'max:150'],
        ]);

        try {
            $payout = $this->payouts->requestPayout($eventId, $request->user()->id, $data);
        } catch (ValidationException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'errors' => $exception->errors()], 422);
        }

        return response()->json(['payout' => $this->serialize($payout)], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Payout $payout): array
    {
        return [
            'id' => $payout->id,
            'event_id' => $payout->event_id,
            'gross_amount' => (float) $payout->gross_amount,
            'platform_fee' => (float) $payout->platform_fee,
            'net_amount' => (float) $payout->net_amount,
            'bank_name' => $payout->bank_name,
            'account_number' => $payout->account_number,
            'account_holder_name' => $payout->account_holder_name,
            'status' => $payout->status->value,
            'status_label' => $payout->status->label(),
            'created_at' => $payout->created_at?->toIso8601String(),
        ];
    }
}
