<?php

namespace App\Services;

use App\Models\Kunjungan;
use App\Models\Resep;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PenulisanResep
{
    public function __construct(private readonly NomorDokumen $nomorDokumen) {}

    public function tulis(Kunjungan $kunjungan, array $item, User $dokter): Resep
    {
        if (! $kunjungan->status->aktif()) {
            throw new RuntimeException('Resep tidak bisa ditulis untuk kunjungan yang sudah selesai atau dibatalkan.');
        }

        Validator::make(['item' => $item], [
            'item' => ['required', 'array', 'min:1'],
            'item.*.obat_id' => ['required', 'exists:obat,id'],
            'item.*.jumlah' => ['required', 'integer', 'min:1'],
            'item.*.aturan_pakai' => ['required', 'string', 'max:100'],
            'item.*.catatan' => ['nullable', 'string', 'max:255'],
        ], [
            'item.min' => 'Resep harus memuat minimal satu obat.',
            'item.required' => 'Resep harus memuat minimal satu obat.',
            'item.*.aturan_pakai.required' => 'Aturan pakai wajib diisi untuk setiap obat.',
            'item.*.jumlah.min' => 'Jumlah obat minimal 1.',
        ])->validate();

        $obatId = array_column($item, 'obat_id');

        if (count($obatId) !== count(array_unique($obatId))) {
            throw ValidationException::withMessages([
                'item' => 'Satu obat hanya boleh muncul satu baris dalam satu resep. Gabungkan jumlahnya.',
            ]);
        }

        return DB::transaction(function () use ($kunjungan, $item, $dokter) {
            $resep = Resep::firstOrNew(['kunjungan_id' => $kunjungan->id]);
            $resep->no_resep ??= $this->nomorDokumen->berikutnya('resep', $kunjungan->tanggal);
            $resep->dokter_id = $dokter->id;
            $resep->status = 'dibuat';
            $resep->save();

            $resep->detail()->delete();
            $resep->detail()->createMany($item);

            return $resep->refresh();
        });
    }
}
