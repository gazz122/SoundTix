<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Active = 'active';
    case Used = 'used';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Used => 'Terpakai',
            self::Expired => 'Kadaluarsa',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
