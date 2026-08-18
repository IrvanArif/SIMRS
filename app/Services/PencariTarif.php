<?php

namespace App\Services;

use App\Models\Penjamin;
use App\Models\TarifTindakan;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Tarif dipilih menurut penjamin kunjungan. Bila penjamin belum punya tarif,
 * dipakai tarif UMUM dan kejadiannya dicatat agar admin menindaklanjuti (aturan 13).
 */
class PencariTarif
{
    public function untuk(int $tindakanId, int $penjaminId, ?CarbonInterface $tanggal = null): int
    {
        $tanggal ??= Carbon::today();

        $tarif = $this->cari($tindakanId, $penjaminId, $tanggal);

        if ($tarif !== null) {
            return $tarif;
        }

        $umum = Penjamin::where('kode', 'UMUM')->first();

        Log::warning('Tarif khusus penjamin tidak ditemukan, memakai tarif UMUM.', [
            'tindakan_id' => $tindakanId,
            'penjamin_id' => $penjaminId,
            'tanggal' => $tanggal->toDateString(),
        ]);

        $tarifUmum = $umum ? $this->cari($tindakanId, $umum->id, $tanggal) : null;

        if ($tarifUmum === null) {
            throw new RuntimeException(
                "Tarif untuk tindakan #{$tindakanId} belum diisi, termasuk tarif UMUM. Hubungi admin master data."
            );
        }

        return $tarifUmum;
    }

    private function cari(int $tindakanId, int $penjaminId, CarbonInterface $tanggal): ?int
    {
        $baris = TarifTindakan::where('tindakan_id', $tindakanId)
            ->where('penjamin_id', $penjaminId)
            ->whereDate('berlaku_mulai', '<=', $tanggal->toDateString())
            ->orderByDesc('berlaku_mulai')
            ->first();

        return $baris ? (int) $baris->tarif : null;
    }
}
