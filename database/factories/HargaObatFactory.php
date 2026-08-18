<?php

namespace Database\Factories;

use App\Models\HargaObat;
use App\Models\Obat;
use App\Models\Penjamin;
use Illuminate\Database\Eloquent\Factories\Factory;

class HargaObatFactory extends Factory
{
    protected $model = HargaObat::class;

    public function definition(): array
    {
        return [
            'obat_id' => Obat::factory(),
            'penjamin_id' => Penjamin::factory(),
            'harga' => $this->faker->numberBetween(500, 50000),
            'berlaku_mulai' => '2026-01-01',
        ];
    }
}
