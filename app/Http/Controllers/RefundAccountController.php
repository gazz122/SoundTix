<?php

namespace App\Http\Controllers;

use App\Enums\RefundGateway;
use App\Enums\RefundStatus;
use App\Jobs\ProcessMidtransIrisPayoutJob;
use App\Models\Refund;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RefundAccountController extends Controller
{
    public function show(Request $request, Refund $refund): Response
    {
        $refund = $this->refundOwnedBy($request, $refund);

        return Inertia::render('Refunds/Account', [
            'refund' => [
                'id' => $refund->id,
                'amount' => (float) $refund->amount,
                'status' => $refund->status->value,
                'status_label' => $refund->status->label(),
                'order_code' => $refund->order?->order_code,
                'event_title' => $refund->order?->orderItems->first()?->ticketCategory?->event?->title,
                'bank_name' => $refund->bank_name,
                'account_number' => $refund->account_number,
                'account_holder_name' => $refund->account_holder_name,
                'is_automated_payout' => $refund->refund_gateway === RefundGateway::MidtransIrisPayout,
                'can_submit' => $refund->status !== RefundStatus::Completed,
            ],
        ]);
    }

    public function store(Request $request, Refund $refund): RedirectResponse
    {
        $refund = $this->refundOwnedBy($request, $refund);

        $validated = $request->validate([
            'bank_name' => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'max:50', 'regex:/^[0-9]+$/'],
            'account_holder_name' => ['required', 'string', 'max:100'],
        ]);

        if ($refund->status === RefundStatus::Completed) {
            return back()->with('error', 'Refund ini sudah selesai dan rekeningnya tidak bisa diubah lagi.');
        }

        // The details are stored for both lanes: an automated payout reads them
        // from here, and a manual refund shows them to the admin so the transfer
        // goes to the account the buyer actually named.
        $refund->update($validated);

        if ($refund->refund_gateway === RefundGateway::MidtransIrisPayout) {
            ProcessMidtransIrisPayoutJob::dispatch($refund);
        } elseif ($refund->requiresBankDetails()) {
            // Nothing automated will pick this up, and the admin now has
            // everything needed to transfer, so the refund rejoins the queue.
            $refund->update(['status' => RefundStatus::Pending]);
        }

        return back()->with(
            'success',
            'Data rekening tersimpan. Dana akan dikembalikan ke rekening tersebut.',
        );
    }

    /**
     * A refund is only visible to the buyer whose order it belongs to. A missing
     * refund and someone else's refund look the same on purpose.
     */
    private function refundOwnedBy(Request $request, Refund $refund): Refund
    {
        $refund->loadMissing('order.orderItems.ticketCategory.event');

        abort_unless($refund->order?->customer_id === $request->user()?->id, 404);

        return $refund;
    }
}
