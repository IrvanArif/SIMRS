<?php

namespace App\Services;

use App\Enums\JenisMutasiStok;
use App\Models\BatchObat;
use App\Models\MutasiStok;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PenerimaanObat
{
    public function terima(array $data, User $apoteker): BatchObat
    {
        $tervalidasi = Validator::make($data, [
            'obat_id' => ['required', 'exists:obat,id'],
            'no_batch' => ['required', 'string', 'max:40'],
            'tanggal_kedaluwarsa' => ['required', 'date', 'after_or_equal:today'],
            'jumlah' => ['required', 'integer', 'min:1'],
            'harga_beli' => ['required', 'integer', 'min:0'],
        ], [
            'no_batch.required' => 'Nomor batch wajib diisi.',
            'tanggal_kedaluwarsa.required' => 'Tanggal kedaluwarsa wajib diisi.',
            'tanggal_kedaluwarsa.after_or_equal' => 'Obat yang sudah kedaluwarsa tidak boleh diterima.',
            'jumlah.min' => 'Jumlah penerimaan minimal 1.',
        ])->validate();

        return DB::transaction(function () use ($tervalidasi, $apoteker) {
            $batch = BatchObat::create([
                'obat_id' => $tervalidasi['obat_id'],
                'no_batch' => $tervalidasi['no_batch'],
                'tanggal_kedaluwarsa' => $tervalidasi['tanggal_kedaluwarsa'],
                'jumlah_awal' => $tervalidasi['jumlah'],
                'jumlah_tersisa' => $tervalidasi['jumlah'],
                'harga_beli' => $tervalidasi['harga_beli'],
                'diterima_pada' => now(),
                'diterima_oleh' => $apoteker->id,
            ]);

            MutasiStok::create([
                'batch_obat_id' => $batch->id,
                'obat_id' => $batch->obat_id,
                'jenis' => JenisMutasiStok::Masuk,
                'jumlah' => $tervalidasi['jumlah'],
                'stok_sesudah' => $tervalidasi['jumlah'],
                'catatan' => 'Penerimaan batch '.$batch->no_batch,
                'dilakukan_oleh' => $apoteker->id,
                'created_at' => now(),
            ]);

            return $batch;
        });
    }
}
