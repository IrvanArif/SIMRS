<?php

namespace App\Services;

use Carbon\CarbonInterface;

/**
 * Nomor antrian dimulai dari 1 setiap hari untuk setiap poli (aturan 4).
 */
class NomorAntrian
{
    public function __construct(private readonly PencatatNomor $pencatat) {}

    public function berikutnya(int $poliId, CarbonInterface $tanggal): int
    {
        return $this->pencatat->ambil("antrian:{$poliId}", $tanggal->format('Y-m-d'));
    }
}
