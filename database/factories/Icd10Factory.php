<?php

namespace Database\Factories;

use App\Models\Icd10;
use Illuminate\Database\Eloquent\Factories\Factory;

class Icd10Factory extends Factory
{
    protected $model = Icd10::class;

    public function definition(): array
    {
        return [
            'kode' => strtoupper($this->faker->unique()->bothify('?##.#')),
            'nama_id' => 'Diagnosa '.$this->faker->unique()->word(),
            'nama_en' => null,
        ];
    }
}
