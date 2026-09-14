<?php

namespace App\Repositories;

use App\Enums\OrderStatus;
use App\Models\Order;
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
}
