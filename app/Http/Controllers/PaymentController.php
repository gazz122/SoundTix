<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentGateway\PaymentGatewayInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(private PaymentGatewayInterface $gateway) {}

    public function pay(Request $request, Order $order): RedirectResponse
    {
        $this->guardOwned($request, $order);

        if ($order->status !== OrderStatus::Pending->value) {
            return redirect()->route('orders.show', $order->order_code)->with(
                'error',
                'Hanya pesanan dengan status menunggu pembayaran yang dapat dibayar.',
            );
        }

        if ($order->expires_at->isPast()) {
            return redirect()->route('orders.show', $order->order_code)->with(
                'error',
                'Batas waktu pembayaran telah habis. Silakan buat pesanan baru.',
            );
        }

        $method = $request->validate([
            'method' => ['required', Rule::enum(PaymentType::class)],
        ])['method'];

        $this->gateway->createPayment($order, PaymentType::from($method));

        return redirect()->route('orders.show', $order->order_code)->with(
            'success',
            'Pembayaran dibuat. Selesaikan simulasi pembayaran Anda.',
        );
    }

    public function settle(Request $request, Payment $payment): RedirectResponse
    {
        $order = $this->guardPaymentOwned($request, $payment);

        if ($payment->payment_status !== PaymentStatus::Pending->value) {
            return redirect()->route('orders.show', $order->order_code)->with(
                'error',
                'Transaksi ini sudah diproses.',
            );
        }

        if ($order->status !== OrderStatus::Pending->value) {
            return redirect()->route('orders.show', $order->order_code)->with(
                'error',
                'Pesanan ini tidak dapat diproses lagi.',
            );
        }

        $this->gateway->process($payment, PaymentStatus::Settlement);

        return redirect()->route('orders.show', $order->order_code)->with(
            'success',
            'Pembayaran berhasil. E-tiket Anda telah diterbitkan.',
        );
    }

    public function expire(Request $request, Payment $payment): RedirectResponse
    {
        $order = $this->guardPaymentOwned($request, $payment);

        if ($payment->payment_status !== PaymentStatus::Pending->value) {
            return redirect()->route('orders.show', $order->order_code)->with(
                'error',
                'Transaksi ini sudah diproses.',
            );
        }

        if ($order->status !== OrderStatus::Pending->value) {
            return redirect()->route('orders.show', $order->order_code)->with(
                'error',
                'Pesanan ini tidak dapat diproses lagi.',
            );
        }

        $this->gateway->process($payment, PaymentStatus::Expire);

        return redirect()->route('orders.show', $order->order_code)->with(
            'success',
            'Pembayaran kadaluarsa. Pesanan dibatalkan dan stok dikembalikan.',
        );
    }

    private function guardOwned(Request $request, Order $order): void
    {
        $this->ensureCustomer($request->user(), $order->customer_id);
    }

    private function guardPaymentOwned(Request $request, Payment $payment): Order
    {
        $this->ensureCustomer($request->user(), $payment->order->customer_id);

        return $payment->order;
    }

    private function ensureCustomer(User $user, int $customerId): void
    {
        abort_unless($user->id === $customerId, 404);
    }
}
