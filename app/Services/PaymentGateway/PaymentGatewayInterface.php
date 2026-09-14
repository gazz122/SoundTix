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
     */
    public function createPayment(Order $order, PaymentType $method): Payment;

    /**
     * Apply a result to a payment. On settlement the order is marked paid
     * and e-tickets are issued; on expire the order's holding stock is released.
     */
    public function process(Payment $payment, PaymentStatus $result): void;
}
