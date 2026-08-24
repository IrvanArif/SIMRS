<?php

namespace Database\Seeders;

use App\Enums\JenisLayanan;
use App\Models\ParameterLab;
use App\Models\PemeriksaanLab;
use App\Models\Penjamin;
use App\Models\RujukanLab;
use App\Models\Tarif;
use Illuminate\Database\Seeder;

/**
 * Sepuluh pemeriksaan yang paling sering dipesan di rawat jalan, beserta
 * parameter dan nilai rujukannya. Rujukan dibedakan menurut jenis kelamin
 * hanya pada parameter yang rentang normalnya memang berbeda.
 */
class LaboratoriumSeeder extends Seeder
{
    /**
     * kode => [nama, kategori, tarif umum, [parameter...]]
     * parameter => [kode, nama, satuan, [jenis_kelamin => [min, maks]]]
     */
    private const PEMERIKSAAN = [
        ['LAB001', 'Darah Rutin', 'hematologi', 75000, [
            ['HB', 'Hemoglobin', 'g/dL', ['L' => [13.0, 17.0], 'P' => [12.0, 15.0]]],
            ['LEU', 'Leukosit', '10^3/uL', ['semua' => [4.0, 11.0]]],
            ['TRO', 'Trombosit', '10^3/uL', ['semua' => [150, 450]]],
            ['HCT', 'Hematokrit', '%', ['L' => [40, 52], 'P' => [36, 47]]],
        ]],
        ['LAB002', 'Gula Darah Sewaktu', 'kimia_klinik', 35000, [
            ['GDS', 'Gula Darah Sewaktu', 'mg/dL', ['semua' => [70, 140]]],
        ]],
        ['LAB003', 'Gula Darah Puasa', 'kimia_klinik', 38000, [
            ['GDP', 'Gula Darah Puasa', 'mg/dL', ['semua' => [70, 110]]],
        ]],
        ['LAB004', 'Kolesterol Total', 'kimia_klinik', 55000, [
            ['CHOL', 'Kolesterol Total', 'mg/dL', ['semua' => [0, 200]]],
        ]],
        ['LAB005', 'Asam Urat', 'kimia_klinik', 50000, [
            ['UA', 'Asam Urat', 'mg/dL', ['L' => [3.4, 7.0], 'P' => [2.4, 5.7]]],
        ]],
        ['LAB006', 'Fungsi Ginjal', 'kimia_klinik', 120000, [
            ['UR', 'Ureum', 'mg/dL', ['semua' => [15, 40]]],
            ['CR', 'Kreatinin', 'mg/dL', ['L' => [0.7, 1.3], 'P' => [0.6, 1.1]]],
        ]],
        ['LAB007', 'Fungsi Hati', 'kimia_klinik', 135000, [
            ['SGOT', 'SGOT', 'U/L', ['L' => [0, 37], 'P' => [0, 31]]],
            ['SGPT', 'SGPT', 'U/L', ['L' => [0, 41], 'P' => [0, 31]]],
        ]],
        ['LAB008', 'Urinalisis', 'urinalisis', 45000, [
            ['BJ', 'Berat Jenis', '', ['semua' => [1.005, 1.030]]],
            ['PH', 'pH Urine', '', ['semua' => [4.6, 8.0]]],
        ]],
        ['LAB009', 'Widal', 'imunologi', 95000, [
            ['TO', 'Titer O', '', ['semua' => [0, 80]]],
        ]],
        ['LAB010', 'Tes Kehamilan', 'imunologi', 40000, [
            ['HCG', 'Beta HCG', 'mIU/mL', ['semua' => [0, 5]]],
        ]],
    ];

    public function run(): void
    {
        $penjamin = Penjamin::pluck('id', 'kode');

        foreach (self::PEMERIKSAAN as [$kode, $nama, $kategori, $tarifUmum, $daftarParameter]) {
            $pemeriksaan = PemeriksaanLab::updateOrCreate(
                ['kode' => $kode],
                ['nama' => $nama, 'kategori' => $kategori, 'aktif' => true]
            );

            foreach ($daftarParameter as $urutan => [$kodeParam, $namaParam, $satuan, $rujukan]) {
                $parameter = ParameterLab::updateOrCreate(
                    ['pemeriksaan_lab_id' => $pemeriksaan->id, 'kode' => $kodeParam],
                    ['nama' => $namaParam, 'satuan' => $satuan, 'urutan' => $urutan + 1]
                );

                foreach ($rujukan as $jenisKelamin => [$min, $maks]) {
                    RujukanLab::updateOrCreate(
                        ['parameter_lab_id' => $parameter->id, 'jenis_kelamin' => $jenisKelamin],
                        ['nilai_min' => $min, 'nilai_maks' => $maks]
                    );
                }
            }

            // Tarif BPJS sekitar 70% tarif umum, dibulatkan ke ribuan.
            foreach (['UMUM' => $tarifUmum, 'BPJS' => (int) (round($tarifUmum * 0.7 / 1000) * 1000)] as $kodePenjamin => $harga) {
                Tarif::updateOrCreate([
                    'jenis_layanan' => JenisLayanan::Lab,
                    'layanan_id' => $pemeriksaan->id,
                    'penjamin_id' => $penjamin[$kodePenjamin],
                    'berlaku_mulai' => '2026-01-01',
                ], ['harga' => $harga]);
            }
        }
    }
}
