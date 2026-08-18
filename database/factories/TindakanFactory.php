<?php

namespace Database\Factories;

use App\Models\Tindakan;
use Illuminate\Database\Eloquent\Factories\Factory;

class TindakanFactory extends Factory
{
    protected $model = Tindakan::class;

    public function definition(): array
    {
        return [
            'kode' => strtoupper($this->faker->unique()->bothify('TD###')),
            'nama' => 'Tindakan '.$this->faker->unique()->word(),
            'kategori' => 'tindakan_medis',
            'aktif' => true,
        ];
    }
}
