<?php

namespace Database\Seeders;

use App\Enums\JenisLayanan;
use App\Models\PemeriksaanRadiologi;
use App\Models\Penjamin;
use App\Models\Tarif;
use Illuminate\Database\Seeder;

/**
 * Dua belas pemeriksaan pencitraan yang lazim tersedia di rumah sakit tipe C,
 * mencakup kelima modalitas. Instruksi persiapan hanya diisi pada pemeriksaan
 * yang memang mensyaratkannya — mengisi semuanya justru membuat yang penting
 * tenggelam.
 */
class RadiologiSeeder extends Seeder
{
    /** kode => [nama, modalitas, tarif umum, persiapan] */
    private const PEMERIKSAAN = [
        ['RAD001', 'Rontgen Toraks PA', 'rontgen', 150000, null],
        ['RAD002', 'Rontgen Abdomen Polos', 'rontgen', 165000, null],
        ['RAD003', 'Rontgen Ekstremitas', 'rontgen', 140000, null],
        ['RAD004', 'Rontgen Panoramik Gigi', 'rontgen', 250000, 'Lepas perhiasan logam di kepala dan leher'],
        ['RAD005', 'USG Abdomen', 'usg', 220000, 'Puasa 6 jam sebelum pemeriksaan'],
        ['RAD006', 'USG Kandungan', 'usg', 200000, 'Menahan buang air kecil satu jam sebelumnya'],
        ['RAD007', 'USG Tiroid', 'usg', 210000, null],
        ['RAD008', 'CT Scan Kepala', 'ct_scan', 950000, 'Puasa 4 jam bila memakai kontras'],
        ['RAD009', 'CT Scan Toraks', 'ct_scan', 1100000, 'Puasa 4 jam bila memakai kontras'],
        ['RAD010', 'CT Scan Abdomen', 'ct_scan', 1250000, 'Puasa 6 jam'],
        ['RAD011', 'MRI Kepala', 'mri', 2100000, 'Lepas seluruh benda logam; beri tahu bila ada implan'],
        ['RAD012', 'Mammografi', 'mammografi', 450000, 'Tidak memakai deodoran atau bedak di area dada'],
    ];

    public function run(): void
    {
        $penjamin = Penjamin::pluck('id', 'kode');

        foreach (self::PEMERIKSAAN as [$kode, $nama, $modalitas, $tarifUmum, $persiapan]) {
            $pemeriksaan = PemeriksaanRadiologi::updateOrCreate(
                ['kode' => $kode],
                ['nama' => $nama, 'modalitas' => $modalitas, 'persiapan' => $persiapan, 'aktif' => true]
            );

            // Tarif BPJS sekitar 70% tarif umum, dibulatkan ke ribuan.
            foreach (['UMUM' => $tarifUmum, 'BPJS' => (int) (round($tarifUmum * 0.7 / 1000) * 1000)] as $kodePenjamin => $harga) {
                Tarif::updateOrCreate([
                    'jenis_layanan' => JenisLayanan::Radiologi,
                    'layanan_id' => $pemeriksaan->id,
                    'penjamin_id' => $penjamin[$kodePenjamin],
                    'berlaku_mulai' => '2026-01-01',
                ], ['harga' => $harga]);
            }
        }
    }
}
