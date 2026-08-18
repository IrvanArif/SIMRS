<?php

namespace Database\Factories;

use App\Models\Pasien;
use App\Services\NomorRekamMedis;
use Illuminate\Database\Eloquent\Factories\Factory;

class PasienFactory extends Factory
{
    protected $model = Pasien::class;

    public function definition(): array
    {
        return [
            'no_rm' => app(NomorRekamMedis::class)->berikutnya(),
            'nik' => $this->faker->unique()->numerify('################'),
            'nama' => $this->faker->name(),
            'tempat_lahir' => $this->faker->city(),
            'tanggal_lahir' => $this->faker->dateTimeBetween('-80 years', '-1 year')->format('Y-m-d'),
            'jenis_kelamin' => $this->faker->randomElement(['L', 'P']),
            'alamat' => $this->faker->streetAddress(),
            'kelurahan' => 'Sukamaju',
            'kecamatan' => 'Sukamaju',
            'kabupaten' => 'Kabupaten Sampel',
            'no_hp' => $this->faker->numerify('08##########'),
        ];
    }
}
