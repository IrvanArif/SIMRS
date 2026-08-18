<?php

namespace Database\Factories;

use App\Models\Poli;
use Illuminate\Database\Eloquent\Factories\Factory;

class PoliFactory extends Factory
{
    protected $model = Poli::class;

    public function definition(): array
    {
        return [
            'kode' => strtoupper($this->faker->unique()->lexify('P??')),
            'nama' => 'Poli '.$this->faker->unique()->word(),
            'lokasi' => 'Lantai '.$this->faker->numberBetween(1, 3),
            'aktif' => true,
        ];
    }
}
