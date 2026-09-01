<?php

namespace App\Services;

use App\Enums\JenisDiagnosa;
use App\Models\Diagnosa;
use Illuminate\Support\Collection;

/**
 * Sepuluh besar penyakit menurut diagnosa primer.
 *
 * Hanya diagnosa primer yang dihitung: diagnosa sekunder adalah penyerta, dan
 * memasukkannya membuat satu pasien terhitung beberapa kali sehingga urutannya
 * tidak lagi menjawab "penyakit apa yang paling sering datang".
 */
class LaporanMorbiditas
{
    /**
     * @param  ?bool  $rawatInap  null berarti keduanya
     * @return Collection<int, array{kode: string, nama: string, jumlah: int}>
     */
    public function sepuluhBesar(RentangTanggal $rentang, ?bool $rawatInap = null, int $batas = 10): Collection
    {
        return Diagnosa::query()
            ->join('kunjungan', 'kunjungan.id', '=', 'diagnosa.kunjungan_id')
            ->join('icd10', 'icd10.id', '=', 'diagnosa.icd10_id')
            ->where('diagnosa.jenis', JenisDiagnosa::Primer->value)
            ->whereBetween('kunjungan.tanggal', [$rentang->awal, $rentang->akhir])
            // Rawat jalan dan rawat inap dipisah karena keduanya menjawab
            // pertanyaan berbeda (aturan 90).
            ->when($rawatInap === true, fn ($q) => $q->whereExists(
                fn ($sub) => $sub->selectRaw(1)->from('rawat_inap')
                    ->whereColumn('rawat_inap.kunjungan_id', 'kunjungan.id')
            ))
            ->when($rawatInap === false, fn ($q) => $q->whereNotExists(
                fn ($sub) => $sub->selectRaw(1)->from('rawat_inap')
                    ->whereColumn('rawat_inap.kunjungan_id', 'kunjungan.id')
            ))
            ->selectRaw('icd10.kode AS kode, icd10.nama_id AS nama, COUNT(*) AS jumlah')
            ->groupBy('icd10.kode', 'icd10.nama_id')
            ->orderByDesc('jumlah')
            ->orderBy('icd10.kode')
            ->limit($batas)
            ->get()
            ->map(fn ($baris) => [
                'kode' => $baris->kode,
                'nama' => $baris->nama,
                'jumlah' => (int) $baris->jumlah,
            ]);
    }
}
