<?php

namespace Database\Seeders;

use App\Enums\JenisDiagnosa;
use App\Enums\MetodePembayaran;
use App\Enums\Peran;
use App\Models\Dokter;
use App\Models\Icd10;
use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\Pasien;
use App\Models\Penjamin;
use App\Models\Tindakan;
use App\Models\User;
use App\Services\PemeriksaanKlinis;
use App\Services\PendaftaranKunjungan;
use App\Services\PenulisanResep;
use App\Services\ProsesPembayaran;
use App\Services\TindakanPelayanan;
use Illuminate\Database\Seeder;

/**
 * Data dummy dibuat lewat service, bukan insert langsung, supaya tetap patuh
 * pada seluruh aturan bisnis: penomoran, status, tarif, dan audit trail.
 */
class KunjunganDummySeeder extends Seeder
{
    public function run(): void
    {
        $pasien = Pasien::inRandomOrder()->limit(50)->get();
        $dokter = Dokter::with('poli')->get();
        $penjamin = Penjamin::pluck('id', 'kode');
        $konsultasi = Tindakan::where('kategori', 'konsultasi')->get();
        $tindakanMedis = Tindakan::where('kategori', 'tindakan_medis')->get();
        $icd = Icd10::inRandomOrder()->limit(30)->get();
        $obat = Obat::inRandomOrder()->limit(20)->get();

        $admisi = User::role(Peran::Admisi->value)->first();
        $perawat = User::role(Peran::Perawat->value)->first();
        $dokterUser = User::role(Peran::Dokter->value)->first();
        $kasir = User::role(Peran::Kasir->value)->first();

        $pendaftaran = app(PendaftaranKunjungan::class);
        $klinis = app(PemeriksaanKlinis::class);
        $pelayanan = app(TindakanPelayanan::class);
        $resep = app(PenulisanResep::class);
        $pembayaran = app(ProsesPembayaran::class);

        $selesai = 0;
        $dibayar = 0;

        foreach ($pasien as $indeks => $orang) {
            $dokterTerpilih = $dokter->random();
            $pakaiBpjs = $indeks % 2 === 0;

            $kunjungan = $pendaftaran->daftarkan([
                'pasien_id' => $orang->id,
                'poli_id' => $dokterTerpilih->poli_id,
                'dokter_id' => $dokterTerpilih->id,
                'penjamin_id' => $pakaiBpjs ? $penjamin['BPJS'] : $penjamin['UMUM'],
                'no_kartu_penjamin' => $pakaiBpjs ? fake()->numerify('#############') : null,
                'tanggal' => now()->toDateString(),
            ], $admisi);

            // 30 kunjungan pertama dibiarkan masih mengantre.
            if ($indeks < 30) {
                continue;
            }

            $klinis->catatVital($kunjungan, [
                'sistolik' => rand(100, 145), 'diastolik' => rand(60, 95), 'nadi' => rand(60, 100),
                'suhu' => rand(360, 385) / 10, 'respirasi' => rand(14, 24),
                'berat_badan' => rand(400, 900) / 10, 'tinggi_badan' => rand(145, 180),
                'keluhan_awal' => 'Keluhan pasien saat datang ke poli.',
            ], $perawat);

            $klinis->catatSoap($kunjungan, [
                'subjective' => 'Pasien mengeluh sesuai keluhan awal.',
                'objective' => 'Pemeriksaan fisik dalam batas yang dicatat perawat.',
                'assessment' => 'Diagnosa kerja sesuai kode ICD-10 terlampir.',
                'plan' => 'Terapi simtomatik dan kontrol bila keluhan memberat.',
            ], $dokterUser);

            $klinis->tambahDiagnosa($kunjungan, $icd->random()->id, JenisDiagnosa::Primer);

            $pelayanan->tambah($kunjungan, $konsultasi->random()->id, 1, $dokterUser);

            if ($indeks % 3 === 0) {
                $pelayanan->tambah($kunjungan, $tindakanMedis->random()->id, 1, $dokterUser);
            }

            $resep->tulis($kunjungan, [[
                'obat_id' => $obat->random()->id,
                'jumlah' => rand(5, 15),
                'aturan_pakai' => '3x1 sesudah makan',
            ]], $dokterUser);

            $klinis->selesaikan($kunjungan, $dokterUser);
            $selesai++;

            // Hanya sebagian tagihan umum yang dilunasi, supaya layar kasir tetap
            // punya antrean pekerjaan saat sistem didemokan.
            $tagihan = $kunjungan->refresh()->tagihan;

            if (! $pakaiBpjs && $dibayar < 5) {
                $pembayaran->bayar($tagihan, MetodePembayaran::Tunai, (int) $tagihan->ditagihkan_ke_pasien, $kasir);
                $dibayar++;
            }
        }

        $belumLunas = \App\Models\Tagihan::where('status', \App\Enums\StatusTagihan::BelumBayar)->count();

        $this->command?->info(
            "Kunjungan dummy: {$selesai} selesai, {$dibayar} sudah dibayar, {$belumLunas} menunggu di kasir."
        );
    }
}
