<?php

namespace App\Enums;

enum JenisKelamin: string
{
    case LakiLaki = 'L';
    case Perempuan = 'P';

    public function label(): string
    {
        return $this === self::LakiLaki ? 'Laki-laki' : 'Perempuan';
    }
}
