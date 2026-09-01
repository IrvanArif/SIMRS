<?php

namespace App\Services;

use App\Enums\StatusTagihan;
use App\Models\Tagihan;
use Illuminate\Support\Collection;

/**
 * Pendapatan per penjamin, dipisah menurut apakah uangnya sudah diterima.
 *
 * Pemisahan ini bukan kerapian: menjumlahkan seluruh tagihan sebagai pendapatan
 * membuat manajemen mengira punya uang yang sebenarnya masih piutang klaim
 * (aturan 91).
 */
class LaporanPendapatan
{
    /**
     * @return Collection<int, array{penjamin: string, lunas: int, menunggu: int, ditanggung_penjamin: int, total: int}>
     */
    public function perPenjamin(RentangTanggal $rentang): Collection
    {
        return Tagihan::query()
            ->join('penjamin', 'penjamin.id', '=', 'tagihan.penjamin_id')
            ->join('kunjungan', 'kunjungan.id', '=', 'tagihan.kunjungan_id')
            ->whereBetween('kunjungan.tanggal', [$rentang->awal, $rentang->akhir])
            ->selectRaw('penjamin.nama AS penjamin')
            ->selectRaw('SUM(CASE WHEN tagihan.status = ? THEN tagihan.total ELSE 0 END) AS lunas', [
                StatusTagihan::Lunas->value,
            ])
            ->selectRaw('SUM(CASE WHEN tagihan.status = ? THEN tagihan.total ELSE 0 END) AS menunggu', [
                StatusTagihan::BelumBayar->value,
            ])
            ->selectRaw('SUM(CASE WHEN tagihan.status = ? THEN tagihan.total ELSE 0 END) AS ditanggung_penjamin', [
                StatusTagihan::DitanggungPenjamin->value,
            ])
            ->selectRaw('SUM(tagihan.total) AS total')
            ->groupBy('penjamin.nama')
            ->orderBy('penjamin.nama')
            ->get()
            ->map(fn ($baris) => [
                'penjamin' => $baris->penjamin,
                'lunas' => (int) $baris->lunas,
                'menunggu' => (int) $baris->menunggu,
                'ditanggung_penjamin' => (int) $baris->ditanggung_penjamin,
                'total' => (int) $baris->total,
            ]);
    }
}
