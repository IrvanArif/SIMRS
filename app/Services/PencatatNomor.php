<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya tempat nomor urut dikeluarkan. Semua penomoran melewati kelas ini
 * supaya penguncian barisnya seragam dan tidak ada yang memakai max() + 1.
 */
class PencatatNomor
{
    public function ambil(string $kunci, string $periode = 'global'): int
    {
        // Baris penghitung dibuat lebih dulu di luar transaksi supaya lockForUpdate()
        // selalu punya baris untuk dikunci. Tanpa ini, dua proses yang sama-sama
        // tidak menemukan baris akan sama-sama menyisipkan dan salah satunya gagal.
        DB::table('nomor_counter')->insertOrIgnore([
            'kunci' => $kunci,
            'periode' => $periode,
            'nilai' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::transaction(function () use ($kunci, $periode) {
            $baris = DB::table('nomor_counter')
                ->where('kunci', $kunci)
                ->where('periode', $periode)
                ->lockForUpdate()
                ->first();

            $berikutnya = (int) $baris->nilai + 1;

            DB::table('nomor_counter')
                ->where('id', $baris->id)
                ->update(['nilai' => $berikutnya, 'updated_at' => now()]);

            return $berikutnya;
        });
    }
}
