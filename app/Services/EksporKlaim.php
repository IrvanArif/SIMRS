<?php

namespace App\Services;

use App\Models\BerkasKlaim;
use Illuminate\Support\Collection;

/**
 * Menyiapkan berkas klaim untuk diunggah ke aplikasi verifikasi.
 *
 * Barisnya dibentuk sebagai larik datar lebih dulu, baru dijadikan CSV. Itu yang
 * membuat isinya bisa diuji tanpa mengurai teks, dan membuat format lain kelak
 * cukup menambah pembungkus, bukan menulis ulang.
 */
class EksporKlaim
{
    private const KOLOM = [
        'no_berkas', 'no_sep', 'no_kartu', 'nama_peserta', 'jenis_pelayanan',
        'kelas_rawat', 'tanggal_masuk', 'tanggal_pulang', 'lama_rawat',
        'diagnosa_primer', 'diagnosa_sekunder', 'prosedur', 'total_biaya', 'status',
    ];

    /**
     * @return array<string, string|int|null>
     */
    public function baris(BerkasKlaim $berkas): array
    {
        $sekunder = $berkas->diagnosa->where('jenis', 'sekunder')->pluck('kode');

        return [
            'no_berkas' => $berkas->no_berkas,
            'no_sep' => $berkas->sep?->no_sep ?? '',
            'no_kartu' => $berkas->no_kartu,
            'nama_peserta' => $berkas->nama_peserta,
            'jenis_pelayanan' => $berkas->jenis_pelayanan->label(),
            'kelas_rawat' => $berkas->kelas_rawat ?? '',
            'tanggal_masuk' => $berkas->tanggal_masuk->toDateString(),
            'tanggal_pulang' => $berkas->tanggal_pulang?->toDateString() ?? '',
            'lama_rawat' => $berkas->lama_rawat ?? '',
            'diagnosa_primer' => $berkas->diagnosa->firstWhere('jenis', 'primer')?->kode ?? '',
            // Pemisah titik koma, bukan koma: koma sudah dipakai CSV sebagai
            // pemisah kolom, dan menumpuk keduanya mengundang salah baca.
            'diagnosa_sekunder' => $sekunder->implode(';'),
            'prosedur' => $berkas->prosedur->pluck('kode')->implode(';'),
            'total_biaya' => (int) $berkas->total_biaya,
            'status' => $berkas->status->label(),
        ];
    }

    /**
     * @param  Collection<int, BerkasKlaim>  $berkas
     */
    public function csv(Collection $berkas): string
    {
        $berkas->loadMissing('sep', 'diagnosa', 'prosedur');

        // fputcsv dipakai, bukan implode: ia yang tahu cara mengutip nama yang
        // memuat koma atau tanda kutip. Menyusunnya sendiri adalah cara klasik
        // menggeser seluruh kolom di berkas yang dikirim ke pihak lain.
        $aliran = fopen('php://temp', 'r+');

        fputcsv($aliran, self::KOLOM);

        foreach ($berkas as $satu) {
            fputcsv($aliran, array_values($this->baris($satu)));
        }

        rewind($aliran);
        $isi = stream_get_contents($aliran);
        fclose($aliran);

        return $isi;
    }

    /** @return list<string> */
    public function kolom(): array
    {
        return self::KOLOM;
    }
}
