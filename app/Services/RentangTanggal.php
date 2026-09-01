<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Rentang tanggal untuk seluruh laporan, dengan satu penjagaan: rentang terbalik
 * ditolak sebelum satu kueri pun berjalan (aturan 92). Laporan yang mengembalikan
 * nol karena tanggalnya tertukar terlihat seperti periode yang memang sepi.
 */
class RentangTanggal
{
    private function __construct(
        public readonly Carbon $awal,
        public readonly Carbon $akhir,
    ) {}

    public static function dari(string|CarbonInterface $awal, string|CarbonInterface $akhir): self
    {
        $mulai = Carbon::parse($awal)->startOfDay();
        $selesai = Carbon::parse($akhir)->startOfDay();

        if ($selesai->lessThan($mulai)) {
            throw new InvalidArgumentException(
                'Tanggal akhir tidak boleh mendahului tanggal awal.'
            );
        }

        return new self($mulai, $selesai);
    }

    /** Jumlah hari, dihitung inklusif: 1–30 Juni berarti 30 hari. */
    public function hari(): int
    {
        return (int) $this->awal->diffInDays($this->akhir) + 1;
    }

    /**
     * Batas atas eksklusif, dipakai untuk mengiris penggal okupansi yang juga
     * berbentuk selang setengah terbuka.
     */
    public function batasAtas(): Carbon
    {
        return $this->akhir->copy()->addDay();
    }

    public function label(): string
    {
        return $this->awal->format('d/m/Y').' – '.$this->akhir->format('d/m/Y');
    }
}
