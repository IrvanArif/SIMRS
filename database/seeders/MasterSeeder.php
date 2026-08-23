<?php

namespace Database\Seeders;

use App\Models\Dokter;
use App\Models\JadwalDokter;
use App\Models\Obat;
use App\Models\Penjamin;
use App\Models\Poli;
use App\Enums\JenisLayanan;
use App\Models\Tarif;
use App\Models\Tindakan;
use Illuminate\Database\Seeder;

class MasterSeeder extends Seeder
{
    public function run(): void
    {
        $poli = $this->poli();
        $this->dokter($poli);
        $penjamin = $this->penjamin();
        $this->tindakanDanTarif($penjamin);
        $this->obat();
    }

    /** @return array<string, Poli> */
    private function poli(): array
    {
        $daftar = [
            ['UMU', 'Poli Umum', 'Lantai 1 Blok A'],
            ['GIG', 'Poli Gigi', 'Lantai 1 Blok B'],
            ['ANK', 'Poli Anak', 'Lantai 2 Blok A'],
            ['KDG', 'Poli Kandungan', 'Lantai 2 Blok B'],
            ['PDL', 'Poli Penyakit Dalam', 'Lantai 2 Blok C'],
        ];

        $hasil = [];

        foreach ($daftar as [$kode, $nama, $lokasi]) {
            $hasil[$kode] = Poli::updateOrCreate(['kode' => $kode], [
                'nama' => $nama, 'lokasi' => $lokasi, 'aktif' => true,
            ]);
        }

        return $hasil;
    }

    /** @param array<string, Poli> $poli */
    private function dokter(array $poli): void
    {
        $daftar = [
            ['1978031220060410', 'dr. Andi Wijaya', 'Umum', 'UMU'],
            ['1981070520080411', 'dr. Rina Kusuma', 'Umum', 'UMU'],
            ['1985111020100412', 'drg. Bayu Prasetyo', 'Gigi dan Mulut', 'GIG'],
            ['1987022520110413', 'drg. Sinta Maharani', 'Gigi dan Mulut', 'GIG'],
            ['1979091520050414', 'dr. Hendra Gunawan, Sp.A', 'Anak', 'ANK'],
            ['1983041820090415', 'dr. Maya Sari, Sp.A', 'Anak', 'ANK'],
            ['1976052020040416', 'dr. Lestari Ningsih, Sp.OG', 'Obstetri dan Ginekologi', 'KDG'],
            ['1984123020120417', 'dr. Yusuf Ramadhan, Sp.OG', 'Obstetri dan Ginekologi', 'KDG'],
            ['1975081020030418', 'dr. Slamet Riyadi, Sp.PD', 'Penyakit Dalam', 'PDL'],
            ['1986061520130419', 'dr. Dewi Anggraini, Sp.PD', 'Penyakit Dalam', 'PDL'],
        ];

        foreach ($daftar as [$nip, $nama, $spesialisasi, $kodePoli]) {
            $dokter = Dokter::updateOrCreate(['nip' => $nip], [
                'nama' => $nama,
                'spesialisasi' => $spesialisasi,
                'no_sip' => 'SIP-'.substr($nip, -4),
                'poli_id' => $poli[$kodePoli]->id,
                'aktif' => true,
            ]);

            // Senin sampai Jumat, 08:00–12:00, kuota 30 pasien per hari.
            foreach (range(1, 5) as $hari) {
                JadwalDokter::updateOrCreate(
                    ['dokter_id' => $dokter->id, 'hari' => $hari, 'jam_mulai' => '08:00:00'],
                    ['jam_selesai' => '12:00:00', 'kuota' => 30]
                );
            }
        }
    }

    /** @return array<string, Penjamin> */
    private function penjamin(): array
    {
        return [
            'UMUM' => Penjamin::updateOrCreate(['kode' => 'UMUM'], [
                'nama' => 'Umum (Tunai)', 'jenis' => 'tunai', 'aktif' => true,
            ]),
            'BPJS' => Penjamin::updateOrCreate(['kode' => 'BPJS'], [
                'nama' => 'BPJS Kesehatan', 'jenis' => 'penjamin', 'aktif' => true,
            ]),
        ];
    }

