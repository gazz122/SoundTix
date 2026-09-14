<?php

namespace App\Services;

use App\Models\Order;
use App\Repositories\OrderRepository;
use App\Repositories\TicketCategoryRepository;
use Illuminate\Support\Facades\DB;

class OrderExpirationService
{
    public function __construct(
        private OrderRepository $orders,
        private TicketCategoryRepository $categories,
    ) {}

    /**
     * Release stock for every pending order whose holding time has expired.
     */
    public function releaseExpired(): int
    {
        $expiredOrders = $this->orders->expiredWithItems();

        if ($expiredOrders->isEmpty()) {
            return 0;
        }

        foreach ($expiredOrders as $order) {
            $this->expire($order);
        }

        return $expiredOrders->count();
    }

    /**
     * Mark a single pending order as expired and return its held stock.
     * Guarded so a concurrent runner cannot release the same order twice.
     */
    public function expire(Order $order): void
    {
        DB::transaction(function () use ($order) {
            if ($this->orders->markExpiredGuarded($order->id) !== 1) {
                return;
            }

            foreach ($order->orderItems as $item) {
                $this->categories->incrementAvailable($item->ticket_category_id, $item->quantity);
            }
        });
    }
}
