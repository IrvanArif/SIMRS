<?php

namespace Database\Factories;

use App\Models\Icd9;
use Illuminate\Database\Eloquent\Factories\Factory;

class Icd9Factory extends Factory
{
    protected $model = Icd9::class;

    public function definition(): array
    {
        return [
            'kode' => $this->faker->unique()->numerify('##.##'),
            'nama' => 'Prosedur '.$this->faker->unique()->word(),
        ];
    }
}
