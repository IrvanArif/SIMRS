<?php

namespace App\Services;

use App\Enums\JenisKelamin;
use App\Enums\PenandaHasil;
use App\Models\ParameterLab;
use Illuminate\Support\Facades\Log;

/**
 * Menentukan rendah/normal/tinggi dari rujukan sesuai jenis kelamin pasien
 * (aturan 40). Penanda dihitung sistem, tidak pernah diketik petugas — penanda
 * yang bisa diketik sama saja dengan tidak ada penanda.
 */
class PenandaNilai
{
    public function untuk(ParameterLab $parameter, float $nilai, JenisKelamin $jenisKelamin): ?PenandaHasil
    {
        $rujukan = $parameter->rujukan()
            ->whereIn('jenis_kelamin', [$jenisKelamin->value, 'semua'])
            // Rujukan khusus jenis kelamin didahulukan; 'semua' hanya dipakai
            // bila yang khusus tidak ada (aturan 41).
            ->orderByRaw('CASE WHEN jenis_kelamin = ? THEN 0 ELSE 1 END', [$jenisKelamin->value])
            ->first();

        if ($rujukan === null) {
            // Tidak ditebak: nilainya tetap tersimpan, penandanya dikosongkan,
            // dan admin diberi tahu supaya master rujukannya dilengkapi.
            Log::warning('Parameter laboratorium tanpa nilai rujukan yang cocok.', [
                'parameter_lab_id' => $parameter->id,
                'parameter' => $parameter->nama,
                'jenis_kelamin' => $jenisKelamin->value,
            ]);

            return null;
        }

        if ($nilai < $rujukan->nilai_min) {
            return PenandaHasil::Rendah;
        }

        if ($nilai > $rujukan->nilai_maks) {
            return PenandaHasil::Tinggi;
        }

        return PenandaHasil::Normal;
    }
}
