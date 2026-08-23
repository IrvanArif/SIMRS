<?php

namespace Database\Factories;

use App\Models\ParameterLab;
use App\Models\RujukanLab;
use Illuminate\Database\Eloquent\Factories\Factory;

class RujukanLabFactory extends Factory
{
    protected $model = RujukanLab::class;

    public function definition(): array
    {
        return [
            'parameter_lab_id' => ParameterLab::factory(),
            'jenis_kelamin' => 'semua',
            'nilai_min' => 0,
            'nilai_maks' => 100,
        ];
    }
}
