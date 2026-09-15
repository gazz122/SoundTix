<?php

namespace App\Repositories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Collection;

class OrderRepository
{
    public function forUser(int $customerId): Collection
    {
        return Order::where('customer_id', $customerId)
            ->with([
                'orderItems.ticketCategory' => fn ($query) => $query->with('event:id,title,slug,event_date'),
                'orderItems.tickets',
                'payments',
            ])
            ->orderByDesc('created_at')
            ->get();
    }

    public function loadOverview(Order $order): Order
    {
        return $order->load([
            'orderItems.ticketCategory.event:id,title,slug,event_date',
            'orderItems.tickets',
            'payments',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createPending(int $customerId, array $attributes): Order
    {
        return Order::create([
            'customer_id' => $customerId,
            ...$attributes,
        ]);
    }

    public function orderCodeExists(string $code): bool
    {
        return Order::where('order_code', $code)->exists();
    }

    public function expiredWithItems(): Collection
    {
        return Order::query()
            ->where('status', OrderStatus::Pending->value)
            ->where('expires_at', '<=', now())
            ->with('orderItems')
            ->get();
    }

    public function markExpiredGuarded(int $orderId): int
    {
        return Order::query()
            ->whereKey($orderId)
            ->where('status', OrderStatus::Pending->value)
            ->update(['status' => OrderStatus::Expired->value]);
    }

    /**
     * @return array{quantity: int, revenue: float}
     */
    public function paidStatsForOrganizer(int $organizerId): array
    {
        $paidOrders = Order::where('status', OrderStatus::Paid->value)
            ->whereHas('orderItems.ticketCategory.event', fn ($query) => $query->where('organizer_id', $organizerId));

        $quantity = (int) (clone $paidOrders)
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->sum('order_items.quantity');

        return [
            'quantity' => $quantity,
            'revenue' => (float) $paidOrders->sum('grand_total'),
        ];
    }

    public function countAll(): int
    {
        return Order::count();
    }

    public function paidRevenue(): float
    {
        return (float) Order::where('status', OrderStatus::Paid->value)->sum('grand_total');
    }

    public function countByCustomer(int $customerId): int
    {
        return Order::where('customer_id', $customerId)->count();
    }

    public function countPaidByCustomer(int $customerId): int
    {
        return Order::where('customer_id', $customerId)
            ->where('status', OrderStatus::Paid->value)
            ->count();
    }

    /** Paid orders grouped by date for sales chart. */
    public function dailyPaidRevenueForOrganizer(int $organizerId, int $days = 30): Collection
    {
        return Order::where('status', OrderStatus::Paid->value)
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', now()->subDays($days))
            ->whereHas('orderItems.ticketCategory.event', fn ($q) => $q->where('organizer_id', $organizerId))
            ->selectRaw('DATE(paid_at) as `date`, SUM(grand_total) as revenue, COUNT(*) as orders')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    /**
     * Paid order items aggregated by event + category for top-selling list.
     *
     * @return Collection<int, array{event_title: string, category_name: string, quantity: int, revenue: float}>
     */
    public function topSellingCategoriesForOrganizer(int $organizerId, int $limit = 5): Collection
    {
        return OrderItem::query()
            ->whereHas('order', fn ($q) => $q->where('status', OrderStatus::Paid->value))
            ->whereHas('ticketCategory.event', fn ($q) => $q->where('organizer_id', $organizerId))
            ->join('ticket_categories', 'order_items.ticket_category_id', '=', 'ticket_categories.id')
            ->join('events', 'ticket_categories.event_id', '=', 'events.id')
            ->selectRaw('events.title as event_title, ticket_categories.name as category_name, SUM(order_items.quantity) as quantity, SUM(order_items.subtotal) as revenue')
            ->groupBy('events.title', 'ticket_categories.name')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();
    }

    /** Flat paid order items for CSV/print export. */
    public function paidOrderItemsForOrganizer(int $organizerId, ?string $from = null, ?string $to = null): Collection
    {
        $query = OrderItem::query()
            ->whereHas('order', fn ($q) => $q->where('status', OrderStatus::Paid->value))
            ->whereHas('ticketCategory.event', fn ($q) => $q->where('organizer_id', $organizerId))
            ->with([
                'order' => fn ($q) => $q->select('id', 'order_code', 'paid_at', 'grand_total'),
                'ticketCategory.event' => fn ($q) => $q->select('id', 'title'),
                'ticketCategory' => fn ($q) => $q->select('id', 'event_id', 'name'),
            ]);

        if ($from) {
            $query->whereHas('order', fn ($q) => $q->where('paid_at', '>=', $from));
        }
        if ($to) {
            $query->whereHas('order', fn ($q) => $q->where('paid_at', '<=', $to.' 23:59:59'));
        }

        return $query->orderByDesc('order_items.id')->get();
    }
}