    /** @param array<string, Penjamin> $penjamin */
    private function tindakanDanTarif(array $penjamin): void
    {
        $daftar = [
            ['ADM001', 'Pendaftaran Rawat Jalan', 'administrasi', 15000],
            ['ADM002', 'Pembuatan Kartu Berobat', 'administrasi', 10000],
            ['ADM003', 'Surat Keterangan Sehat', 'administrasi', 25000],
            ['ADM004', 'Surat Keterangan Sakit', 'administrasi', 20000],
            ['KON001', 'Konsultasi Dokter Umum', 'konsultasi', 50000],
            ['KON002', 'Konsultasi Dokter Gigi', 'konsultasi', 65000],
            ['KON003', 'Konsultasi Dokter Spesialis Anak', 'konsultasi', 120000],
            ['KON004', 'Konsultasi Dokter Spesialis Kandungan', 'konsultasi', 130000],
            ['KON005', 'Konsultasi Dokter Spesialis Penyakit Dalam', 'konsultasi', 130000],
            ['KON006', 'Konsultasi Gizi', 'konsultasi', 40000],
            ['TIN001', 'Injeksi Intramuskular', 'tindakan_medis', 25000],
            ['TIN002', 'Injeksi Intravena', 'tindakan_medis', 35000],
            ['TIN003', 'Pemasangan Infus', 'tindakan_medis', 75000],
            ['TIN004', 'Nebulisasi', 'tindakan_medis', 60000],
            ['TIN005', 'Jahit Luka 1-5 Jahitan', 'tindakan_medis', 150000],
            ['TIN006', 'Jahit Luka 6-10 Jahitan', 'tindakan_medis', 250000],
            ['TIN007', 'Angkat Jahitan', 'tindakan_medis', 50000],
            ['TIN008', 'Perawatan Luka Sederhana', 'tindakan_medis', 45000],
            ['TIN009', 'Perawatan Luka Kompleks', 'tindakan_medis', 120000],
            ['TIN010', 'Ekstraksi Kuku', 'tindakan_medis', 200000],
            ['TIN011', 'Cabut Gigi Susu', 'tindakan_medis', 75000],
            ['TIN012', 'Cabut Gigi Permanen', 'tindakan_medis', 175000],
            ['TIN013', 'Tambal Gigi Sementara', 'tindakan_medis', 90000],
            ['TIN014', 'Tambal Gigi Permanen', 'tindakan_medis', 165000],
            ['TIN015', 'Pembersihan Karang Gigi', 'tindakan_medis', 250000],
            ['TIN016', 'Elektrokardiografi (EKG)', 'tindakan_medis', 100000],
            ['TIN017', 'Pemeriksaan Tekanan Darah', 'tindakan_medis', 15000],
            ['TIN018', 'Pemeriksaan Gula Darah Sewaktu', 'tindakan_medis', 30000],
            ['TIN019', 'Imunisasi Dasar', 'tindakan_medis', 85000],
            ['TIN020', 'Pemeriksaan Kehamilan (ANC)', 'tindakan_medis', 110000],
            ['TIN021', 'Pemeriksaan USG Kandungan', 'tindakan_medis', 200000],
            ['TIN022', 'Papsmear', 'tindakan_medis', 275000],
            ['TIN023', 'Pemasangan Kontrasepsi IUD', 'tindakan_medis', 350000],
            ['TIN024', 'Pelepasan Kontrasepsi IUD', 'tindakan_medis', 175000],
            ['TIN025', 'Suntik KB', 'tindakan_medis', 60000],
            ['TIN026', 'Irigasi Telinga', 'tindakan_medis', 80000],
            ['TIN027', 'Ekstraksi Benda Asing Telinga', 'tindakan_medis', 120000],
            ['TIN028', 'Insisi Abses', 'tindakan_medis', 180000],
            ['TIN029', 'Sirkumsisi', 'tindakan_medis', 750000],
            ['TIN030', 'Pemeriksaan Visus Mata', 'tindakan_medis', 40000],
        ];

        foreach ($daftar as [$kode, $nama, $kategori, $tarifUmum]) {
            $tindakan = Tindakan::updateOrCreate(['kode' => $kode], [
                'nama' => $nama, 'kategori' => $kategori, 'aktif' => true,
            ]);

            // Tarif BPJS dibuat sekitar 70% tarif umum, dibulatkan ke ribuan terdekat.
            $tarifBpjs = (int) (round($tarifUmum * 0.7 / 1000) * 1000);

            foreach (['UMUM' => $tarifUmum, 'BPJS' => $tarifBpjs] as $kodePenjamin => $tarif) {
                Tarif::updateOrCreate([
                    'jenis_layanan' => JenisLayanan::Tindakan,
                    'layanan_id' => $tindakan->id,
                    'penjamin_id' => $penjamin[$kodePenjamin]->id,
                    'berlaku_mulai' => '2026-01-01',
                ], ['harga' => $tarif]);
            }
        }
    }

