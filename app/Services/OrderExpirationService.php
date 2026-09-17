<?php

namespace App\Services;

use App\Models\Order;
use App\Notifications\SoundTixNotification;
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

        $released = 0;

        foreach ($expiredOrders as $order) {
            $released += (int) $this->expire($order);
        }

        return $released;
    }

    /**
     * Mark a single pending order as expired and return its held stock.
     * Guarded so a concurrent runner cannot release the same order twice.
     */
    public function expire(Order $order): bool
    {
        $expired = false;

        DB::transaction(function () use ($order, &$expired) {
            if ($this->orders->markExpiredGuarded($order->id) !== 1) {
                return;
            }

            foreach ($order->orderItems as $item) {
                $this->categories->incrementAvailable($item->ticket_category_id, $item->quantity);
            }

            $expired = true;
        });

        if ($expired) {
            $order->customer->notify(new SoundTixNotification([
                'title' => 'Pesanan kadaluarsa',
                'message' => "Pesanan {$order->order_code} kadaluarsa dan stok tiket telah dikembalikan.",
                'type' => 'order_expired',
                'action_url' => route('orders.show', $order->order_code),
                'action_label' => 'Lihat pesanan',
            ]));
        }

        return $expired;
    }
}
