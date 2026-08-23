<?php

namespace Database\Factories;

use App\Enums\JenisLayanan;
use App\Models\Penjamin;
use App\Models\Tarif;
use App\Models\Tindakan;
use Illuminate\Database\Eloquent\Factories\Factory;

class TarifFactory extends Factory
{
    protected $model = Tarif::class;

    public function definition(): array
    {
        return [
            'jenis_layanan' => JenisLayanan::Tindakan,
            'layanan_id' => Tindakan::factory(),
            'penjamin_id' => Penjamin::factory(),
            'harga' => $this->faker->numberBetween(1000, 500000),
            'berlaku_mulai' => '2026-01-01',
        ];
    }
}
