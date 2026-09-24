<?php

namespace App\Enums;

enum Bank: string
{
    case BCA = 'bca';
    case Mandiri = 'mandiri';
    case BNI = 'bni';
    case BRI = 'bri';
    case Permata = 'permata';
    case CIMB = 'cimb';

    public function label(): string
    {
        return match ($this) {
            self::BCA => 'BCA',
            self::Mandiri => 'Bank Mandiri',
            self::BNI => 'Bank BNI',
            self::BRI => 'Bank BRI',
            self::Permata => 'Bank Permata',
            self::CIMB => 'CIMB Niaga',
        };
    }

    public function vaPrefix(): string
    {
        return match ($this) {
            self::BCA => '8008',
            self::Mandiri => '8950',
            self::BNI => '8895',
            self::BRI => '8005',
            self::Permata => '8160',
            self::CIMB => '8804',
        };
    }
}
