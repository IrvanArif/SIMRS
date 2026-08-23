<?php

namespace App\Enums;

enum PenandaHasil: string
{
    case Rendah = 'rendah';
    case Normal = 'normal';
    case Tinggi = 'tinggi';

    public function abnormal(): bool
    {
        return $this !== self::Normal;
    }

    public function label(): string
    {
        return match ($this) {
            self::Rendah => 'Rendah',
            self::Normal => 'Normal',
            self::Tinggi => 'Tinggi',
        };
    }
}
