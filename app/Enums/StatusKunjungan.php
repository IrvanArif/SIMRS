<?php

namespace App\Enums;

enum StatusKunjungan: string
{
    case Terdaftar = 'terdaftar';
    case DiperiksaPerawat = 'diperiksa_perawat';
    case DiperiksaDokter = 'diperiksa_dokter';
    case Selesai = 'selesai';
    case Batal = 'batal';

    public function aktif(): bool
    {
        return ! in_array($this, [self::Selesai, self::Batal], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Terdaftar => 'Terdaftar',
            self::DiperiksaPerawat => 'Diperiksa Perawat',
            self::DiperiksaDokter => 'Diperiksa Dokter',
            self::Selesai => 'Selesai',
            self::Batal => 'Batal',
        };
    }
}
