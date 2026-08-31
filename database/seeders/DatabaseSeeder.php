<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Sengaja tanpa trait WithoutModelEvents: observer audit bergantung pada
     * model event, jadi data dummy pun harus meninggalkan jejak di audit_logs.
     */
    public function run(): void
    {
        $this->call([
            PeranSeeder::class,
            MasterSeeder::class,
            Icd10Seeder::class,
            PenggunaSeeder::class,
            FarmasiSeeder::class,
            LaboratoriumSeeder::class,
            RadiologiSeeder::class,
            RawatInapSeeder::class,
            PasienDummySeeder::class,
            KunjunganDummySeeder::class,
        ]);
    }
}
