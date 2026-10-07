<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Enums\RefundStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Refund;
use App\Notifications\SoundTixNotification;
use App\Services\RefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AdminRefundPageController extends Controller
{
    public function __construct(private RefundService $refunds) {}

    public function index(): Response
    {
        $refunds = Refund::query()
            ->with([
                'order.customer:id,name,email',
                'order.orderItems.ticketCategory.event:id,title,event_date',
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Refund $refund) => [
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
            ])
            ->values()
            ->all();

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
        ]);
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

        $this->refunds->cancelEventAndRefund($event, (string) $event->cancellation_reason);

        $orders = Order::query()
            ->where('status', OrderStatus::Cancelled)
            ->whereHas('orderItems.ticketCategory', fn ($query) => $query->where('event_id', $event->id))
            ->with('customer:id,name,email')
            ->get();

        foreach ($orders as $order) {
            $order->customer->notify(new SoundTixNotification([
                'title' => 'Event dibatalkan',
                'message' => "Event \"{$event->title}\" dibatalkan. Dana pesanan {$order->order_code} akan dikembalikan oleh admin.",
                'type' => 'event_cancelled',
                'action_url' => route('orders.show', $order->order_code),
                'action_label' => 'Lihat pesanan',
            ]));
        }

        $pending = Refund::query()
            ->where('status', RefundStatus::Pending)
            ->whereHas('order.orderItems.ticketCategory', fn ($query) => $query->where('event_id', $event->id))
            ->count();

        return redirect()->route('admin.refunds.index')->with(
            'success',
            "Event \"{$event->title}\" dibatalkan. {$pending} refund menunggu transfer manual oleh admin."
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
}
