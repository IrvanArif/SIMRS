<?php

namespace Database\Factories;

use App\Models\Obat;
use Illuminate\Database\Eloquent\Factories\Factory;

class ObatFactory extends Factory
{
    protected $model = Obat::class;

    public function definition(): array
    {
        return [
            'kode' => strtoupper($this->faker->unique()->bothify('OB###')),
            'nama' => 'Obat '.$this->faker->unique()->word(),
            'satuan' => $this->faker->randomElement(['tablet', 'kapsul', 'botol', 'tube']),
            'bentuk_sediaan' => $this->faker->randomElement(['Tablet', 'Kapsul', 'Sirup', 'Salep']),
            'aktif' => true,
        ];
    }
}
