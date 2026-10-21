<?php

namespace App\Enums;

enum RefundGateway: string
{
    case MidtransRefundApi = 'midtrans_refund_api';
    case MidtransIrisPayout = 'midtrans_iris_payout';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::MidtransRefundApi => 'Midtrans Refund API',
            self::MidtransIrisPayout => 'Midtrans Iris Payout',
            self::Manual => 'Transfer Manual',
        };
    }
}
