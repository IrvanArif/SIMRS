<?php

namespace App\Services;

use App\Enums\JenisMutasiStok;
use App\Models\BatchObat;
use App\Models\MutasiStok;
use App\Models\User;
use App\Support\KonteksAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Koreksi stok hasil opname. Selalu beralasan dan selalu berjejak (aturan 33) —
 * stok yang bisa diubah diam-diam sama saja dengan tidak punya kartu stok.
 */
class PenyesuaianStok
{
    public function sesuaikan(BatchObat $batch, int $jumlahBaru, string $alasan, User $apoteker): BatchObat
    {
        if (trim($alasan) === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan penyesuaian stok wajib diisi.',
            ]);
        }

        if ($jumlahBaru < 0) {
            throw ValidationException::withMessages([
                'jumlah_baru' => 'Jumlah stok tidak boleh negatif.',
            ]);
        }

        return KonteksAudit::dengan(trim($alasan), function () use ($batch, $jumlahBaru, $alasan, $apoteker) {
            return DB::transaction(function () use ($batch, $jumlahBaru, $alasan, $apoteker) {
                $terkunci = BatchObat::whereKey($batch->id)->lockForUpdate()->first();
                $selisih = $jumlahBaru - (int) $terkunci->jumlah_tersisa;

                if ($selisih === 0) {
                    return $terkunci;
                }

                $terkunci->update(['jumlah_tersisa' => $jumlahBaru]);

                MutasiStok::create([
                    'batch_obat_id' => $terkunci->id,
                    'obat_id' => $terkunci->obat_id,
                    'jenis' => JenisMutasiStok::Penyesuaian,
                    'jumlah' => $selisih,
                    'stok_sesudah' => $jumlahBaru,
                    'catatan' => trim($alasan),
                    'dilakukan_oleh' => $apoteker->id,
                    'created_at' => now(),
                ]);

                return $terkunci->refresh();
            });
        });
    }
}
