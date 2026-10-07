<?php

namespace App\Http\Controllers;

use App\Models\Payout;
use App\Services\PayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminPayoutPageController extends Controller
{
    public function __construct(private PayoutService $payouts) {}

    public function index(): Response
    {
        $payouts = Payout::query()
            ->with(['event:id,title', 'organizer:id,name'])
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Admin/Payouts/Index', [
            'payouts' => $payouts->map(fn (Payout $payout) => [
                'id' => $payout->id,
                'event' => $payout->event?->title,
                'organizer' => $payout->organizer?->name,
                'gross_amount' => (float) $payout->gross_amount,
                'platform_fee' => (float) $payout->platform_fee,
                'refund_deductions' => (float) $payout->refund_deductions,
                'net_amount' => (float) $payout->net_amount,
                'bank_name' => $payout->bank_name,
                'account_number' => $payout->account_number,
                'account_holder_name' => $payout->account_holder_name,
                'status' => $payout->status->value,
                'status_label' => $payout->status->label(),
                'transfer_proof_url' => $payout->transfer_proof !== null ? Storage::disk('public')->url($payout->transfer_proof) : null,
                'admin_notes' => $payout->admin_notes,
                'created_at' => $payout->created_at?->format('Y-m-d H:i'),
                'processed_at' => $payout->processed_at?->format('Y-m-d H:i'),
            ])->values()->all(),
        ]);
    }

    public function complete(Request $request, Payout $payout): RedirectResponse
    {
        $data = $request->validate([
            'transfer_proof' => ['required', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:5120'],
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $path = $request->file('transfer_proof')->store('payouts', 'public');

        try {
            $this->payouts->completePayout($payout->id, $request->user()->id, $path, $data['admin_notes'] ?? null);
        } catch (ValidationException $exception) {
            Storage::disk('public')->delete($path);

            return back()->withErrors($exception->errors())->with('error', $exception->getMessage());
        }

        return redirect()->route('admin.payouts.index')
            ->with('success', 'Transfer dana ditandai selesai dan bukti transfer diunggah.');
    }
}
