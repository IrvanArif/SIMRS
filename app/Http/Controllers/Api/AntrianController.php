<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Antrian;
use Illuminate\Http\JsonResponse;

class AntrianController extends Controller
{
    public function index(): JsonResponse
    {
        // Pemetaan eksplisit inilah yang menjaga aturan 20: model tidak pernah dikirim
        // utuh, jadi kolom pasien tidak bisa bocor saat tabelnya bertambah kolom.
        $antrian = Antrian::with('poli', 'kunjungan.dokter')
            ->whereDate('tanggal', today())
            ->orderBy('poli_id')
            ->orderBy('nomor')
            ->get()
            ->map(fn (Antrian $baris) => [
                'nomor' => (int) $baris->nomor,
                'kode' => $baris->kode(),
                'poli' => $baris->poli->nama,
                'dokter' => $baris->kunjungan->dokter->nama,
                'status' => $baris->status->value,
            ]);

        return response()->json(['data' => $antrian]);
    }
}
