<?php

namespace App\Services;

use App\Models\Kunjungan;
use App\Models\TindakanKunjungan;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class TindakanPelayanan
{
    public function __construct(private readonly PencariTarif $pencariTarif) {}

    public function tambah(Kunjungan $kunjungan, int $tindakanId, int $jumlah, User $petugas): TindakanKunjungan
    {
        if (! $kunjungan->status->aktif()) {
            throw new RuntimeException('Tindakan tidak bisa ditambahkan pada kunjungan yang sudah selesai.');
        }

        Validator::make(
            ['tindakan_id' => $tindakanId, 'jumlah' => $jumlah],
            [
                'tindakan_id' => ['required', 'exists:tindakan,id'],
                'jumlah' => ['required', 'integer', 'min:1'],
            ],
            ['jumlah.min' => 'Jumlah tindakan minimal 1.']
        )->validate();

        // Tarif disalin ke baris ini supaya perubahan master tarif di kemudian hari
        // tidak mengubah nilai tagihan yang sudah terbentuk (aturan 12).
        return $kunjungan->tindakan()->create([
            'tindakan_id' => $tindakanId,
            'jumlah' => $jumlah,
            'tarif_satuan' => $this->pencariTarif->untuk($tindakanId, $kunjungan->penjamin_id, $kunjungan->tanggal),
            'dilakukan_oleh' => $petugas->id,
        ]);
    }
}
