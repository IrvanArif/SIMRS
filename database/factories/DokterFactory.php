<?php

namespace Database\Factories;

use App\Models\Dokter;
use App\Models\Poli;
use Illuminate\Database\Eloquent\Factories\Factory;

class DokterFactory extends Factory
{
    protected $model = Dokter::class;

    public function definition(): array
    {
        return [
            'nip' => $this->faker->unique()->numerify('##########'),
            'nama' => 'dr. '.$this->faker->name(),
            'spesialisasi' => 'Umum',
            'no_sip' => $this->faker->unique()->numerify('SIP-####'),
            'poli_id' => Poli::factory(),
            'aktif' => true,
        ];
    }
}
