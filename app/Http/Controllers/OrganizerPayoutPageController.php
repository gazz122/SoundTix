<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Services\EventCompletionService;
use App\Services\PayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrganizerPayoutPageController extends Controller
{
    public function __construct(
        private PayoutService $payouts,
        private EventCompletionService $events,
    ) {}

    public function index(Request $request): Response
    {
        $events = Event::query()
            ->where('organizer_id', $request->user()->id)
            ->whereIn('status', [EventStatus::Completed->value, EventStatus::Published->value])
            ->with('payouts')
            ->orderByDesc('event_date')
            ->get();

        $items = $events
            ->map(function (Event $event) {
                $past = $event->event_date?->isPast() ?? false;
                $completed = $event->status === EventStatus::Completed->value;
                $revenue = $this->payouts->revenueForEvent($event->id);
                $latestPayout = $event->payouts->sortByDesc('id')->first();

                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'slug' => $event->slug,
                    'event_date' => $event->event_date?->format('Y-m-d'),
                    'status' => $event->status,
                    'status_label' => EventStatus::from($event->status)->label(),
                    'eligible' => $completed || $past,
                    ...$revenue,
                    'payout' => $latestPayout ? [
                        'id' => $latestPayout->id,
                        'gross_amount' => (float) $latestPayout->gross_amount,
                        'platform_fee' => (float) $latestPayout->platform_fee,
                        'refund_deductions' => (float) $latestPayout->refund_deductions,
                        'net_amount' => (float) $latestPayout->net_amount,
                        'status' => $latestPayout->status->value,
                        'status_label' => $latestPayout->status->label(),
                        'bank_name' => $latestPayout->bank_name,
                        'account_number' => $latestPayout->account_number,
                        'account_holder_name' => $latestPayout->account_holder_name,
                        'transfer_proof_url' => $latestPayout->transfer_proof !== null ? Storage::disk('public')->url($latestPayout->transfer_proof) : null,
                        'admin_notes' => $latestPayout->admin_notes,
                        'created_at' => $latestPayout->created_at?->format('Y-m-d H:i'),
                        'processed_at' => $latestPayout->processed_at?->format('Y-m-d H:i'),
                    ] : null,
                ];
            })
            ->values()
            ->all();

        return Inertia::render('Organizer/Payouts/Index', ['events' => $items]);
    }

    public function withdraw(Request $request, int $eventId): RedirectResponse
    {
        $data = $request->validate([
            'bank_name' => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'max:50'],
            'account_holder_name' => ['required', 'string', 'max:150'],
        ]);

        $this->events->completeIfPast($eventId);

        try {
            $this->payouts->requestPayout($eventId, $request->user()->id, $data);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->with('error', $exception->getMessage());
        }

        return redirect()->route('organizer.payouts.index')
            ->with('success', 'Pengajuan penarikan dana berhasil dikirim. Uang akan ditransfer setelah disetujui admin.');
    }
}
