<?php

namespace App\Enums;

enum StatusBerkasKlaim: string
{
    case Draf = 'draf';
    case Diajukan = 'diajukan';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';
    case Batal = 'batal';

    /**
     * Aturan 86: yang sudah keluar dari meja rekam medis tidak bisa disunting
     * diam-diam. Perubahannya lewat pembatalan beralasan lalu penyusunan ulang.
     */
    public function bisaDisunting(): bool
    {
        return $this === self::Draf;
    }

    /** Berkas yang masih menduduki kunjungannya (aturan 87). */
    public function berlaku(): bool
    {
        return $this !== self::Batal;
    }

    public function label(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::Diajukan => 'Diajukan',
            self::Disetujui => 'Disetujui',
            self::Ditolak => 'Ditolak',
            self::Batal => 'Batal',
        };
    }
}
