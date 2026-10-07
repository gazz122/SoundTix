<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\TicketStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Notifications\SoundTixNotification;
use App\Repositories\EventRepository;
use App\Repositories\OrderRepository;
use App\Repositories\TicketCategoryRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(
        private EventRepository $events,
        private OrderRepository $orders,
        private TicketCategoryRepository $categories,
    ) {}

    public function index(Request $request): Response
    {
        $orders = $this->orders->forUser($request->user()->id)
            ->map(fn (Order $order) => $this->serialize($order))
            ->values()
            ->all();

        return Inertia::render('Orders/Index', ['orders' => $orders]);
    }

    public function show(Request $request, Order $order): Response
    {
        $order = $this->guardOwned($request, $order);

        return Inertia::render('Orders/Show', ['order' => $this->serialize($this->orders->loadOverview($order))]);
    }

    public function store(Request $request, string $slug): RedirectResponse
    {
        $event = $this->events->findPublishedBySlug($slug);

        if (! $request->user()->is_verified) {
            return back()->withErrors([
                'items' => 'Verifikasi email Anda terlebih dahulu sebelum membeli tiket.',
            ]);
        }

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.category_id' => ['required', 'integer', Rule::exists('ticket_categories', 'id')],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        $errors = $this->assertOrderable($event, $data['items']);
        if ($errors !== []) {
            return back()->withErrors($errors)->withInput();
        }

        $order = $this->createOrder($event, $request->user(), $data['items']);
        $request->user()->notify(new SoundTixNotification([
            'title' => 'Pesanan berhasil dibuat',
            'message' => "Pesanan {$order->order_code} menunggu pembayaran selama 15 menit.",
            'type' => 'order_created',
            'action_url' => route('orders.show', $order->order_code),
            'action_label' => 'Bayar sekarang',
        ]));

        return redirect()
            ->route('orders.show', $order->order_code)
            ->with('success', 'Pesanan berhasil dibuat. Selesaikan pembayaran dalam 15 menit.');
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $order = $this->guardOwned($request, $order);
        $order->loadMissing('orderItems');

        if ($order->status !== OrderStatus::Pending->value) {
            return redirect()->route('orders.show', $order->order_code)->with(
                'error',
                'Hanya pesanan dengan status menunggu pembayaran yang dapat dibatalkan.',
            );
        }

        DB::transaction(function () use ($order) {
            $order->update(['status' => OrderStatus::Cancelled->value]);

            foreach ($order->orderItems as $item) {
                $this->categories->incrementAvailable($item->ticket_category_id, $item->quantity);
            }
        });

        return redirect()->route('orders.show', $order->order_code)->with('success', 'Pesanan dibatalkan dan stok tiket dikembalikan.');
    }

    private function guardOwned(Request $request, Order $order): Order
    {
        abort_if($order->customer_id !== $request->user()->id, 404);

        return $order;
    }

    /**
     * @param  array<int, array{category_id: int, quantity: int}>  $items
     * @return array<string, string>
     */
    private function assertOrderable(Event $event, array $items): array
    {
        $errors = [];
        $totalQuantity = 0;

        foreach ($items as $index => $item) {
            $category = $this->categories->optionalInEvent($event, $item['category_id']);

            if ($category === null) {
                $errors['items.invalid'] = 'Kategori tiket tidak sesuai dengan event ini.';

                continue;
            }

            $key = "items.{$index}.quantity";

            if ($item['quantity'] > $category->max_per_order) {
                $errors[$key] = "Maksimal {$category->max_per_order} tiket untuk {$category->name}.";
            }

            if ($item['quantity'] > $category->available_quantity) {
                $errors[$key] = "Stok {$category->name} tidak mencukupi (sisa {$category->available_quantity}).";
            }

            $totalQuantity += $item['quantity'];
        }

        if ($totalQuantity === 0) {
            $errors['items'] = 'Pilih minimal satu tiket.';
        } elseif ($totalQuantity > 5) {
            $errors['items'] = 'Maksimal 5 tiket per transaksi.';
        }

        return $errors;
    }

    /**
     * @param  array<int, array{category_id: int, quantity: int}>  $items
     */
    private function createOrder(Event $event, User $user, array $items): Order
    {
        $categoryIds = array_column($items, 'category_id');

        return DB::transaction(function () use ($user, $event, $items, $categoryIds) {
            /** @var Collection<int, TicketCategory> $categories */
            $categories = $this->categories->lockForUpdateByIds($event, $categoryIds);

            $totalAmount = 0;

            foreach ($items as $item) {
                $category = $categories->get($item['category_id']);

                if ($category === null || $category->available_quantity < $item['quantity']) {
                    abort(422, 'Stok tiket tidak mencukupi.');
                }

                $category->decrement('available_quantity', $item['quantity']);
                $totalAmount += (float) $category->price * $item['quantity'];
            }

            $order = $this->orders->createPending($user->id, [
                'order_code' => $this->orderCode(),
                'total_amount' => $totalAmount,
                'admin_fee' => 0,
                'grand_total' => $totalAmount,
                'status' => OrderStatus::Pending->value,
                'expires_at' => now()->addMinutes(15),
            ]);

            foreach ($items as $item) {
                /** @var TicketCategory $category */
                $category = $categories->get($item['category_id']);

                $order->orderItems()->create([
                    'ticket_category_id' => $category->id,
                    'quantity' => $item['quantity'],
                    'price_per_unit' => $category->price,
                    'subtotal' => $category->price * $item['quantity'],
                ]);
            }

            return $order;
        });
    }

    private function orderCode(): string
    {
        do {
            $code = 'STX-'.now()->format('Ymd').'-'.Str::upper(Str::random(4));
        } while ($this->orders->orderCodeExists($code));

        return $code;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Order $order, bool $loadItems = false): array
    {
        $items = $order->orderItems->map(fn ($item) => [
            'id' => $item->id,
            'category' => $item->ticketCategory->name,
            'price_per_unit' => (float) $item->price_per_unit,
            'quantity' => $item->quantity,
            'subtotal' => (float) $item->subtotal,
            'event' => $item->ticketCategory->event ? [
                'id' => $item->ticketCategory->event->id,
                'title' => $item->ticketCategory->event->title,
                'slug' => $item->ticketCategory->event->slug,
                'event_date' => $this->ticketEventDate($item->ticketCategory),
                'ticket_day_label' => $this->ticketDayLabel($item->ticketCategory),
            ] : null,
        ])->values()->all();

        return [
            'order_code' => $order->order_code,
            'status' => $order->status,
            'status_label' => OrderStatus::from($order->status)->label(),
            'total_amount' => (float) $order->total_amount,
            'admin_fee' => (float) $order->admin_fee,
            'grand_total' => (float) $order->grand_total,
            'created_at' => $order->created_at?->format('d M Y H:i'),
            'expires_at' => $order->expires_at?->toIso8601String(),
            'paid_at' => $order->paid_at?->format('d M Y H:i'),
            'tickets' => $order->status === OrderStatus::Paid->value
                ? $order->orderItems->flatMap(fn ($item) => $item->tickets->map(
                    fn (Ticket $ticket) => [
                        'id' => $ticket->id,
                        'ticket_code' => $ticket->ticket_code,
                        'status' => $ticket->status,
                        'status_label' => TicketStatus::from($ticket->status)->label(),
                        'qr_payload' => $ticket->qr_payload,
                        'category' => $item->ticketCategory->name,
                        'event_title' => $item->ticketCategory->event?->title,
                        'event_date' => $this->ticketEventDate($item->ticketCategory),
                        'ticket_day_label' => $this->ticketDayLabel($item->ticketCategory),
                        'venue' => $item->ticketCategory->event
                            ? sprintf('%s, %s', $item->ticketCategory->event->venue_name, $item->ticketCategory->event->city)
                            : null,
                        'start_time' => $item->ticketCategory->event?->start_time,
                    ],
                ))->values()->all()
                : [],
            'items' => $items,
            'payments' => $order->payments
                ->sortByDesc('created_at')
                ->values()
                ->map(fn ($payment) => [
                    'id' => $payment->id,
                    'transaction_id' => $payment->transaction_id,
                    'payment_type' => $payment->payment_type,
                    'method_label' => PaymentType::from($payment->payment_type)->label(),
                    'gross_amount' => (float) $payment->gross_amount,
                    'status' => $payment->payment_status,
                    'status_label' => PaymentStatus::from($payment->payment_status)->label(),
                    'bank' => $payment->raw_response['bank'] ?? null,
                    'va_number' => $payment->raw_response['va_number'] ?? null,
                    'qr_string' => $payment->raw_response['qr_string'] ?? null,
                    'created_at' => $payment->created_at?->format('d M Y H:i'),
                ])
                ->all(),
            'refunds' => $order->refunds
                ->sortByDesc('created_at')
                ->values()
                ->map(fn ($refund) => [
                    'id' => $refund->id,
                    'amount' => (float) $refund->amount,
                    'status' => $refund->status->value,
                    'status_label' => $refund->status->label(),
                    'reason' => $refund->reason,
                    'created_at' => $refund->created_at?->format('d M Y H:i'),
                ])
                ->all(),
        ];
    }

    private function ticketEventDate(TicketCategory $category): ?string
    {
        $eventDate = $category->access_type === 'SINGLE_DAY'
            ? $category->eventDay?->date
            : $category->event?->event_date;

        return $eventDate?->format('Y-m-d');
    }

    private function ticketDayLabel(TicketCategory $category): ?string
    {
        if ($category->access_type === 'SINGLE_DAY' && $category->eventDay !== null) {
            return sprintf('Day %d', $category->eventDay->day_number);
        }

        return $category->access_type === 'ALL_DAYS'
            && $category->event?->duration_type === 'multi_day'
            ? 'Semua Hari'
            : null;
    }
}
