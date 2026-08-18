<?php

namespace App\Services;

use App\Enums\MetodePembayaran;
use App\Enums\StatusTagihan;
use App\Models\Pembayaran;
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
