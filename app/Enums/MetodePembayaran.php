<?php

namespace App\Enums;

enum MetodePembayaran: string
{
    case Tunai = 'tunai';
    case Debit = 'debit';
    case Qris = 'qris';

    public function butuhKembalian(): bool
    {
        return $this === self::Tunai;
    }
}
