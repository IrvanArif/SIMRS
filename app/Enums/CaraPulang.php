<?php

namespace App\Enums;

/**
 * Cara pasien mengakhiri masa rawat. Dibedakan karena artinya berbeda secara
 * klinis maupun pelaporan: pulang paksa dan rujuk keluar bukan kegagalan yang
 * sama, dan keduanya bukan kesembuhan.
 */
enum CaraPulang: string
{
    case Sembuh = 'sembuh';
    case Membaik = 'membaik';
    case RujukKeluar = 'rujuk_keluar';
    case PulangPaksa = 'pulang_paksa';
    case Meninggal = 'meninggal';

    public function label(): string
    {
        return match ($this) {
            self::Sembuh => 'Sembuh',
            self::Membaik => 'Membaik',
            self::RujukKeluar => 'Rujuk Keluar',
            self::PulangPaksa => 'Pulang Paksa',
            self::Meninggal => 'Meninggal',
        };
    }
}
