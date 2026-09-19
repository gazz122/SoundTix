<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PayoutStatus;
use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Services\PayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminPayoutController extends Controller
{
    public function __construct(private PayoutService $payouts) {}

    public function index(): JsonResponse
    {
        $payouts = Payout::query()
            ->with(['event:id,title', 'organizer:id,name'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'payouts' => $payouts->map(fn (Payout $payout) => [
                'id' => $payout->id,
                'event' => $payout->event?->title,
                'organizer' => $payout->organizer?->name,
                'gross_amount' => (float) $payout->gross_amount,
                'platform_fee' => (float) $payout->platform_fee,
                'net_amount' => (float) $payout->net_amount,
                'status' => $payout->status->value,
                'status_label' => $payout->status->label(),
                'processed_at' => $payout->processed_at?->toIso8601String(),
            ]),
        ]);
    }

    public function approve(Request $request, int $payoutId): JsonResponse
    {
        try {
            $approved = $this->payouts->approvePayout($payoutId, $request->user()->id);
        } catch (ValidationException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'errors' => $exception->errors()], 422);
        }

        $payout = Payout::find($payoutId);

        return response()->json([
            'approved' => $approved,
            'payout' => [
                'id' => $payout?->id,
                'status' => $payout?->status?->value ?? PayoutStatus::Failed->value,
                'status_label' => $payout?->status?->label(),
                'processed_at' => $payout?->processed_at?->toIso8601String(),
            ],
        ]);
    }
}
