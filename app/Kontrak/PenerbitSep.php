<?php

namespace App\Kontrak;

use App\Models\Kunjungan;
use App\Models\Sep;

/**
 * Batas antara SIMRS dan penerbit SEP.
 *
 * Penerapan bawaannya `SepLokal`, yang menerbitkan nomor sendiri dan benar-benar
 * berjalan. Saat kredensial BPJS tersedia, penerapan kedua yang memanggil VClaim
 * diikat ke antarmuka ini tanpa menyentuh satu pun pemanggilnya — apa yang harus
 * dikerjakannya dirinci di spec Fase 6 bagian 7.
 */
interface PenerbitSep
{
    /**
     * Mengembalikan nomor SEP. Kegagalan dilempar sebagai exception, tidak pernah
     * dikembalikan sebagai nomor kosong.
     */
    public function terbitkan(Kunjungan $kunjungan, string $diagnosaAwal): string;

    public function batalkan(Sep $sep, string $alasan): void;

    /**
     * Penanda singkat penerapan ini, disimpan pada SEP-nya supaya nomor hasil
     * simulasi tidak pernah tertukar dengan nomor sungguhan.
     */
    public function nama(): string;
}
