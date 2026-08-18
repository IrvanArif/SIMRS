<?php

namespace App\Enums;

enum JenisMutasiStok: string
{
    case Masuk = 'masuk';
    case Keluar = 'keluar';
    case Pengembalian = 'pengembalian';
    case Penyesuaian = 'penyesuaian';
}
