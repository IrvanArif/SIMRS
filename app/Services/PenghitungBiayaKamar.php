<?php

namespace App\Services;

use App\Models\OkupansiBed;
use App\Models\RawatInap;
use Illuminate\Support\Carbon;

/**
 * Menghitung biaya kamar dari riwayat okupansi, bukan dari satu tarif dikali
 * lama rawat. Pasien yang pindah dari VIP ke Kelas 2 di tengah masa rawat harus
 * ditagih dua tarif berbeda, dan hanya riwayat berpenggal yang menyimpan
 * keterangan itu.
 */
class PenghitungBiayaKamar
{
    /**
     * Lama rawat dalam hari kalender, minimal satu (aturan 71).
     *
     * Dihitung dari penggal okupansinya, bukan dari `waktu_masuk`: yang menandai
     * pasien benar-benar memakai kamar adalah saat ia menempatinya.
     */
    public function lamaRawat(RawatInap $rawatInap): int
    {
        $okupansi = $rawatInap->okupansi;

        if ($okupansi->isEmpty()) {
            return 0;
        }

        $mulai = $okupansi->first()->mulai;
        $akhir = $okupansi->last()->selesai ?? Carbon::today();

        return max(1, (int) $mulai->diffInDays($akhir));
    }

    /**
     * @return list<array{okupansi: OkupansiBed, hari: int, subtotal: int}>
     */
    public function penggal(RawatInap $rawatInap): array
    {
        return $rawatInap->okupansi()->with('bed.ruang', 'bed.kelas')->get()
            ->map(fn (OkupansiBed $okupansi) => [
                'okupansi' => $okupansi,
                'hari' => $okupansi->hari(),
                'subtotal' => $okupansi->subtotal(),
            ])
            ->all();
    }

    public function total(RawatInap $rawatInap): int
    {
        return array_sum(array_column($this->penggal($rawatInap), 'subtotal'));
    }

    /**
     * Keterangan yang muncul di kuitansi pasien, dibuat supaya bisa dibaca
     * tanpa membuka sistem.
     */
    public function deskripsi(OkupansiBed $okupansi): string
    {
        $bed = $okupansi->bed;

        return "Kamar {$bed->kelas->nama} — {$bed->ruang->nama} {$bed->nomor} ({$okupansi->hari()} hari)";
    }
}
