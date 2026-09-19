<?php

namespace App\Enums;

enum EventStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Published = 'published';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Menunggu Persetujuan',
            self::Published => 'Published',
            self::Rejected => 'Ditolak',
            self::Cancelled => 'Dibatalkan',
            self::Completed => 'Selesai',
        };
    }
}
