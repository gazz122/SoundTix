<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Enums\RefundStatus;
use App\Jobs\ProcessEventCancellationRefundsJob;
use App\Models\Event;
use App\Models\Order;
use App\Models\Refund;
use App\Notifications\SoundTixNotification;
use App\Services\RefundService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminRefundPageController extends Controller
{
    public function __construct(private RefundService $refunds) {}

    public function index(Request $request): Response
    {
        $refunds = $this->filteredRefunds($request)
            ->with([
                'order.customer:id,name,email',
                'order.orderItems.ticketCategory.event:id,title,event_date',
            ])
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Refund $refund) => [
                'id' => $refund->id,
                'amount' => (float) $refund->amount,
                'status' => $refund->status->value,
                'status_label' => $refund->status->label(),
                'reason' => $refund->reason,
                'failure_reason' => $refund->failureMessage(),
                'bank_name' => $refund->bank_name,
                'account_number' => $refund->account_number,
                'account_holder_name' => $refund->account_holder_name,
                'notes' => $refund->notes,
                'refund_gateway' => $refund->refund_gateway?->value,
                'refund_gateway_label' => $refund->refund_gateway?->label(),
                'proof_of_transfer_url' => $refund->proof_of_transfer !== null
                    ? Storage::disk('public')->url($refund->proof_of_transfer)
                    : null,
                'processed_at' => $refund->processed_at?->format('d M Y H:i'),
                'can_complete' => $refund->canBeCompleted(),
                'created_at' => $refund->created_at?->format('d M Y H:i'),
                'order_code' => $refund->order->order_code,
                'customer_name' => $refund->order->customer->name,
                'customer_email' => $refund->order->customer->email,
                'event_title' => $refund->order->orderItems->first()?->ticketCategory?->event?->title,
            ]);

        $transferable = $this->transferableRefunds($request);

        $cancellations = Event::query()
            ->where('status', EventStatus::PendingCancellation->value)
            ->with('organizer:id,name')
            ->select('events.*')
            ->selectSub(
                Order::query()
                    ->where('status', OrderStatus::Paid->value)
                    ->whereHas('orderItems.ticketCategory', fn ($query) => $query->whereColumn('ticket_categories.event_id', 'events.id'))
                    ->selectRaw('count(*)'),
                'paid_orders_count',
            )
            ->selectSub(
                Order::query()
                    ->where('status', OrderStatus::Paid->value)
                    ->whereHas('orderItems.ticketCategory', fn ($query) => $query->whereColumn('ticket_categories.event_id', 'events.id'))
                    ->selectRaw('coalesce(sum(grand_total), 0)'),
                'refund_total',
            )
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'event_date' => $event->event_date?->format('d M Y'),
                'cancellation_reason' => $event->cancellation_reason,
                'organizer' => $event->organizer->name,
                'paid_orders_count' => (int) $event->paid_orders_count,
                'refund_total' => (float) $event->refund_total,
                'submitted_at' => $event->updated_at?->format('d M Y H:i'),
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/Refunds/Index', [
            'refunds' => $refunds,
            'cancellations' => $cancellations,
            'filters' => [
                'status' => RefundStatus::tryFrom((string) $request->input('status', ''))?->value,
                'search' => trim((string) $request->input('search', '')),
                'ready' => $request->boolean('ready'),
            ],
            // Drives the bulk action's confirmation, so the admin sees how many
            // transfers one click will settle before committing.
            'bulk' => [
                'count' => $transferable->count(),
                'total' => (float) $transferable->sum('amount'),
            ],
        ]);
    }

    /**
     * Download the current selection as a bank bulk-transfer file.
     *
     * Banks settle a hundred transfers uploaded in one file; the alternative is
     * an admin typing every account number by hand.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->transferableRefunds($request)->with('order:id,order_code');

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');

            // The remark carries the order code so every transfer in the file can
            // be traced back to the refund it settles.
            fputcsv($handle, ['bank_name', 'account_number', 'account_holder_name', 'amount', 'remark']);

            $query->chunkById(500, function ($refunds) use ($handle): void {
                foreach ($refunds as $refund) {
                    fputcsv($handle, [
                        $refund->bank_name,
                        $refund->account_number,
                        $refund->account_holder_name,
                        number_format((float) $refund->amount, 2, '.', ''),
                        $refund->order?->order_code,
                    ]);
                }
            });

            fclose($handle);
        }, 'refund-transfer-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Settle every refund the current filter selects.
     *
     * Used after a bank file upload: the money left the account in one batch, so
     * the records are closed in one batch too.
     */
    public function completeBatch(Request $request): RedirectResponse
    {
        $completed = $this->refunds->completeBatch(
            $this->transferableRefunds($request)->lazyById(500),
        );

        return redirect()
            ->route('admin.refunds.index', $request->only(['status', 'search', 'ready']))
            ->with('success', "{$completed} refund ditandai selesai.");
    }

    public function complete(Request $request, Refund $refund): RedirectResponse
    {
        $validated = $request->validate([
            'bank_name' => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'max:50', 'regex:/^[0-9]+$/'],
            'account_holder_name' => ['required', 'string', 'max:100'],
            'proof_of_transfer' => ['nullable', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $refund->canBeCompleted()) {
            return redirect()->route('admin.refunds.index')->with(
                'error',
                'Refund ini sudah selesai dan tidak dapat diproses ulang.',
            );
        }

        $proof = $request->file('proof_of_transfer');

        $this->refunds->completeManually($refund, [
            'bank_name' => $validated['bank_name'],
            'account_number' => $validated['account_number'],
            'account_holder_name' => $validated['account_holder_name'],
            'notes' => $validated['notes'] ?? null,
            'proof_of_transfer' => $proof?->store('refund-proofs', 'public'),
        ]);

        return redirect()->route('admin.refunds.index')->with(
            'success',
            "Refund pesanan {$refund->order->order_code} ditandai selesai.",
        );
    }

    public function approveCancellation(Request $request, int $eventId): RedirectResponse
    {
        $event = $this->findPendingCancellation($eventId);

        // Thousands of refunds must not be opened inside a web request, so the
        // cancel-and-refund work is handed to the queue. The job notifies the
        // buyers and prints the refunds.
        ProcessEventCancellationRefundsJob::dispatch($event);

        return redirect()->route('admin.refunds.index')->with(
            'success',
            "Pembatalan event \"{$event->title}\" disetujui. Refund sedang dibuat dan diproses.",
        );
    }

    public function rejectCancellation(Request $request, int $eventId): RedirectResponse
    {
        $event = $this->findPendingCancellation($eventId);

        $event->update([
            'status' => EventStatus::Published->value,
            'cancellation_reason' => null,
        ]);

        $event->organizer->notify(new SoundTixNotification([
            'title' => 'Pembatalan event ditolak',
            'message' => "Pengajuan pembatalan event \"{$event->title}\" ditolak admin. Event tetap berlangsung.",
            'type' => 'event_cancellation_rejected',
            'action_url' => route('organizer.events.show', $event->id),
            'action_label' => 'Lihat event',
        ]));

        return redirect()->route('admin.refunds.index')
            ->with('success', "Pengajuan pembatalan event \"{$event->title}\" ditolak.");
    }

    private function findPendingCancellation(int $eventId): Event
    {
        return Event::with('organizer')->where('status', EventStatus::PendingCancellation->value)->findOrFail($eventId);
    }

    /**
     * @return Builder<Refund>
     */
    private function filteredRefunds(Request $request): Builder
    {
        // input(), not query(): the batch actions post the same filters back.
        $status = RefundStatus::tryFrom((string) $request->input('status', ''));
        $search = trim((string) $request->input('search', ''));

        return Refund::query()
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($request->boolean('ready'), fn (Builder $query) => $query
                ->whereNotNull('bank_name')
                ->whereNotNull('account_number')
                ->whereNotNull('account_holder_name'))
            ->when($search !== '', fn (Builder $query) => $query->whereHas(
                'order',
                fn (Builder $order) => $order->where('order_code', 'like', "%{$search}%"),
            ));
    }

    /**
     * Refunds the admin can settle as one batch: the destination is known and no
     * gateway is mid-flight. Mirrors Refund::canBeCompleted().
     *
     * @return Builder<Refund>
     */
    private function transferableRefunds(Request $request): Builder
    {
        return $this->filteredRefunds($request)
            ->whereNotIn('status', [RefundStatus::Completed->value, RefundStatus::Processing->value])
            ->whereNotNull('bank_name')
            ->whereNotNull('account_number')
            ->whereNotNull('account_holder_name');
    }
}
