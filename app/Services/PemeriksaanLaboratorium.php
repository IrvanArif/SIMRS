<?php

namespace App\Services;

use App\Enums\StatusOrderLab;
use App\Models\HasilLab;
use App\Models\OrderLab;
use App\Models\ParameterLab;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PemeriksaanLaboratorium
{
    public function __construct(private readonly PenandaNilai $penandaNilai) {}

    public function ambilSampel(OrderLab $order, User $analis): OrderLab
    {
        if ($order->status !== StatusOrderLab::Dipesan) {
            throw new RuntimeException(
                "Order {$order->no_order} berstatus {$order->status->label()} dan sampelnya tidak bisa diambil."
            );
        }

        $order->update([
            'status' => StatusOrderLab::SampelDiambil,
            'waktu_sampel' => now(),
            'diambil_oleh' => $analis->id,
        ]);

        return $order->refresh();
    }

    /**
     * @param  array<int, mixed>  $nilai  parameter_lab_id => nilai
     */
    public function entriHasil(OrderLab $order, array $nilai, User $analis): OrderLab
    {
        if (! $order->status->bisaEntriHasil()) {
            throw new RuntimeException(
                "Hasil belum bisa dientri: order {$order->no_order} berstatus {$order->status->label()}."
            );
        }

        $jenisKelamin = $order->kunjungan->pasien->jenis_kelamin;

        // Seluruh nilai divalidasi lebih dulu, sebelum satu baris pun ditulis:
        // satu nilai salah ketik tidak boleh menyisakan separuh hasil tersimpan.
        $tervalidasi = $this->validasiNilai($nilai);

        return DB::transaction(function () use ($order, $tervalidasi, $analis, $jenisKelamin) {
            foreach ($tervalidasi as $parameterId => $angka) {
                $parameter = ParameterLab::findOrFail($parameterId);

                $detail = $order->detail()
                    ->where('pemeriksaan_lab_id', $parameter->pemeriksaan_lab_id)
                    ->firstOrFail();

                HasilLab::updateOrCreate([
                    'order_lab_detail_id' => $detail->id,
                    'parameter_lab_id' => $parameter->id,
                ], [
                    'nilai' => $angka,
                    // Penanda dihitung sistem, tidak pernah diketik petugas (aturan 40).
                    'penanda' => $this->penandaNilai->untuk($parameter, $angka, $jenisKelamin),
                ]);
            }

            $order->update([
                'status' => StatusOrderLab::HasilDientri,
                'waktu_hasil' => now(),
                'dientri_oleh' => $analis->id,
            ]);

            return $order->refresh();
        });
    }

    /**
     * @param  array<int, mixed>  $nilai
     * @return array<int, float>
     */
    private function validasiNilai(array $nilai): array
    {
        $tervalidasi = [];

        foreach ($nilai as $parameterId => $angka) {
            if (! is_numeric($angka)) {
                $nama = ParameterLab::find($parameterId)?->nama ?? "#{$parameterId}";

                // Pesannya menyebut parameter mana yang salah — menolak seluruh
                // formulir tanpa keterangan membuat analis menebak-nebak.
                throw ValidationException::withMessages([
                    "nilai.{$parameterId}" => "Nilai {$nama} harus berupa angka.",
                ]);
            }

            $tervalidasi[(int) $parameterId] = (float) $angka;
        }

        return $tervalidasi;
    }
}
