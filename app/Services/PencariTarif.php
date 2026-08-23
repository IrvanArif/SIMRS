<?php

namespace App\Services;

use App\Enums\JenisLayanan;
use App\Models\Penjamin;
use App\Models\Tarif;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Satu pencari untuk seluruh jenis layanan. Bila penjamin belum punya tarif,
 * dipakai tarif UMUM dan kejadiannya dicatat agar admin menindaklanjuti
 * (aturan 13 untuk tindakan, aturan 27 untuk obat, aturan 41 untuk lab).
 */
class PencariTarif
{
    public function untuk(
        JenisLayanan $jenis,
        int $layananId,
        int $penjaminId,
        ?CarbonInterface $tanggal = null
    ): int {
        $tanggal ??= Carbon::today();

        $harga = $this->cari($jenis, $layananId, $penjaminId, $tanggal);

        if ($harga !== null) {
            return $harga;
        }

        $umum = Penjamin::where('kode', 'UMUM')->first();

        Log::warning('Tarif khusus penjamin tidak ditemukan, memakai tarif UMUM.', [
            'jenis_layanan' => $jenis->value,
            'layanan_id' => $layananId,
            'penjamin_id' => $penjaminId,
            'tanggal' => $tanggal->toDateString(),
        ]);

        $tarifUmum = $umum ? $this->cari($jenis, $layananId, $umum->id, $tanggal) : null;

        if ($tarifUmum === null) {
            throw new RuntimeException(
                "Tarif {$jenis->label()} #{$layananId} belum diisi, termasuk tarif UMUM. Hubungi admin master data."
            );
        }

        return $tarifUmum;
    }

    private function cari(
        JenisLayanan $jenis,
        int $layananId,
        int $penjaminId,
        CarbonInterface $tanggal
    ): ?int {
        $baris = Tarif::where('jenis_layanan', $jenis->value)
            ->where('layanan_id', $layananId)
            ->where('penjamin_id', $penjaminId)
            ->whereDate('berlaku_mulai', '<=', $tanggal->toDateString())
            ->orderByDesc('berlaku_mulai')
            ->first();

        return $baris ? (int) $baris->harga : null;
    }
}
