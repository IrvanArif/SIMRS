<?php

namespace Database\Factories;

use App\Models\PemeriksaanRadiologi;
use Illuminate\Database\Eloquent\Factories\Factory;

class PemeriksaanRadiologiFactory extends Factory
{
    protected $model = PemeriksaanRadiologi::class;

    public function definition(): array
    {
        return [
            'kode' => strtoupper($this->faker->unique()->bothify('RAD###')),
            'nama' => 'Pemeriksaan '.$this->faker->unique()->word(),
            'modalitas' => 'rontgen',
            'persiapan' => null,
            'aktif' => true,
        ];
    }
}
