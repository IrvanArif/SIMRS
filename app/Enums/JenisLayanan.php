<?php

namespace App\Enums;

enum JenisLayanan: string
{
    case Tindakan = 'tindakan';
    case Obat = 'obat';
    case Lab = 'lab';

    public function label(): string
    {
        return match ($this) {
            self::Tindakan => 'Tindakan',
            self::Obat => 'Obat',
            self::Lab => 'Pemeriksaan Laboratorium',
        };
    }
}
