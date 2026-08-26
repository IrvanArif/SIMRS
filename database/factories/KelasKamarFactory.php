<?php

namespace Database\Factories;

use App\Models\KelasKamar;
use Illuminate\Database\Eloquent\Factories\Factory;

class KelasKamarFactory extends Factory
{
    protected $model = KelasKamar::class;

    public function definition(): array
    {
        return [
            'kode' => strtoupper($this->faker->unique()->bothify('KLS##')),
            'nama' => 'Kelas '.$this->faker->unique()->numberBetween(1, 999),
            'urutan' => 1,
            'aktif' => true,
        ];
    }
}
