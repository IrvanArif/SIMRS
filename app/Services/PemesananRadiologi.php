<?php

namespace App\Services;

use App\Enums\JenisLayanan;
use App\Enums\StatusOrderRadiologi;
use App\Models\Kunjungan;
use App\Models\OrderRadiologi;
use App\Models\User;
use App\Support\KonteksAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PemesananRadiologi
{
    public function __construct(
        private readonly NomorDokumen $nomorDokumen,
        private readonly PencariTarif $pencariTarif,
    ) {}

    /**
     * @param  list<int>  $pemeriksaanId
     */
    public function pesan(
        Kunjungan $kunjungan,
        array $pemeriksaanId,
        User $dokter,
        string $indikasiKlinis
    ): OrderRadiologi {
        if (! $kunjungan->status->aktif()) {
            throw new RuntimeException(
                'Pemeriksaan radiologi tidak bisa dipesan pada kunjungan yang sudah selesai atau dibatalkan.'
            );
        }

        Validator::make([
            'pemeriksaan' => $pemeriksaanId,
            'indikasi_klinis' => trim($indikasiKlinis),
        ], [
            'pemeriksaan' => ['required', 'array', 'min:1'],
            'pemeriksaan.*' => ['required', 'exists:pemeriksaan_radiologi,id'],
            // Aturan 48: pencitraan tanpa indikasi berarti pasien menerima radiasi
            // tanpa alasan yang tercatat.
            'indikasi_klinis' => ['required', 'string', 'max:255'],
        ], [
            'pemeriksaan.required' => 'Order radiologi harus memuat minimal satu pemeriksaan.',
            'pemeriksaan.min' => 'Order radiologi harus memuat minimal satu pemeriksaan.',
            'indikasi_klinis.required' => 'Indikasi klinis wajib diisi.',
        ])->validate();

        if (count($pemeriksaanId) !== count(array_unique($pemeriksaanId))) {
            throw ValidationException::withMessages([
                'pemeriksaan' => 'Satu pemeriksaan hanya boleh muncul sekali dalam satu order.',
            ]);
        }

        return DB::transaction(function () use ($kunjungan, $pemeriksaanId, $dokter, $indikasiKlinis) {
            $order = OrderRadiologi::create([
                'no_order' => $this->nomorDokumen->berikutnya('radiologi', $kunjungan->tanggal),
                'kunjungan_id' => $kunjungan->id,
                'dokter_id' => $dokter->id,
                'status' => StatusOrderRadiologi::Dipesan,
                'indikasi_klinis' => trim($indikasiKlinis),
            ]);

            foreach ($pemeriksaanId as $id) {
                // Tarif disalin sekarang supaya perubahan master tidak mengubah
                // order lama. Biayanya baru masuk tagihan saat kunjungan diselesaikan.
                $order->detail()->create([
                    'pemeriksaan_radiologi_id' => $id,
                    'tarif_satuan' => $this->pencariTarif->untuk(
                        JenisLayanan::Radiologi, (int) $id, $kunjungan->penjamin_id, $kunjungan->tanggal
                    ),
                ]);
            }

            return $order->refresh()->load('detail');
        });
    }

    public function batalkan(OrderRadiologi $order, User $petugas, string $alasan): OrderRadiologi
    {
        if (trim($alasan) === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan pembatalan order radiologi wajib diisi.',
            ]);
        }

        if ($order->status->selesai()) {
            throw new RuntimeException(
                "Order {$order->no_order} berstatus {$order->status->label()} dan tidak bisa dibatalkan."
            );
        }

        return KonteksAudit::dengan(trim($alasan), function () use ($order) {
            $order->update(['status' => StatusOrderRadiologi::Batal]);

            return $order->refresh();
        });
    }
}
