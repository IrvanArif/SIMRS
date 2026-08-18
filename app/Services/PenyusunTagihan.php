<?php

namespace App\Services;

use App\Enums\StatusTagihan;
use App\Models\Kunjungan;
use App\Models\Tagihan;
use Illuminate\Support\Facades\DB;

class PenyusunTagihan
{
    public function __construct(private readonly NomorDokumen $nomorDokumen) {}

    /**
     * Idempoten: pemanggilan kedua mengembalikan tagihan yang sudah ada (aturan 12).
     */
    public function susun(Kunjungan $kunjungan): Tagihan
    {
        if ($kunjungan->tagihan !== null) {
            return $kunjungan->tagihan;
        }

        return DB::transaction(function () use ($kunjungan) {
            $baris = $kunjungan->tindakan()->with('tindakan')->get();
            $total = $baris->sum(fn ($item) => $item->subtotal());
            $ditanggung = $kunjungan->penjamin->ditanggung();

            $tagihan = Tagihan::create([
                'no_tagihan' => $this->nomorDokumen->berikutnya('tagihan', $kunjungan->tanggal),
                'kunjungan_id' => $kunjungan->id,
                'penjamin_id' => $kunjungan->penjamin_id,
                'total' => $total,
                // Nilai penuh tetap dicatat meski pasien tidak membayar — itu bahan
                // klaim di fase berikutnya (aturan 14).
                'ditanggung_penjamin' => $ditanggung ? $total : 0,
                'ditagihkan_ke_pasien' => $ditanggung ? 0 : $total,
                'status' => $ditanggung ? StatusTagihan::DitanggungPenjamin : StatusTagihan::BelumBayar,
                'disusun_pada' => now(),
            ]);

            foreach ($baris as $item) {
                $tagihan->detail()->create([
                    'tindakan_kunjungan_id' => $item->id,
                    'deskripsi' => $item->tindakan->nama,
                    'jumlah' => $item->jumlah,
                    'tarif_satuan' => $item->tarif_satuan,
                    'subtotal' => $item->subtotal(),
                ]);
            }

            return $tagihan;
        });
    }
}
