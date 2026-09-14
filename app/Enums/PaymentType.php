<?php

namespace App\Enums;

enum PaymentType: string
{
    case BankTransfer = 'bank_transfer';
    case Qris = 'qris';

    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => 'Transfer Bank',
            self::Qris => 'QRIS',
        };
    }
}
