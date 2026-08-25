<?php

namespace App\Services;

use App\Enums\StatusOrderRadiologi;
use App\Models\EkspertiseRadiologi;
use App\Models\OrderRadiologi;
use App\Models\User;
use App\Support\KonteksAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Dinamai menurut kegiatannya, bukan benda yang dihasilkan, supaya tidak
 * bertabrakan dengan model EkspertiseRadiologi.
 */
class PenulisanEkspertise
{
    /**
     * @param  array<int, array{temuan?: ?string, kesan?: ?string, saran?: ?string}>  $bacaan
     */
    public function tulis(OrderRadiologi $order, array $bacaan, User $dokter): OrderRadiologi
    {
        if (! $order->status->bisaDiekspertise()) {
            throw new RuntimeException(
                "Ekspertise belum bisa ditulis: order {$order->no_order} berstatus {$order->status->label()}."
            );
        }

        $tervalidasi = $this->validasiBacaan($bacaan);

        return DB::transaction(function () use ($order, $tervalidasi, $dokter) {
            $this->simpan($order, $tervalidasi);

            $order->update([
                'status' => StatusOrderRadiologi::Selesai,
                'waktu_ekspertise' => now(),
                'ditulis_oleh' => $dokter->id,
            ]);

            return $order->refresh();
        });
    }

    /**
     * Mengubah bacaan yang sudah ditulis. Wajib beralasan dan berjejak (aturan 56).
     *
     * @param  array<int, array{temuan?: ?string, kesan?: ?string, saran?: ?string}>  $bacaan
     */
    public function koreksi(OrderRadiologi $order, array $bacaan, User $dokter, string $alasan): OrderRadiologi
    {
        if (trim($alasan) === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan koreksi ekspertise wajib diisi.',
            ]);
        }

        if ($order->status !== StatusOrderRadiologi::Selesai) {
            throw new RuntimeException(
                'Koreksi hanya berlaku untuk ekspertise yang sudah ditulis. Yang belum ditulis cukup ditulis biasa.'
            );
        }

        $tervalidasi = $this->validasiBacaan($bacaan);

        return KonteksAudit::dengan(trim($alasan), function () use ($order, $tervalidasi, $dokter) {
            return DB::transaction(function () use ($order, $tervalidasi, $dokter) {
                $this->simpan($order, $tervalidasi);

                $order->update([
                    'waktu_ekspertise' => now(),
                    'ditulis_oleh' => $dokter->id,
                ]);

                return $order->refresh();
            });
        });
    }

    /**
     * @param  array<int, array<string, ?string>>  $tervalidasi
     */
    private function simpan(OrderRadiologi $order, array $tervalidasi): void
    {
        foreach ($tervalidasi as $detailId => $isi) {
            // firstOrFail memastikan detail yang dituju memang milik order ini,
            // sehingga bacaan tidak bisa nyasar ke order pasien lain.
            $detail = $order->detail()->whereKey($detailId)->firstOrFail();

            EkspertiseRadiologi::updateOrCreate(
                ['order_radiologi_detail_id' => $detail->id],
                [
                    'temuan' => $isi['temuan'],
                    'kesan' => $isi['kesan'],
                    'saran' => $isi['saran'],
                ]
            );
        }
    }

    /**
     * @param  array<int, array<string, ?string>>  $bacaan
     * @return array<int, array<string, ?string>>
     */
    private function validasiBacaan(array $bacaan): array
    {
        // Seluruh bacaan divalidasi sebelum satu baris pun ditulis, sehingga satu
        // isian kosong tidak menyisakan separuh ekspertise tersimpan.
        Validator::make(['bacaan' => $bacaan], [
            'bacaan' => ['required', 'array', 'min:1'],
            'bacaan.*.temuan' => ['required', 'string'],
            'bacaan.*.kesan' => ['required', 'string'],
            'bacaan.*.saran' => ['nullable', 'string'],
        ], [
            'bacaan.required' => 'Ekspertise harus memuat minimal satu bacaan.',
            'bacaan.*.temuan.required' => 'Temuan wajib diisi.',
            'bacaan.*.kesan.required' => 'Kesan wajib diisi.',
        ])->validate();

        $hasil = [];

        foreach ($bacaan as $detailId => $isi) {
            $hasil[(int) $detailId] = [
                'temuan' => trim((string) $isi['temuan']),
                'kesan' => trim((string) $isi['kesan']),
                'saran' => isset($isi['saran']) && trim((string) $isi['saran']) !== ''
                    ? trim((string) $isi['saran'])
                    : null,
            ];
        }

        return $hasil;
    }
}
