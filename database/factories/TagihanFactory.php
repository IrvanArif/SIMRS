<?php

namespace Database\Factories;

use App\Enums\StatusTagihan;
use App\Models\Kunjungan;
use App\Models\Tagihan;
use App\Services\NomorDokumen;
use Illuminate\Database\Eloquent\Factories\Factory;

class TagihanFactory extends Factory
{
    protected $model = Tagihan::class;

    public function definition(): array
    {
        $kunjungan = Kunjungan::factory()->create();

        return [
            'no_tagihan' => app(NomorDokumen::class)->berikutnya('tagihan'),
            'kunjungan_id' => $kunjungan->id,
            'penjamin_id' => $kunjungan->penjamin_id,
            'total' => 0,
            'ditanggung_penjamin' => 0,
            'ditagihkan_ke_pasien' => 0,
            'status' => StatusTagihan::BelumBayar,
            'disusun_pada' => now(),
        ];
    }
}
