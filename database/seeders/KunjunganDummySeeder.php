<?php

namespace Database\Seeders;

use App\Enums\JenisDiagnosa;
use App\Enums\MetodePembayaran;
use App\Enums\Peran;
use App\Models\Dokter;
use App\Models\Icd10;
use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\PemeriksaanLab;
use App\Models\Pasien;
use App\Models\Penjamin;
use App\Models\Tindakan;
use App\Models\User;
use App\Services\PemeriksaanKlinis;
use App\Services\PemeriksaanLaboratorium;
use App\Services\PemesananLab;
use App\Services\PendaftaranKunjungan;
use App\Services\PenulisanResep;
use App\Services\PenyerahanObat;
use App\Services\PenyiapanResep;
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
        // Obat bersaldo tipis sengaja dikecualikan: obat itu ada untuk menguji
        // layar peringatan, bukan untuk diresepkan. Meresepkannya akan ditolak
        // aturan 24 dan menggagalkan seeder.
        $obat = Obat::orderBy('id')->get()
            ->filter(fn (Obat $o) => $o->stokTersedia() >= 20)
            ->values();

        $admisi = User::role(Peran::Admisi->value)->first();
        $perawat = User::role(Peran::Perawat->value)->first();
        $dokterUser = User::role(Peran::Dokter->value)->first();
        $kasir = User::role(Peran::Kasir->value)->first();
        $apoteker = User::role(Peran::Apoteker->value)->first();
        $analis = User::role(Peran::Analis->value)->first();

        $pendaftaran = app(PendaftaranKunjungan::class);
        $klinis = app(PemeriksaanKlinis::class);
        $pelayanan = app(TindakanPelayanan::class);
        $resep = app(PenulisanResep::class);
        $pembayaran = app(ProsesPembayaran::class);
        $penyiapan = app(PenyiapanResep::class);
        $penyerahan = app(PenyerahanObat::class);
        $pemesananLab = app(PemesananLab::class);
        $lab = app(PemeriksaanLaboratorium::class);
        $daftarPemeriksaanLab = PemeriksaanLab::where('aktif', true)->pluck('id');

        $selesai = 0;
        $orderLab = 0;
        $antreLab = 0;
        $disiapkan = 0;
        $diserahkan = 0;
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

            // 30 kunjungan pertama dibiarkan masih mengantre di poli. Sebagian
            // diberi order laboratorium yang sengaja berhenti di tiap tahap,
            // supaya keempat layar analis punya isi saat sistem didemokan.
            if ($indeks < 30) {
                if ($indeks < 11 && $daftarPemeriksaanLab->isNotEmpty()) {
                    $order = $pemesananLab->pesan(
                        $kunjungan,
                        [$daftarPemeriksaanLab->random()],
                        $dokterUser,
                        'Menunggu pengerjaan laboratorium.'
                    );
                    $antreLab++;

                    // 5 berhenti di "dipesan", 3 di "sampel diambil", 3 di "menunggu validasi".
                    if ($indeks >= 5) {
                        $lab->ambilSampel($order, $analis);
                    }

                    if ($indeks >= 8) {
                        $nilai = [];

                        foreach ($order->refresh()->detail as $detail) {
                            foreach ($detail->pemeriksaan->parameter as $parameter) {
                                $rujukan = $parameter->rujukan->first();
                                $nilai[$parameter->id] = $rujukan
                                    ? round($rujukan->nilai_maks * (rand(70, 130) / 100), 2)
                                    : rand(1, 100);
                            }
                        }

                        $lab->entriHasil($order->refresh(), $nilai, $analis);
                    }
                }

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

            $resepPasien = $resep->tulis($kunjungan, [[
                'obat_id' => $obat->random()->id,
                'jumlah' => rand(5, 15),
                'aturan_pakai' => '3x1 sesudah makan',
            ]], $dokterUser);

            // Sebagian kunjungan disertai pemeriksaan laboratorium. Urutannya wajib
            // lab dulu baru kunjungan ditutup — aturan 37 menolak bila terbalik,
            // dan penolakan itu justru bukti aturannya bekerja.
            if ($indeks % 4 === 0 && $daftarPemeriksaanLab->isNotEmpty()) {
                $order = $pemesananLab->pesan(
                    $kunjungan,
                    [$daftarPemeriksaanLab->random()],
                    $dokterUser,
                    'Menunjang diagnosa kerja.'
                );
                $orderLab++;

                $lab->ambilSampel($order, $analis);

                $nilai = [];

                foreach ($order->refresh()->detail as $detail) {
                    foreach ($detail->pemeriksaan->parameter as $parameter) {
                        $rujukan = $parameter->rujukan->first();
                        // Sebagian sengaja di luar rentang supaya penanda abnormal
                        // benar-benar muncul saat didemokan.
                        $nilai[$parameter->id] = $rujukan
                            ? round($rujukan->nilai_maks * (rand(70, 130) / 100), 2)
                            : rand(1, 100);
                    }
                }

                $lab->entriHasil($order->refresh(), $nilai, $analis);
                $lab->validasi($order->refresh(), $analis);
            }

            $klinis->selesaikan($kunjungan, $dokterUser);
            $selesai++;

            // Sebagian resep sengaja dibiarkan menunggu apotek. Tagihannya ikut
            // terkunci di kasir (aturan 29) — itulah keadaan yang justru perlu
            // terlihat saat sistem didemokan.
            if ($indeks < 40) {
                continue;
            }

            $penyiapan->siapkan($resepPasien, $apoteker);
            $disiapkan++;

            $tagihan = $kunjungan->refresh()->tagihan;

            // Pasien berpenjamin langsung menerima obat; pasien umum menunggu
            // kasir lebih dulu (aturan 30).
            // Sebagian sengaja berhenti di status disiapkan — obat sudah siap tapi
            // belum diambil pasien. Layar penyerahan perlu punya isi saat didemokan.
            if ($pakaiBpjs) {
                if ($diserahkan < 3) {
                    $penyerahan->serahkan($resepPasien->refresh(), $apoteker);
                    $diserahkan++;
                }

                continue;
            }

            if ($dibayar < 5) {
                $pembayaran->bayar($tagihan, MetodePembayaran::Tunai, (int) $tagihan->ditagihkan_ke_pasien, $kasir);
                $dibayar++;

                if ($diserahkan < 6) {
                    $penyerahan->serahkan($resepPasien->refresh(), $apoteker);
                    $diserahkan++;
                }
            }
        }

        $menungguApotek = \App\Models\Resep::where('status', \App\Enums\StatusResep::Dibuat)->count();
        $belumLunas = \App\Models\Tagihan::where('status', \App\Enums\StatusTagihan::BelumBayar)->count();

        $this->command?->info(
            "Kunjungan dummy: {$selesai} selesai, {$orderLab} order lab, {$disiapkan} resep disiapkan, "
            ."{$diserahkan} obat diserahkan, {$dibayar} dibayar, "
            ."{$menungguApotek} resep menunggu apotek, {$belumLunas} tagihan menunggu kasir."
        );
    }
}
