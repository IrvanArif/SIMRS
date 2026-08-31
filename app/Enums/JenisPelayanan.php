<?php

namespace App\Enums;

/**
 * Nilainya mengikuti kode BPJS supaya tidak perlu diterjemahkan saat berkasnya
 * dikirim: 1 rawat inap, 2 rawat jalan.
 */
enum JenisPelayanan: string
{
    case RawatInap = '1';
    case RawatJalan = '2';

    public function label(): string
    {
        return match ($this) {
            self::RawatInap => 'Rawat Inap',
            self::RawatJalan => 'Rawat Jalan',
        };
    }
}
