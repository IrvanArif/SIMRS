<?php

namespace App\Enums;

enum StatusOrderLab: string
{
    case Dipesan = 'dipesan';
    case SampelDiambil = 'sampel_diambil';
    case HasilDientri = 'hasil_dientri';
    case Divalidasi = 'divalidasi';
    case Batal = 'batal';

    /**
     * Hasil hanya boleh dientri setelah sampel benar-benar diambil (aturan 38),
     * dan masih boleh diperbaiki selama belum divalidasi.
     */
    public function bisaEntriHasil(): bool
    {
        return in_array($this, [self::SampelDiambil, self::HasilDientri], true);
    }

    /**
     * Order yang sudah selesai tidak lagi menahan penyelesaian kunjungan (aturan 37).
     */
    public function selesai(): bool
    {
        return in_array($this, [self::Divalidasi, self::Batal], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Dipesan => 'Menunggu Sampel',
            self::SampelDiambil => 'Sampel Diambil',
            self::HasilDientri => 'Menunggu Validasi',
            self::Divalidasi => 'Selesai',
            self::Batal => 'Batal',
        };
    }
}
