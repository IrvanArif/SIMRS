<?php

namespace App\Services;

use App\Enums\CaraPulang;
use App\Enums\StatusRawatInap;
use App\Models\RawatInap;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class PemulanganPasien
{
    public function __construct(
        private readonly PenempatanBed $penempatanBed,
        private readonly PemeriksaanKlinis $pemeriksaanKlinis,
    ) {}

    public function pulangkan(
        RawatInap $rawatInap,
        User $dokter,
        int $icd10Id,
        CaraPulang $cara,
        ?string $ringkasan = null
    ): RawatInap {
        if (! $rawatInap->status->aktif()) {
            throw new RuntimeException(
                "Masa rawat {$rawatInap->no_rawat_inap} berstatus {$rawatInap->status->label()} "
                .'dan tidak bisa dipulangkan lagi.'
            );
        }

        // Diagnosa akhir bukan formalitas: ia yang menjadi dasar klaim dan
        // pelaporan, dan tidak bisa disusulkan setelah berkasnya ditutup.
        Validator::make(['diagnosa_akhir_id' => $icd10Id], [
            'diagnosa_akhir_id' => ['required', 'integer', 'exists:icd10,id'],
        ], [
            'diagnosa_akhir_id.required' => 'Diagnosa akhir wajib ditetapkan sebelum pasien dipulangkan.',
            'diagnosa_akhir_id.exists' => 'Diagnosa akhir wajib ditetapkan sebelum pasien dipulangkan.',
        ])->validate();

        $this->pastikanPenunjangSelesai($rawatInap);

        return DB::transaction(function () use ($rawatInap, $dokter, $icd10Id, $cara, $ringkasan) {
            $terkunci = RawatInap::whereKey($rawatInap->id)->lockForUpdate()->firstOrFail();

            if (! $terkunci->status->aktif()) {
                throw new RuntimeException('Pasien ini baru saja dipulangkan petugas lain.');
            }

            // Bed dilepas lebih dulu supaya kamarnya langsung bisa dipakai, dan
            // penggal okupansinya tertutup pada tanggal hari ini — angka itulah
            // yang dipakai menghitung biaya kamar.
            $this->penempatanBed->lepaskan($terkunci);

            $terkunci->update([
                'status' => StatusRawatInap::Pulang,
                'cara_pulang' => $cara,
                'diagnosa_akhir_id' => $icd10Id,
                'ringkasan_pulang' => $ringkasan,
                'waktu_pulang' => now(),
                'dipulangkan_oleh' => $dokter->id,
            ]);

            // Baru setelah masa rawat ditandai pulang, kunjungannya boleh
            // ditutup: penjaga di selesaikan() menolak kunjungan yang masih
            // dirawat inap, dan di sinilah tagihannya disusun.
            $this->pemeriksaanKlinis->selesaikan($terkunci->kunjungan->refresh(), $dokter);

            return $terkunci->refresh();
        });
    }

    /**
     * Aturan 69: pasien tidak pulang dengan hasil penunjang yang menggantung.
     * Alasannya sama dengan rawat jalan — diagnosa akhirnya harus berdasar hasil,
     * bukan dugaan sambil menunggu.
     */
    private function pastikanPenunjangSelesai(RawatInap $rawatInap): void
    {
        $kunjungan = $rawatInap->kunjungan;

        $orderLab = $kunjungan->orderLab()->belumSelesai()->first();

        if ($orderLab !== null) {
            throw new RuntimeException(
                "Pasien belum bisa dipulangkan: hasil order {$orderLab->no_order} belum divalidasi."
            );
        }

        $orderRadiologi = $kunjungan->orderRadiologi()->belumSelesai()->first();

        if ($orderRadiologi !== null) {
            throw new RuntimeException(
                "Pasien belum bisa dipulangkan: ekspertise order {$orderRadiologi->no_order} belum ditulis."
            );
        }
    }
}
