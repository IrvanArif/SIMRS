<?php

namespace Database\Factories;

use App\Models\ParameterLab;
use App\Models\PemeriksaanLab;
use Illuminate\Database\Eloquent\Factories\Factory;

class ParameterLabFactory extends Factory
{
    protected $model = ParameterLab::class;

    public function definition(): array
    {
        return [
            'pemeriksaan_lab_id' => PemeriksaanLab::factory(),
            'kode' => strtoupper($this->faker->unique()->lexify('P??')),
            'nama' => 'Parameter '.$this->faker->unique()->word(),
            'satuan' => 'mg/dL',
            'urutan' => 1,
        ];
    }
}
