<?php

namespace Database\Seeders;

use App\Models\Pasien;
use Illuminate\Database\Seeder;

class PasienDummySeeder extends Seeder
{
    public function run(): void
    {
        Pasien::factory()->count(100)->create();
    }
}
