<?php

namespace App\Enums;

enum StatusResep: string
{
    case Dibuat = 'dibuat';
    case Disiapkan = 'disiapkan';
    case Diserahkan = 'diserahkan';
    case Batal = 'batal';

    public function bisaDisiapkan(): bool
    {
        return $this === self::Dibuat;
    }

    public function label(): string
    {
        return match ($this) {
            self::Dibuat => 'Menunggu Disiapkan',
            self::Disiapkan => 'Sudah Disiapkan',
            self::Diserahkan => 'Sudah Diserahkan',
            self::Batal => 'Batal',
        };
    }
}
