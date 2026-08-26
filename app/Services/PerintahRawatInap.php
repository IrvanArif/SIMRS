<?php

namespace App\Services;

use App\Enums\StatusKunjungan;
use App\Enums\StatusRawatInap;
use App\Models\KelasKamar;
use App\Models\Kunjungan;
use App\Models\RawatInap;
use App\Models\User;
use App\Support\KonteksAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PerintahRawatInap
{
    public function __construct(private readonly NomorDokumen $nomorDokumen) {}

    public function terbitkan(
        Kunjungan $kunjungan,
        User $dokter,
        string $indikasi,
        KelasKamar $kelasDiminta
    ): RawatInap {
        if (! $kunjungan->status->aktif()) {
            throw new RuntimeException(
                'Rawat inap tidak bisa diperintahkan pada kunjungan yang sudah selesai atau dibatalkan.'
            );
        }

        if ($kunjungan->rawatInap()->whereIn('status', [
            StatusRawatInap::Dirawat->value, StatusRawatInap::Pulang->value,
        ])->exists()) {
            throw new RuntimeException(
                "Kunjungan {$kunjungan->no_kunjungan} sudah punya masa rawat inap."
            );
        }

        Validator::make(['indikasi' => trim($indikasi)], [
            // Indikasi rawat adalah alasan pasien menginap. Tanpa itu, lama
            // rawatnya tidak bisa dipertanggungjawabkan ke siapa pun.
            'indikasi' => ['required', 'string', 'max:255'],
        ], [
            'indikasi.required' => 'Indikasi rawat inap wajib diisi.',
        ])->validate();

        return DB::transaction(function () use ($kunjungan, $dokter, $indikasi, $kelasDiminta) {
            $rawatInap = RawatInap::create([
                'no_rawat_inap' => $this->nomorDokumen->berikutnya('rawat_inap', $kunjungan->tanggal),
                'kunjungan_id' => $kunjungan->id,
                'dokter_id' => $kunjungan->dokter_id,
                'kelas_diminta_id' => $kelasDiminta->id,
                'indikasi' => trim($indikasi),
                'status' => StatusRawatInap::Dirawat,
                'diperintahkan_oleh' => $dokter->id,
            ]);

            $kunjungan->update(['status' => StatusKunjungan::DalamPerawatan]);

            return $rawatInap->refresh();
        });
    }

    public function batalkan(RawatInap $rawatInap, User $petugas, string $alasan): RawatInap
    {
        if (trim($alasan) === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan pembatalan rawat inap wajib diisi.',
            ]);
        }

        if (! $rawatInap->status->aktif()) {
            throw new RuntimeException(
                "Masa rawat {$rawatInap->no_rawat_inap} berstatus {$rawatInap->status->label()} "
                .'dan tidak bisa dibatalkan.'
            );
        }

        if ($rawatInap->bed()->exists()) {
            throw new RuntimeException(
                'Pasien sudah menempati bed. Yang berlaku sejak titik itu adalah pemulangan, bukan pembatalan.'
            );
        }

        return KonteksAudit::dengan(trim($alasan), function () use ($rawatInap) {
            return DB::transaction(function () use ($rawatInap) {
                $rawatInap->update(['status' => StatusRawatInap::Batal]);

                // Kunjungannya kembali menjadi kunjungan poli biasa dan bisa
                // ditutup lewat alur rawat jalan seperti sedia kala.
                $rawatInap->kunjungan->update(['status' => StatusKunjungan::DiperiksaDokter]);

                return $rawatInap->refresh();
            });
        });
    }
}
