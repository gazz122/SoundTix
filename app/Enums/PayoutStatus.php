<?php

namespace App\Enums;

enum PayoutStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Verifikasi',
            self::Approved => 'Disetujui',
            self::Processing => 'Sedang Diproses',
            self::Completed => 'Selesai',
            self::Failed => 'Gagal',
        };
    }
}
