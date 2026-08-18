<?php

namespace App\Services;

use App\Models\HargaObat;
use App\Models\Penjamin;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Harga jual obat menurut penjamin kunjungan. Bila penjamin belum punya harga,
 * dipakai harga UMUM dan kejadiannya dicatat agar admin menindaklanjuti (aturan 27).
 */
class PencariHargaObat
{
    public function untuk(int $obatId, int $penjaminId, ?CarbonInterface $tanggal = null): int
    {
        $tanggal ??= Carbon::today();

        $harga = $this->cari($obatId, $penjaminId, $tanggal);

        if ($harga !== null) {
            return $harga;
        }

        $umum = Penjamin::where('kode', 'UMUM')->first();

        Log::warning('Harga khusus penjamin tidak ditemukan, memakai harga UMUM.', [
            'obat_id' => $obatId,
            'penjamin_id' => $penjaminId,
            'tanggal' => $tanggal->toDateString(),
        ]);

        $hargaUmum = $umum ? $this->cari($obatId, $umum->id, $tanggal) : null;

        if ($hargaUmum === null) {
            throw new RuntimeException(
                "Harga untuk obat #{$obatId} belum diisi, termasuk harga UMUM. Hubungi admin master data."
            );
        }

        return $hargaUmum;
    }

    private function cari(int $obatId, int $penjaminId, CarbonInterface $tanggal): ?int
    {
        $baris = HargaObat::where('obat_id', $obatId)
            ->where('penjamin_id', $penjaminId)
            ->whereDate('berlaku_mulai', '<=', $tanggal->toDateString())
            ->orderByDesc('berlaku_mulai')
            ->first();

        return $baris ? (int) $baris->harga : null;
    }
}
