<?php

namespace App\Enums;

enum Peran: string
{
    case Admisi = 'admisi';
    case Perawat = 'perawat';
    case Dokter = 'dokter';
    case RekamMedis = 'rekam_medis';
    case Kasir = 'kasir';
    case Apoteker = 'apoteker';
    case Admin = 'admin';

    public static function semua(): array
    {
        return array_column(self::cases(), 'value');
    }
}
