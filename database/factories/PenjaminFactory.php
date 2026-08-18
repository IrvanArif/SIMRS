<?php

namespace Database\Factories;

use App\Models\Penjamin;
use Illuminate\Database\Eloquent\Factories\Factory;

class PenjaminFactory extends Factory
{
    protected $model = Penjamin::class;

    public function definition(): array
    {
        return [
            'kode' => strtoupper($this->faker->unique()->lexify('PJ???')),
            'nama' => $this->faker->company(),
            'jenis' => 'tunai',
            'aktif' => true,
        ];
    }
}
