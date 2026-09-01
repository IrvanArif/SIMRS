<?php

namespace App\Services;

use App\Models\Kunjungan;
use Illuminate\Support\Collection;

class LaporanKunjungan
{
    /**
     * @return Collection<int, array{poli: string, rawat_jalan: int, rawat_inap: int, total: int}>
     */
    public function perPoli(RentangTanggal $rentang): Collection
    {
        return Kunjungan::query()
            ->join('poli', 'poli.id', '=', 'kunjungan.poli_id')
            ->leftJoin('rawat_inap', 'rawat_inap.kunjungan_id', '=', 'kunjungan.id')
            ->whereBetween('kunjungan.tanggal', [$rentang->awal, $rentang->akhir])
            ->selectRaw('poli.nama AS poli')
            ->selectRaw('SUM(CASE WHEN rawat_inap.id IS NULL THEN 1 ELSE 0 END) AS rawat_jalan')
            ->selectRaw('SUM(CASE WHEN rawat_inap.id IS NULL THEN 0 ELSE 1 END) AS rawat_inap')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('poli.nama')
            ->orderBy('poli.nama')
            ->get()
            ->map(fn ($baris) => [
                'poli' => $baris->poli,
                'rawat_jalan' => (int) $baris->rawat_jalan,
                'rawat_inap' => (int) $baris->rawat_inap,
                'total' => (int) $baris->total,
            ]);
    }
}
