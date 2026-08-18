<?php

namespace App\Enums;

enum StatusAntrian: string
{
    case Menunggu = 'menunggu';
    case Dipanggil = 'dipanggil';
    case Dilayani = 'dilayani';
    case Selesai = 'selesai';
    case Terlewat = 'terlewat';
}
