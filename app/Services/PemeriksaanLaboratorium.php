<?php

namespace App\Services;

use App\Enums\StatusOrderLab;
use App\Models\HasilLab;
use App\Models\OrderLab;
use App\Models\ParameterLab;
use App\Models\User;
use App\Support\KonteksAudit;
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

    public function validasi(OrderLab $order, User $analis): OrderLab
    {
        if ($order->status !== StatusOrderLab::HasilDientri) {
            throw new RuntimeException(
                "Order {$order->no_order} berstatus {$order->status->label()} dan belum bisa divalidasi."
            );
        }

        return DB::transaction(function () use ($order, $analis) {
            $terkunci = OrderLab::whereKey($order->id)->lockForUpdate()->first();

            if ($terkunci->status !== StatusOrderLab::HasilDientri) {
                throw new RuntimeException('Order ini baru saja divalidasi petugas lain.');
            }

            // Aturan 43: pelaku entri dan pelaku validasi boleh sama — melarangnya
            // akan menghentikan lab yang shift malamnya hanya punya satu analis —
            // tetapi keduanya tetap tercatat sehingga bisa ditelusuri.
            $terkunci->update([
                'status' => StatusOrderLab::Divalidasi,
                'waktu_validasi' => now(),
                'divalidasi_oleh' => $analis->id,
            ]);

            return $terkunci->refresh();
        });
    }

    /**
     * Mengubah hasil yang sudah divalidasi. Wajib beralasan dan berjejak (aturan 44).
     *
     * @param  array<int, mixed>  $nilai
     */
    public function koreksi(OrderLab $order, array $nilai, User $analis, string $alasan): OrderLab
    {
        if (trim($alasan) === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan koreksi hasil laboratorium wajib diisi.',
            ]);
        }

        if ($order->status !== StatusOrderLab::Divalidasi) {
            throw new RuntimeException(
                'Koreksi hanya berlaku untuk hasil yang sudah divalidasi. Hasil yang belum divalidasi cukup dientri ulang.'
            );
        }

        $jenisKelamin = $order->kunjungan->pasien->jenis_kelamin;
        $tervalidasi = $this->validasiNilai($nilai);

        return KonteksAudit::dengan(trim($alasan), function () use ($order, $tervalidasi, $analis, $jenisKelamin) {
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
                        'penanda' => $this->penandaNilai->untuk($parameter, $angka, $jenisKelamin),
                    ]);
                }

                $order->update([
                    'waktu_validasi' => now(),
                    'divalidasi_oleh' => $analis->id,
                ]);

                return $order->refresh();
            });
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
