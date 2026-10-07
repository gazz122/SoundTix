<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Services\PayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
                'refund_deductions' => (float) $payout->refund_deductions,
                'net_amount' => (float) $payout->net_amount,
                'status' => $payout->status->value,
                'status_label' => $payout->status->label(),
                'transfer_proof_url' => $payout->transfer_proof !== null ? Storage::disk('public')->url($payout->transfer_proof) : null,
                'admin_notes' => $payout->admin_notes,
                'processed_at' => $payout->processed_at?->toIso8601String(),
            ]),
        ]);
    }

    public function completePayout(Request $request, int $payoutId): JsonResponse
    {
        $data = $request->validate([
            'transfer_proof' => ['required', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:5120'],
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $path = $request->file('transfer_proof')->store('payouts', 'public');

        try {
            $this->payouts->completePayout($payoutId, $request->user()->id, $path, $data['admin_notes'] ?? null);
        } catch (ValidationException $exception) {
            Storage::disk('public')->delete($path);

            return response()->json(['message' => $exception->getMessage(), 'errors' => $exception->errors()], 422);
        }

        $payout = Payout::find($payoutId);

        return response()->json([
            'payout' => [
                'id' => $payout?->id,
                'status' => $payout?->status?->value,
                'status_label' => $payout?->status?->label(),
                'transfer_proof_url' => $payout?->transfer_proof !== null ? Storage::disk('public')->url($payout->transfer_proof) : null,
                'admin_notes' => $payout?->admin_notes,
                'processed_at' => $payout?->processed_at?->toIso8601String(),
            ],
        ]);
    }
}
