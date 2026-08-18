<?php

namespace Database\Factories;

use App\Enums\StatusKunjungan;
use App\Models\Dokter;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\Penjamin;
use App\Services\NomorDokumen;
use Illuminate\Database\Eloquent\Factories\Factory;

class KunjunganFactory extends Factory
{
    protected $model = Kunjungan::class;

    public function definition(): array
    {
        $dokter = Dokter::factory()->create();

        return [
            'no_kunjungan' => app(NomorDokumen::class)->berikutnya('kunjungan'),
            'pasien_id' => Pasien::factory(),
            'poli_id' => $dokter->poli_id,
            'dokter_id' => $dokter->id,
            'penjamin_id' => Penjamin::factory(),
            'no_kartu_penjamin' => null,
            'jenis_kunjungan' => 'baru',
            'tanggal' => now()->toDateString(),
            'status' => StatusKunjungan::Terdaftar,
            'waktu_daftar' => now(),
        ];
    }
}
