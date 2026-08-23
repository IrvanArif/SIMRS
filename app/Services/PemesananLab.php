<?php

namespace App\Services;

use App\Enums\JenisLayanan;
use App\Enums\StatusOrderLab;
use App\Models\Kunjungan;
use App\Models\OrderLab;
use App\Models\User;
use App\Support\KonteksAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PemesananLab
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
        ?string $catatanKlinis = null
    ): OrderLab {
        if (! $kunjungan->status->aktif()) {
            throw new RuntimeException(
                'Pemeriksaan laboratorium tidak bisa dipesan pada kunjungan yang sudah selesai atau dibatalkan.'
            );
        }

        Validator::make(['pemeriksaan' => $pemeriksaanId], [
            'pemeriksaan' => ['required', 'array', 'min:1'],
            'pemeriksaan.*' => ['required', 'exists:pemeriksaan_lab,id'],
        ], [
            'pemeriksaan.required' => 'Order laboratorium harus memuat minimal satu pemeriksaan.',
            'pemeriksaan.min' => 'Order laboratorium harus memuat minimal satu pemeriksaan.',
        ])->validate();

        if (count($pemeriksaanId) !== count(array_unique($pemeriksaanId))) {
            throw ValidationException::withMessages([
                'pemeriksaan' => 'Satu pemeriksaan hanya boleh muncul sekali dalam satu order.',
            ]);
        }

        return DB::transaction(function () use ($kunjungan, $pemeriksaanId, $dokter, $catatanKlinis) {
            $order = OrderLab::create([
                'no_order' => $this->nomorDokumen->berikutnya('lab', $kunjungan->tanggal),
                'kunjungan_id' => $kunjungan->id,
                'dokter_id' => $dokter->id,
                'status' => StatusOrderLab::Dipesan,
                'catatan_klinis' => $catatanKlinis,
            ]);

            foreach ($pemeriksaanId as $id) {
                // Tarif disalin sekarang supaya perubahan master tidak mengubah
                // order lama. Biayanya sendiri baru masuk tagihan saat kunjungan
                // diselesaikan, karena pada titik ini tagihannya memang belum ada.
                $order->detail()->create([
                    'pemeriksaan_lab_id' => $id,
                    'tarif_satuan' => $this->pencariTarif->untuk(
                        JenisLayanan::Lab, (int) $id, $kunjungan->penjamin_id, $kunjungan->tanggal
                    ),
                ]);
            }

            return $order->refresh()->load('detail');
        });
    }

    public function batalkan(OrderLab $order, User $petugas, string $alasan): OrderLab
    {
        if (trim($alasan) === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan pembatalan order laboratorium wajib diisi.',
            ]);
        }

        if ($order->status->selesai()) {
            throw new RuntimeException(
                "Order {$order->no_order} berstatus {$order->status->label()} dan tidak bisa dibatalkan."
            );
        }

        return KonteksAudit::dengan(trim($alasan), function () use ($order) {
            $order->update(['status' => StatusOrderLab::Batal]);

            return $order->refresh();
        });
    }
}
