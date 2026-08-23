<?php

namespace Database\Factories;

use App\Models\PemeriksaanLab;
use Illuminate\Database\Eloquent\Factories\Factory;

class PemeriksaanLabFactory extends Factory
{
    protected $model = PemeriksaanLab::class;

    public function definition(): array
    {
        return [
            'kode' => strtoupper($this->faker->unique()->bothify('LAB###')),
            'nama' => 'Pemeriksaan '.$this->faker->unique()->word(),
            'kategori' => 'kimia_klinik',
            'aktif' => true,
        ];
    }
}
