<?php

namespace App\Enums;

enum StatusTagihan: string
{
    case BelumBayar = 'belum_bayar';
    case Lunas = 'lunas';
    case DitanggungPenjamin = 'ditanggung_penjamin';
    case Batal = 'batal';
}
