<?php

namespace App\Support;

use App\Enums\StatusAntrian;
use App\Enums\StatusResep;
use App\Models\Antrian;
use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\Pasien;
use App\Models\Resep;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Angka ringkas untuk halaman depan. Hanya nilai agregat — tidak ada satu pun
 * data pasien, karena halaman ini terbuka tanpa login.
 */
class DenyutSistem
{
    /**
     * @return array<string, int|null> null berarti angkanya tidak bisa dibaca
     */
    public static function ambil(): array
    {
        try {
            return [
                'pasien' => Pasien::count(),
                'kunjungan_hari_ini' => Kunjungan::whereDate('tanggal', today())->count(),
                'menunggu_poli' => Antrian::whereDate('tanggal', today())
                    ->where('status', StatusAntrian::Menunggu->value)->count(),
                'menunggu_apotek' => Resep::where('status', StatusResep::Dibuat->value)->count(),
                'obat_menipis' => Obat::menipis()->count(),
            ];
        } catch (Throwable $e) {
            // Halaman depan tidak boleh mati hanya karena database sedang bermasalah.
            Log::warning('Denyut sistem tidak terbaca: '.$e->getMessage());

            return [
                'pasien' => null,
                'kunjungan_hari_ini' => null,
                'menunggu_poli' => null,
                'menunggu_apotek' => null,
                'obat_menipis' => null,
            ];
        }
    }
}
