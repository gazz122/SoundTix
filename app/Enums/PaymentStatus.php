<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Settlement = 'settlement';
    case Expire = 'expire';
    case Failure = 'failure';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Pembayaran',
            self::Settlement => 'Berhasil',
            self::Expire => 'Kadaluarsa',
            self::Failure => 'Gagal',
        };
    }
}
