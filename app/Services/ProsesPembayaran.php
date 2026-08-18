<?php

namespace App\Services;

use App\Enums\MetodePembayaran;
use App\Enums\StatusResep;
use App\Enums\StatusTagihan;
use App\Models\Pembayaran;
use App\Models\Resep;
use App\Models\Tagihan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProsesPembayaran
{
    public function __construct(private readonly NomorDokumen $nomorDokumen) {}

    public function bayar(Tagihan $tagihan, MetodePembayaran $metode, int $nominal, User $kasir): Pembayaran
    {
        return DB::transaction(function () use ($tagihan, $metode, $nominal, $kasir) {
            // Baris dikunci di dalam transaksi supaya dua kasir tidak bisa
            // memproses tagihan yang sama secara bersamaan (aturan 15).
            $terkunci = Tagihan::whereKey($tagihan->id)->lockForUpdate()->first();

            // Aturan 29: uang tidak boleh diterima sebelum apotek selesai, karena
            // biaya obat baru masuk tagihan pada saat penyiapan.
            $menunggu = Resep::where('kunjungan_id', $terkunci->kunjungan_id)
                ->where('status', StatusResep::Dibuat->value)
                ->first();

            if ($menunggu !== null) {
                throw new RuntimeException(
                    "Tagihan belum bisa dilunasi: resep {$menunggu->no_resep} belum disiapkan apotek."
                );
            }

            if ($terkunci->status === StatusTagihan::Lunas) {
                throw new RuntimeException('Tagihan ini sudah lunas dan tidak bisa dibayar ulang.');
            }

            if ($terkunci->status !== StatusTagihan::BelumBayar) {
                throw new RuntimeException(
                    'Tagihan ini tidak ditagihkan ke pasien karena ditanggung penjamin.'
                );
            }

            $ditagihkan = (int) $terkunci->ditagihkan_ke_pasien;

            if ($nominal < $ditagihkan) {
                throw new RuntimeException('Nominal pembayaran kurang dari jumlah yang ditagihkan.');
            }

            if (! $metode->butuhKembalian() && $nominal !== $ditagihkan) {
                throw new RuntimeException('Pembayaran nontunai harus persis sejumlah tagihan.');
            }

            $pembayaran = Pembayaran::create([
                'no_kuitansi' => $this->nomorDokumen->berikutnya('kuitansi'),
                'tagihan_id' => $terkunci->id,
                'metode' => $metode,
                'nominal' => $nominal,
                'kembalian' => $nominal - $ditagihkan,
                'kasir_id' => $kasir->id,
                'waktu_bayar' => now(),
            ]);

            $terkunci->update(['status' => StatusTagihan::Lunas]);

            return $pembayaran;
        });
    }
}
