<?php

namespace Database\Factories;

use App\Models\Ruang;
use Illuminate\Database\Eloquent\Factories\Factory;

class RuangFactory extends Factory
{
    protected $model = Ruang::class;

    public function definition(): array
    {
        return [
            'kode' => strtoupper($this->faker->unique()->bothify('RG##')),
            'nama' => 'Ruang '.$this->faker->unique()->word(),
            'lantai' => 'Lantai '.$this->faker->numberBetween(1, 3),
            'aktif' => true,
        ];
    }
}
