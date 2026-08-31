<?php

namespace Database\Seeders;

use App\Enums\JenisLayanan;
use App\Models\Bed;
use App\Models\KelasKamar;
use App\Models\Penjamin;
use App\Models\Ruang;
use App\Models\Tarif;
use Illuminate\Database\Seeder;

/**
 * Empat bangsal berisi empat puluh bed. Kelas melekat pada bednya, bukan pada
 * ruangnya, sehingga satu bangsal bisa memuat lebih dari satu kelas — dan di
 * sini Anggrek memang begitu.
 */
class RawatInapSeeder extends Seeder
{
    /** kode => [nama, urutan, tarif umum per hari] */
    private const KELAS = [
        ['VIP', 'VIP', 1, 750000],
        ['K1', 'Kelas 1', 2, 450000],
        ['K2', 'Kelas 2', 3, 300000],
        ['K3', 'Kelas 3', 4, 175000],
    ];

    /** kode => [nama, lantai, [kode kelas => jumlah bed]] */
    private const RUANG = [
        ['RG-MEL', 'Melati', 'Lantai 1', ['K3' => 12]],
        ['RG-ANG', 'Anggrek', 'Lantai 2', ['K2' => 10, 'K3' => 4]],
        ['RG-MAW', 'Mawar', 'Lantai 2', ['K1' => 8]],
        ['RG-CEN', 'Cendana', 'Lantai 3', ['VIP' => 6]],
    ];

    public function run(): void
    {
        $penjamin = Penjamin::pluck('id', 'kode');
        $kelas = [];

        foreach (self::KELAS as [$kode, $nama, $urutan, $tarifUmum]) {
            $kelas[$kode] = KelasKamar::updateOrCreate(
                ['kode' => $kode],
                ['nama' => $nama, 'urutan' => $urutan, 'aktif' => true]
            );

            // Tarif BPJS sekitar 70% tarif umum, dibulatkan ke ribuan.
            foreach (['UMUM' => $tarifUmum, 'BPJS' => (int) (round($tarifUmum * 0.7 / 1000) * 1000)] as $kodePenjamin => $harga) {
                Tarif::updateOrCreate([
                    'jenis_layanan' => JenisLayanan::Kamar,
                    'layanan_id' => $kelas[$kode]->id,
                    'penjamin_id' => $penjamin[$kodePenjamin],
                    'berlaku_mulai' => '2026-01-01',
                ], ['harga' => $harga]);
            }
        }

        foreach (self::RUANG as [$kodeRuang, $namaRuang, $lantai, $susunan]) {
            $ruang = Ruang::updateOrCreate(
                ['kode' => $kodeRuang],
                ['nama' => $namaRuang, 'lantai' => $lantai, 'aktif' => true]
            );

            $nomor = 1;

            foreach ($susunan as $kodeKelas => $jumlah) {
                for ($i = 0; $i < $jumlah; $i++) {
                    Bed::updateOrCreate(
                        ['ruang_id' => $ruang->id, 'nomor' => str_pad((string) $nomor, 2, '0', STR_PAD_LEFT)],
                        ['kelas_kamar_id' => $kelas[$kodeKelas]->id, 'aktif' => true]
                    );

                    $nomor++;
                }
            }
        }
    }
}
