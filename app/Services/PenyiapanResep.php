<?php

namespace App\Services;

use App\Enums\JenisMutasiStok;
use App\Enums\StatusResep;
use App\Exceptions\SeluruhBatchKedaluwarsa;
use App\Exceptions\StokTidakCukup;
use App\Models\BatchObat;
use App\Models\MutasiStok;
use App\Models\Resep;
use App\Models\ResepDetail;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PenyiapanResep
{
    public function __construct(
        private readonly PencariHargaObat $pencariHarga,
        private readonly PenyusunTagihan $penyusunTagihan,
    ) {}

    public function siapkan(Resep $resep, User $apoteker): Resep
    {
        if (! $resep->status->bisaDisiapkan()) {
            throw new RuntimeException(
                "Resep {$resep->no_resep} sudah berstatus {$resep->status->label()} dan tidak bisa disiapkan lagi."
            );
        }

        $kunjungan = $resep->kunjungan;
        $tanggal = Carbon::today();

        return DB::transaction(function () use ($resep, $apoteker, $kunjungan, $tanggal) {
            // Kunci baris resep supaya dua apoteker tidak menyiapkan resep yang sama.
            $terkunci = Resep::whereKey($resep->id)->lockForUpdate()->first();

            if (! $terkunci->status->bisaDisiapkan()) {
                throw new RuntimeException('Resep ini baru saja disiapkan petugas lain.');
            }

            foreach ($terkunci->detail as $baris) {
                $alokasi = $this->alokasikan($baris, $tanggal);

                foreach ($alokasi as ['batch' => $batch, 'jumlah' => $jumlah]) {
                    $sisa = (int) $batch->jumlah_tersisa - $jumlah;

                    $batch->update(['jumlah_tersisa' => $sisa]);

                    $baris->pengambilan()->create([
                        'batch_obat_id' => $batch->id,
                        'jumlah' => $jumlah,
                        'harga_beli' => $batch->harga_beli,
                    ]);

                    MutasiStok::create([
                        'batch_obat_id' => $batch->id,
                        'obat_id' => $batch->obat_id,
                        'jenis' => JenisMutasiStok::Keluar,
                        'jumlah' => -$jumlah,
                        'stok_sesudah' => $sisa,
                        'resep_id' => $terkunci->id,
                        'catatan' => 'Penyiapan resep '.$terkunci->no_resep,
                        'dilakukan_oleh' => $apoteker->id,
                        'created_at' => now(),
                    ]);
                }

                $baris->update([
                    'jumlah_diserahkan' => $baris->jumlah,
                    'harga_satuan' => $this->pencariHarga->untuk(
                        $baris->obat_id, $kunjungan->penjamin_id, $tanggal
                    ),
                ]);
            }

            $terkunci->update([
                'status' => StatusResep::Disiapkan,
                'disiapkan_pada' => now(),
                'disiapkan_oleh' => $apoteker->id,
            ]);

            // Pengecekan "tagihan sudah lunas" ada di dalam transaksi yang sama,
            // jadi bila ditolak, seluruh pemotongan stok ikut dibatalkan.
            $this->penyusunTagihan->tambahObat($terkunci->refresh()->load('detail.obat'));

            return $terkunci->refresh()->load('detail.pengambilan');
        });
    }

    /**
     * Alokasi FEFO: batch berkedaluwarsa terdekat diambil lebih dulu, dan satu baris
     * resep boleh ditarik dari beberapa batch sekaligus (aturan 23).
     *
     * @return list<array{batch: BatchObat, jumlah: int}>
     */
    private function alokasikan(ResepDetail $baris, Carbon $tanggal): array
    {
        $dibutuhkan = (int) $baris->jumlah;

        $batch = BatchObat::where('obat_id', $baris->obat_id)
            ->layakPakai($tanggal)
            ->orderBy('tanggal_kedaluwarsa')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $tersedia = (int) $batch->sum('jumlah_tersisa');

        if ($tersedia < $dibutuhkan) {
            $namaObat = $baris->obat->nama;

            $adaBatchKedaluwarsa = BatchObat::where('obat_id', $baris->obat_id)
                ->where('jumlah_tersisa', '>', 0)
                ->whereDate('tanggal_kedaluwarsa', '<', $tanggal->toDateString())
                ->exists();

            if ($tersedia === 0 && $adaBatchKedaluwarsa) {
                throw SeluruhBatchKedaluwarsa::untuk($namaObat);
            }

            throw StokTidakCukup::untuk($namaObat, $dibutuhkan, $tersedia);
        }

        $alokasi = [];
        $sisa = $dibutuhkan;

        foreach ($batch as $b) {
            if ($sisa === 0) {
                break;
            }

            $ambil = min($sisa, (int) $b->jumlah_tersisa);
            $alokasi[] = ['batch' => $b, 'jumlah' => $ambil];
            $sisa -= $ambil;
        }

        return $alokasi;
    }
}
