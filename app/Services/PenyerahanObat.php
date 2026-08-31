<?php

namespace App\Services;

use App\Enums\StatusResep;
use App\Enums\StatusTagihan;
use App\Models\Resep;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PenyerahanObat
{
    public function serahkan(Resep $resep, User $apoteker): Resep
    {
        if ($resep->status !== StatusResep::Disiapkan) {
            throw new RuntimeException(
                "Resep {$resep->no_resep} berstatus {$resep->status->label()} dan belum siap diserahkan."
            );
        }

        $kunjungan = $resep->kunjungan;

        // Aturan 30: pasien tunai menunggu lunas; pasien berpenjamin tidak.
        // Aturan 29 mencegah uang lolos, aturan ini mencegah obat lolos.
        // Kecuali pasien rawat inap: ia tidak bisa membayar lebih dulu karena
        // tagihannya baru ada saat pulang, dan obatnya diberikan selama dirawat.
        if (! $kunjungan->penjamin->ditanggung() && ! $kunjungan->sedangDirawatInap()) {
            $tagihan = $kunjungan->tagihan;

            if ($tagihan === null || $tagihan->status !== StatusTagihan::Lunas) {
                throw new RuntimeException(
                    'Obat belum bisa diserahkan: tagihan pasien belum lunas di kasir.'
                );
            }
        }

        return DB::transaction(function () use ($resep, $apoteker) {
            $resep->update([
                'status' => StatusResep::Diserahkan,
                'diserahkan_pada' => now(),
                'diserahkan_oleh' => $apoteker->id,
            ]);

            return $resep->refresh();
        });
    }
}
