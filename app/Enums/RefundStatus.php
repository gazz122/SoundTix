<?php

namespace App\Enums;

enum RefundStatus: string
{
    case Pending = 'pending';
    case WaitingBankDetails = 'waiting_bank_details';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::WaitingBankDetails => 'Menunggu Data Rekening',
            self::Processing => 'Diproses Otomatis',
            self::Completed => 'Selesai',
            self::Failed => 'Gagal',
            self::Refunded => 'Dikembalikan',
        };
    }
}
