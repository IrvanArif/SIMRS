<?php

namespace Database\Factories;

use App\Enums\StatusAntrian;
use App\Models\Antrian;
use App\Models\Kunjungan;
use App\Services\NomorAntrian;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class AntrianFactory extends Factory
{
    protected $model = Antrian::class;

    public function definition(): array
    {
        $kunjungan = Kunjungan::factory()->create();
        $tanggal = Carbon::parse($kunjungan->tanggal);

        return [
            'kunjungan_id' => $kunjungan->id,
            'poli_id' => $kunjungan->poli_id,
            'tanggal' => $tanggal->toDateString(),
            'nomor' => app(NomorAntrian::class)->berikutnya($kunjungan->poli_id, $tanggal),
            'status' => StatusAntrian::Menunggu,
        ];
    }
}
