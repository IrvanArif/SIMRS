<?php

namespace App\Services;

use App\Enums\Peran;
use App\Models\CatatanPerkembangan;
use App\Models\RawatInap;
use App\Models\User;
use App\Support\KonteksAudit;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CatatanHarian
{
    private const UNSUR = ['subjective', 'objective', 'assessment', 'plan'];

    /**
     * @param  array<string, ?string>  $soap
     */
    public function tulis(RawatInap $rawatInap, array $soap, User $penulis): CatatanPerkembangan
    {
        if (! $rawatInap->status->aktif()) {
            throw new RuntimeException(
                "Masa rawat {$rawatInap->no_rawat_inap} berstatus {$rawatInap->status->label()}; "
                .'catatan perkembangan hanya bisa ditulis selama pasien dirawat.'
            );
        }

        $isi = $this->validasi($soap);

        return CatatanPerkembangan::create($isi + [
            'rawat_inap_id' => $rawatInap->id,
            'ditulis_oleh' => $penulis->id,
            'peran_penulis' => $this->peran($penulis),
            'waktu' => now(),
        ]);
    }

    /**
     * @param  array<string, ?string>  $soap
     */
    public function koreksi(
        CatatanPerkembangan $catatan,
        array $soap,
        User $pengoreksi,
        string $alasan
    ): CatatanPerkembangan {
        if (trim($alasan) === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan koreksi catatan perkembangan wajib diisi.',
            ]);
        }

        $isi = $this->validasi($soap);

        return KonteksAudit::dengan(trim($alasan), function () use ($catatan, $isi) {
            // Kolom ditulis_oleh sengaja tidak disentuh: catatan klinis adalah
            // pernyataan seseorang, dan siapa yang mengoreksi tercatat di audit
            // log, bukan dengan menimpa nama penulisnya.
            $catatan->update($isi);

            return $catatan->refresh();
        });
    }

    /**
     * @param  array<string, ?string>  $soap
     * @return array<string, string>
     */
    private function validasi(array $soap): array
    {
        $dipangkas = [];

        foreach (self::UNSUR as $unsur) {
            $dipangkas[$unsur] = trim((string) ($soap[$unsur] ?? ''));
        }

        Validator::make($dipangkas, array_fill_keys(self::UNSUR, ['required', 'string']), [
            'subjective.required' => 'Subjective wajib diisi.',
            'objective.required' => 'Objective wajib diisi.',
            'assessment.required' => 'Assessment wajib diisi.',
            'plan.required' => 'Plan wajib diisi.',
        ])->validate();

        return $dipangkas;
    }

    private function peran(User $penulis): string
    {
        foreach ([Peran::Dokter, Peran::Perawat] as $peran) {
            if ($penulis->hasRole($peran->value)) {
                return $peran->value;
            }
        }

        throw new RuntimeException(
            'Catatan perkembangan hanya boleh ditulis pengguna berperan dokter atau perawat.'
        );
    }
}
