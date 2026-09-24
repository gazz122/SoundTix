<?php

namespace App\Services\PaymentGateway;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Order;
use App\Models\Payment;

interface PaymentGatewayInterface
{
    /**
     * Register a pending payment for an order with the chosen method.
     *
     * @param  string|null  $bank  Bank code when PaymentType::BankTransfer is used.
     */
    public function createPayment(Order $order, PaymentType $method, ?string $bank = null): Payment;

    /**
     * Query the gateway for the current status of a pending payment.
     */
    public function checkStatus(Payment $payment): PaymentStatus;

    /**
     * Apply a result to a payment. On settlement the order is marked paid
     * and e-tickets are issued; on expire the order's holding stock is released.
     */
    public function process(Payment $payment, PaymentStatus $result): void;
}
