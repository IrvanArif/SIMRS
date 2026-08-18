<?php

namespace Database\Factories;

use App\Models\Kunjungan;
use App\Models\Pemeriksaan;
use Illuminate\Database\Eloquent\Factories\Factory;

class PemeriksaanFactory extends Factory
{
    protected $model = Pemeriksaan::class;

    public function definition(): array
    {
        return [
            'kunjungan_id' => Kunjungan::factory(),
            'sistolik' => $this->faker->numberBetween(100, 140),
            'diastolik' => $this->faker->numberBetween(60, 90),
            'nadi' => $this->faker->numberBetween(60, 100),
            'suhu' => $this->faker->randomFloat(1, 36, 38),
            'respirasi' => $this->faker->numberBetween(12, 24),
            'berat_badan' => $this->faker->randomFloat(1, 40, 90),
            'tinggi_badan' => $this->faker->numberBetween(140, 185),
            'keluhan_awal' => 'Keluhan umum',
        ];
    }
}
