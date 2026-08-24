<?php

namespace Database\Seeders;

use App\Enums\Peran;
use App\Models\Dokter;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class PenggunaSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException(
                'PenggunaSeeder memakai kata sandi seragam dan hanya boleh dijalankan di lingkungan lokal.'
            );
        }

        $daftar = [
            [Peran::Admisi, 'Petugas Admisi', 'admisi@rs.test'],
            [Peran::Perawat, 'Perawat Poli', 'perawat@rs.test'],
            [Peran::RekamMedis, 'Petugas Rekam Medis', 'rekammedis@rs.test'],
            [Peran::Kasir, 'Kasir Rawat Jalan', 'kasir@rs.test'],
            [Peran::Apoteker, 'Apoteker', 'apoteker@rs.test'],
            [Peran::Analis, 'Analis Laboratorium', 'analis@rs.test'],
            [Peran::Admin, 'Administrator', 'admin@rs.test'],
        ];

        foreach ($daftar as [$peran, $nama, $email]) {
            User::updateOrCreate(
                ['email' => $email],
                ['name' => $nama, 'password' => Hash::make('rahasia123'), 'aktif' => true]
            )->syncRoles([$peran->value]);
        }

        $dokter = Dokter::orderBy('id')->first();

        User::updateOrCreate(
            ['email' => 'dokter@rs.test'],
            [
                'name' => $dokter->nama,
                'password' => Hash::make('rahasia123'),
                'dokter_id' => $dokter->id,
                'aktif' => true,
            ]
        )->syncRoles([Peran::Dokter->value]);
    }
}
