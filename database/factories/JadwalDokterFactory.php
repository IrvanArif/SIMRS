<?php

namespace Database\Factories;

use App\Models\Dokter;
use App\Models\JadwalDokter;
use Illuminate\Database\Eloquent\Factories\Factory;

class JadwalDokterFactory extends Factory
{
    protected $model = JadwalDokter::class;

    public function definition(): array
    {
        return [
            'dokter_id' => Dokter::factory(),
            'hari' => $this->faker->numberBetween(1, 5),
            'jam_mulai' => '08:00:00',
            'jam_selesai' => '12:00:00',
            'kuota' => 30,
        ];
    }
}
