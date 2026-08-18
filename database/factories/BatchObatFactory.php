<?php

namespace Database\Factories;

use App\Models\BatchObat;
use App\Models\Obat;
use Illuminate\Database\Eloquent\Factories\Factory;

class BatchObatFactory extends Factory
{
    protected $model = BatchObat::class;

    public function definition(): array
    {
        $jumlah = $this->faker->numberBetween(50, 200);

        return [
            'obat_id' => Obat::factory(),
            'no_batch' => strtoupper($this->faker->unique()->bothify('B####??')),
            'tanggal_kedaluwarsa' => $this->faker->dateTimeBetween('+6 months', '+3 years')->format('Y-m-d'),
            'jumlah_awal' => $jumlah,
            'jumlah_tersisa' => $jumlah,
            'harga_beli' => $this->faker->numberBetween(300, 30000),
            'diterima_pada' => now(),
        ];
    }
}
