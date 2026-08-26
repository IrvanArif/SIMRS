<?php

namespace Database\Factories;

use App\Models\Bed;
use App\Models\KelasKamar;
use App\Models\Ruang;
use Illuminate\Database\Eloquent\Factories\Factory;

class BedFactory extends Factory
{
    protected $model = Bed::class;

    public function definition(): array
    {
        return [
            'ruang_id' => Ruang::factory(),
            'kelas_kamar_id' => KelasKamar::factory(),
            'nomor' => str_pad((string) $this->faker->unique()->numberBetween(1, 999), 2, '0', STR_PAD_LEFT),
            'rawat_inap_id' => null,
            'aktif' => true,
        ];
    }
}
