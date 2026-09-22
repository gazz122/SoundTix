<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Enums\RefundStatus;
use App\Enums\TicketStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Refund;
use App\Notifications\SoundTixNotification;
use App\Repositories\TicketCategoryRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminRefundPageController extends Controller
{
    public function __construct(private TicketCategoryRepository $categories) {}

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
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'event_date' => $event->event_date?->format('d M Y'),
                'cancellation_reason' => $event->cancellation_reason,
                'organizer' => $event->organizer->name,
                'paid_orders_count' => $event->paid_orders_count,
                'submitted_at' => $event->updated_at?->format('d M Y H:i'),
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/Refunds/Index', [
            'refunds' => $refunds,
            'cancellations' => $cancellations,
        ]);
    }

    public function approve(Request $request, Refund $refund): RedirectResponse
    {
        if ($refund->status !== RefundStatus::Pending) {
            return redirect()->route('admin.refunds.index')->with(
                'error',
                'Hanya refund berstatus menunggu yang dapat disetujui.',
            );
        }

        $order = $refund->order->loadMissing(['orderItems', 'orderItems.tickets']);

        DB::transaction(function () use ($refund, $order) {
            $refund->update(['status' => RefundStatus::Completed->value]);

            foreach ($order->orderItems as $item) {
                $this->categories->incrementAvailable($item->ticket_category_id, $item->quantity);

                foreach ($item->tickets as $ticket) {
                    $ticket->update(['status' => TicketStatus::Cancelled->value]);
                }
            }
        });

        $order->customer->notify(new SoundTixNotification([
            'title' => 'Refund disetujui',
            'message' => "Refund pesanan {$order->order_code} telah disetujui. Dana akan dikembalikan melalui metode pembayaran awal.",
            'type' => 'refund_completed',
            'action_url' => route('orders.show', $order->order_code),
            'action_label' => 'Lihat pesanan',
        ]));

        return redirect()->route('admin.refunds.index')->with('success', 'Refund disetujui. Tiket dibatalkan dan stok dikembalikan.');
    }

    public function approveCancellation(Request $request, int $eventId): RedirectResponse
    {
        $event = $this->findPendingCancellation($eventId);

        $orders = Order::query()
            ->where('status', OrderStatus::Paid->value)
            ->whereHas('orderItems.ticketCategory', fn ($query) => $query->where('event_id', $event->id))
            ->with(['orderItems.ticketCategory', 'orderItems.tickets', 'customer:id,name,email'])
            ->get();

        DB::transaction(function () use ($event, $orders) {
            $event->update(['status' => EventStatus::Cancelled->value]);

            foreach ($orders as $order) {
                $order->update(['status' => OrderStatus::Cancelled->value]);

                $order->refunds()->create([
                    'amount' => $order->grand_total,
                    'status' => RefundStatus::Completed->value,
                    'reason' => 'Event dibatalkan: '.$event->cancellation_reason,
                ]);

                foreach ($order->orderItems as $item) {
                    $this->categories->incrementAvailable($item->ticket_category_id, $item->quantity);

                    foreach ($item->tickets as $ticket) {
                        $ticket->update(['status' => TicketStatus::Cancelled->value]);
                    }
                }
            }
        });

        foreach ($orders as $order) {
            $order->customer->notify(new SoundTixNotification([
                'title' => 'Event dibatalkan',
                'message' => "Event \"{$event->title}\" dibatalkan. Pesanan {$order->order_code} otomatis di-refund.",
                'type' => 'event_cancelled',
                'action_url' => route('orders.show', $order->order_code),
                'action_label' => 'Lihat pesanan',
            ]));
        }

        return redirect()->route('admin.refunds.index')
            ->with('success', "Event \"{$event->title}\" dibatalkan. Semua pesanan lunas di-refund otomatis.");
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
