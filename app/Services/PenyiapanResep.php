<?php

namespace App\Services;

use App\Enums\JenisLayanan;
use App\Enums\JenisMutasiStok;
use App\Enums\StatusResep;
use App\Exceptions\SeluruhBatchKedaluwarsa;
use App\Exceptions\StokTidakCukup;
use App\Models\BatchObat;
use App\Models\MutasiStok;
use App\Models\Resep;
use App\Models\ResepDetail;
use App\Models\User;
use App\Support\KonteksAudit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PenyiapanResep
{
    public function __construct(
        private readonly PencariTarif $pencariTarif,
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
                    'harga_satuan' => $this->pencariTarif->untuk(
                        JenisLayanan::Obat, $baris->obat_id, $kunjungan->penjamin_id, $tanggal
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
     * Mengembalikan seluruh jumlah ke batch asalnya dan mencabut baris obat dari
     * tagihan (aturan 32). Resep kembali berstatus dibuat sehingga bisa disiapkan ulang.
     */
    public function batalkan(Resep $resep, User $apoteker, string $alasan): Resep
    {
        if (trim($alasan) === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan pembatalan penyiapan wajib diisi.',
            ]);
        }

        if ($resep->status !== StatusResep::Disiapkan) {
            throw new RuntimeException(
                "Hanya resep berstatus disiapkan yang bisa dibatalkan. Resep ini berstatus {$resep->status->label()}."
            );
        }

        return KonteksAudit::dengan(trim($alasan), function () use ($resep, $apoteker) {
            return DB::transaction(function () use ($resep, $apoteker) {
                foreach ($resep->detail as $baris) {
                    foreach ($baris->pengambilan as $pengambilan) {
                        $batch = BatchObat::whereKey($pengambilan->batch_obat_id)->lockForUpdate()->first();
                        $sisa = (int) $batch->jumlah_tersisa + (int) $pengambilan->jumlah;

                        $batch->update(['jumlah_tersisa' => $sisa]);

                        MutasiStok::create([
                            'batch_obat_id' => $batch->id,
                            'obat_id' => $batch->obat_id,
                            'jenis' => JenisMutasiStok::Pengembalian,
                            'jumlah' => (int) $pengambilan->jumlah,
                            'stok_sesudah' => $sisa,
                            'resep_id' => $resep->id,
                            'catatan' => 'Pembatalan penyiapan resep '.$resep->no_resep,
                            'dilakukan_oleh' => $apoteker->id,
                            'created_at' => now(),
                        ]);
                    }

                    $baris->pengambilan()->delete();
                    $baris->update(['jumlah_diserahkan' => 0, 'harga_satuan' => 0]);
                }

                $tagihan = $resep->kunjungan->tagihan;

                if ($tagihan !== null) {
                    $this->penyusunTagihan->hapusBarisDari($tagihan, ResepDetail::class);
                    $this->penyusunTagihan->hitungUlang($tagihan);
                }

                $resep->update([
                    'status' => StatusResep::Dibuat,
                    'disiapkan_pada' => null,
                    'disiapkan_oleh' => null,
                ]);

                return $resep->refresh();
            });
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
