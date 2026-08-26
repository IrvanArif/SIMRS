<?php

namespace App\Enums;

enum StatusRawatInap: string
{
    case Dirawat = 'dirawat';
    case Pulang = 'pulang';
    case Batal = 'batal';

    public function aktif(): bool
    {
        return $this === self::Dirawat;
    }

    public function label(): string
    {
        return match ($this) {
            self::Dirawat => 'Sedang Dirawat',
            self::Pulang => 'Sudah Pulang',
            self::Batal => 'Batal',
        };
    }
}
