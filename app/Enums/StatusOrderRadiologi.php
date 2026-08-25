<?php

namespace App\Enums;

enum StatusOrderRadiologi: string
{
    case Dipesan = 'dipesan';
    case Dikerjakan = 'dikerjakan';
    case Selesai = 'selesai';
    case Batal = 'batal';

    public function bisaDikerjakan(): bool
    {
        return $this === self::Dipesan;
    }

    /**
     * Ekspertise ditulis setelah pencitraannya benar-benar dikerjakan (aturan 52).
     */
    public function bisaDiekspertise(): bool
    {
        return $this === self::Dikerjakan;
    }

    /**
     * Order yang sudah selesai tidak lagi menahan penyelesaian kunjungan (aturan 50).
     */
    public function selesai(): bool
    {
        return in_array($this, [self::Selesai, self::Batal], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Dipesan => 'Menunggu Dikerjakan',
            self::Dikerjakan => 'Menunggu Ekspertise',
            self::Selesai => 'Selesai',
            self::Batal => 'Batal',
        };
    }
}
