<?php

namespace App\Http\Controllers;

use App\Enums\Bank;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentGateway\PaymentGatewayInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PaymentController extends Controller
{
    public function __construct(private PaymentGatewayInterface $gateway) {}

    public function webhook(Request $request): JsonResponse
    {
        Log::info('Midtrans Webhook Received:', $request->all());

        $orderId = $request->input('order_id');
        $statusCode = $request->input('status_code');
        $grossAmount = $request->input('gross_amount');
        $signature = $request->input('signature_key');
        $serverKey = config('services.midtrans.server_key');

        if ((! is_string($orderId) && ! is_int($orderId))
            || (! is_string($statusCode) && ! is_int($statusCode))
            || (! is_string($grossAmount) && ! is_int($grossAmount) && ! is_float($grossAmount))
            || ! is_numeric($grossAmount)
            || ! is_string($signature)
            || ! is_string($serverKey)
            || $serverKey === '') {
            Log::warning('Midtrans webhook signature validation failed.', [
                'order_id' => $orderId,
                'expected_signature' => null,
                'received_signature' => $signature,
            ]);

            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $orderId = (string) $orderId;
        $statusCode = (string) $statusCode;
        $grossAmount = (string) $grossAmount;
        $grossAmountCandidates = array_unique([
            $grossAmount,
            number_format((float) $grossAmount, 2, '.', ''),
        ]);
        $expectedSignatures = array_map(
            fn (string $amount): string => hash('sha512', $orderId.$statusCode.$amount.$serverKey),
            $grossAmountCandidates,
        );

        $signatureIsValid = false;
        foreach ($expectedSignatures as $expectedSignature) {
            if (hash_equals($expectedSignature, $signature)) {
                $signatureIsValid = true;

                break;
            }
        }

        if (! $signatureIsValid) {
            Log::warning('Midtrans webhook signature validation failed.', [
                'order_id' => $orderId,
                'expected_signatures' => $expectedSignatures,
                'received_signature' => $signature,
            ]);

            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $payment = Payment::query()
            ->whereRelation('order', 'order_code', $orderId)
            ->first()
            ?? Payment::query()->where('transaction_id', $orderId)->first();

        if ($payment === null) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        $transactionStatus = $request->input('transaction_status');
        $fraudStatus = $request->input('fraud_status');

        $result = match ($transactionStatus) {
            'settlement' => PaymentStatus::Settlement,
            'capture' => match ($fraudStatus) {
                'accept' => PaymentStatus::Settlement,
                'deny' => PaymentStatus::Failure,
                default => null,
            },
            'pending' => PaymentStatus::Pending,
            'deny', 'cancel' => PaymentStatus::Failure,
            'expire' => PaymentStatus::Expire,
            default => null,
        };

        if ($result === PaymentStatus::Settlement || $result === PaymentStatus::Expire) {
            $this->gateway->process($payment, $result);
        } elseif ($result === PaymentStatus::Failure) {
            Payment::query()
                ->whereKey($payment->id)
                ->where('payment_status', PaymentStatus::Pending->value)
                ->update(['payment_status' => PaymentStatus::Failure->value]);
        }

        return response()->json(['message' => 'Success'], 200);
    }

    public function pay(Request $request, Order $order): SymfonyResponse
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

        $bank = null;
        if ($method === PaymentType::BankTransfer->value) {
            $bank = $request->validate([
                'bank' => ['required', Rule::enum(Bank::class)],
            ])['bank'];
        }

        $payment = $this->gateway->createPayment($order, PaymentType::from($method), $bank);

        $redirectUrl = data_get($payment->raw_response, 'redirect_url');
        if (is_string($redirectUrl) && $redirectUrl !== '') {
            return Inertia::location($redirectUrl);
        }

        return redirect()->route('orders.show', $order->order_code)->with(
            'success',
            'Pembayaran dibuat. Ikuti instruksi dan lakukan pembayaran.',
        );
    }

    public function checkStatus(Request $request, Payment $payment): RedirectResponse
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

        if ($this->gateway->checkStatus($payment) === PaymentStatus::Settlement) {
            $this->gateway->process($payment, PaymentStatus::Settlement);
        }

        return redirect()->route('orders.show', $order->order_code)->with(
            'success',
            'Pembayaran dikonfirmasi. E-tiket Anda telah diterbitkan.',
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