    private function obat(): void
    {
        $daftar = [
            ['Paracetamol 500 mg', 'tablet', 'Tablet'],
            ['Paracetamol Sirup 120 mg/5 ml', 'botol', 'Sirup'],
            ['Ibuprofen 400 mg', 'tablet', 'Tablet'],
            ['Asam Mefenamat 500 mg', 'tablet', 'Tablet'],
            ['Natrium Diklofenak 50 mg', 'tablet', 'Tablet'],
            ['Amoksisilin 500 mg', 'kapsul', 'Kapsul'],
            ['Amoksisilin Sirup Kering', 'botol', 'Sirup'],
            ['Cefadroxil 500 mg', 'kapsul', 'Kapsul'],
            ['Ciprofloxacin 500 mg', 'tablet', 'Tablet'],
            ['Kotrimoksazol 480 mg', 'tablet', 'Tablet'],
            ['Eritromisin 500 mg', 'tablet', 'Tablet'],
            ['Metronidazol 500 mg', 'tablet', 'Tablet'],
            ['Ambroxol 30 mg', 'tablet', 'Tablet'],
            ['Gliseril Guaiakolat 100 mg', 'tablet', 'Tablet'],
            ['OBH Sirup', 'botol', 'Sirup'],
            ['Salbutamol 2 mg', 'tablet', 'Tablet'],
            ['Cetirizine 10 mg', 'tablet', 'Tablet'],
            ['Loratadine 10 mg', 'tablet', 'Tablet'],
            ['CTM 4 mg', 'tablet', 'Tablet'],
            ['Deksametason 0,5 mg', 'tablet', 'Tablet'],
            ['Metilprednisolon 4 mg', 'tablet', 'Tablet'],
            ['Antasida Doen', 'tablet', 'Tablet'],
            ['Ranitidin 150 mg', 'tablet', 'Tablet'],
            ['Omeprazol 20 mg', 'kapsul', 'Kapsul'],
            ['Sukralfat Sirup', 'botol', 'Sirup'],
            ['Domperidon 10 mg', 'tablet', 'Tablet'],
            ['Ondansetron 4 mg', 'tablet', 'Tablet'],
            ['Oralit', 'sachet', 'Serbuk'],
            ['Zinc 20 mg', 'tablet', 'Tablet'],
            ['Loperamid 2 mg', 'tablet', 'Tablet'],
            ['Attapulgit', 'tablet', 'Tablet'],
            ['Amlodipin 5 mg', 'tablet', 'Tablet'],
            ['Amlodipin 10 mg', 'tablet', 'Tablet'],
            ['Kaptopril 25 mg', 'tablet', 'Tablet'],
            ['Furosemid 40 mg', 'tablet', 'Tablet'],
            ['Bisoprolol 5 mg', 'tablet', 'Tablet'],
            ['Simvastatin 20 mg', 'tablet', 'Tablet'],
            ['Metformin 500 mg', 'tablet', 'Tablet'],
            ['Glibenklamid 5 mg', 'tablet', 'Tablet'],
            ['Allopurinol 100 mg', 'tablet', 'Tablet'],
            ['Vitamin B Kompleks', 'tablet', 'Tablet'],
            ['Vitamin C 50 mg', 'tablet', 'Tablet'],
            ['Asam Folat 1 mg', 'tablet', 'Tablet'],
            ['Tablet Tambah Darah (Fe)', 'tablet', 'Tablet'],
            ['Kalsium Laktat 500 mg', 'tablet', 'Tablet'],
            ['Hidrokortison Krim 2,5%', 'tube', 'Krim'],
            ['Gentamisin Salep 0,1%', 'tube', 'Salep'],
            ['Ketokonazol Krim 2%', 'tube', 'Krim'],
            ['Povidon Iodin 10%', 'botol', 'Larutan'],
            ['Oksitetrasiklin Salep Mata', 'tube', 'Salep'],
        ];

        foreach ($daftar as $indeks => [$nama, $satuan, $bentuk]) {
            Obat::updateOrCreate(['kode' => sprintf('OB%03d', $indeks + 1)], [
                'nama' => $nama, 'satuan' => $satuan, 'bentuk_sediaan' => $bentuk, 'aktif' => true,
            ]);
        }
    }
}
