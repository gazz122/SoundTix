<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Pembayaran',
            self::Paid => 'Lunas',
            self::Expired => 'Kadaluarsa',
            self::Cancelled => 'Dibatalkan',
            self::Failed => 'Gagal',
        };
    }
}
