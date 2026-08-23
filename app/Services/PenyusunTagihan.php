<?php

namespace App\Services;

use App\Enums\StatusTagihan;
use App\Models\Kunjungan;
use App\Models\Resep;
use App\Models\Tagihan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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
                    'sumber_tipe' => $item::class,
                    'sumber_id' => $item->id,
                    'deskripsi' => $item->tindakan->nama,
                    'jumlah' => $item->jumlah,
                    'tarif_satuan' => $item->tarif_satuan,
                    'subtotal' => $item->subtotal(),
                ]);
            }

            return $tagihan;
        });
    }

    /**
     * Menambahkan baris obat ke tagihan kunjungan yang sudah ada (aturan 28).
     * Tagihan tidak disusun ulang — hanya ditambahi, dan hanya selama belum lunas.
     */
    public function tambahObat(Resep $resep): Tagihan
    {
        $tagihan = $resep->kunjungan->tagihan;

        if ($tagihan === null) {
            throw new RuntimeException(
                'Kunjungan ini belum punya tagihan. Dokter harus menyelesaikan kunjungan lebih dulu.'
            );
        }

        if ($tagihan->status === StatusTagihan::Lunas) {
            throw new RuntimeException(
                "Tagihan {$tagihan->no_tagihan} sudah lunas dan tidak bisa ditambahi biaya obat."
            );
        }

        return DB::transaction(function () use ($resep, $tagihan) {
            foreach ($resep->detail as $baris) {
                if ((int) $baris->jumlah_diserahkan === 0) {
                    continue;
                }

                $tagihan->detail()->create([
                    'sumber_tipe' => $baris::class,
                    'sumber_id' => $baris->id,
                    'deskripsi' => $baris->obat->nama,
                    'jumlah' => $baris->jumlah_diserahkan,
                    'tarif_satuan' => $baris->harga_satuan,
                    'subtotal' => $baris->subtotal(),
                ]);
            }

            return $this->hitungUlang($tagihan);
        });
    }

    /**
     * Mencabut seluruh baris yang berasal dari satu jenis sumber. Dipakai saat
     * penyiapan resep dibatalkan, dan nanti saat order laboratorium dibatalkan.
     */
    public function hapusBarisDari(Tagihan $tagihan, string $sumberTipe): void
    {
        $tagihan->detail()->where('sumber_tipe', $sumberTipe)->delete();
    }

    /**
     * Menyetel ulang total dari seluruh rinciannya. Dipakai setiap kali baris
     * ditambahkan atau dibatalkan, supaya angkanya tidak pernah dihitung di dua tempat.
     */
    public function hitungUlang(Tagihan $tagihan): Tagihan
    {
        $total = (int) $tagihan->detail()->sum('subtotal');
        $ditanggung = $tagihan->penjamin->ditanggung();

        $tagihan->update([
            'total' => $total,
            'ditanggung_penjamin' => $ditanggung ? $total : 0,
            'ditagihkan_ke_pasien' => $ditanggung ? 0 : $total,
        ]);

        return $tagihan->refresh();
    }
}
