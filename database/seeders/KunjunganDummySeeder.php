<?php

namespace Database\Seeders;

use App\Enums\CaraPulang;
use App\Enums\JenisDiagnosa;
use App\Enums\MetodePembayaran;
use App\Enums\StatusBerkasKlaim;
use App\Enums\StatusKunjungan;
use App\Enums\Peran;
use App\Models\Dokter;
use App\Models\Icd10;
use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\PemeriksaanLab;
use App\Models\KelasKamar;
use App\Models\Bed;
use App\Models\PemeriksaanRadiologi;
use App\Models\Pasien;
use App\Models\Penjamin;
use App\Models\Tindakan;
use App\Models\User;
use App\Services\PemeriksaanKlinis;
use App\Services\PemeriksaanLaboratorium;
use App\Services\CatatanHarian;
use App\Services\PelaksanaanRadiologi;
use App\Services\PemesananLab;
use App\Services\PemesananRadiologi;
use App\Services\PendaftaranKunjungan;
use App\Services\PemulanganPasien;
use App\Services\PenempatanBed;
use App\Services\PenerbitanSep;
use App\Services\PenyusunBerkasKlaim;
use App\Services\PerintahRawatInap;
use App\Services\PenulisanEkspertise;
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
        // Harus dokter yang memegang poli. Sejak dokter radiologi ikut diseed,
        // pengguna berperan dokter yang pertama bisa jadi dia — dan dia tidak
        // punya poli, sehingga seluruh SOAP dummy akan salah atas nama.
        $dokterUser = User::role(Peran::Dokter->value)->whereNotNull('dokter_id')->first();
        $kasir = User::role(Peran::Kasir->value)->first();
        $apoteker = User::role(Peran::Apoteker->value)->first();
        $analis = User::role(Peran::Analis->value)->first();
        $radiografer = User::role(Peran::Radiografer->value)->first();
        $dokterRadiologi = User::where('email', 'dokterradiologi@rs.test')->first();

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
        $pemesananRadiologi = app(PemesananRadiologi::class);
        $pelaksanaan = app(PelaksanaanRadiologi::class);
        $ekspertise = app(PenulisanEkspertise::class);
        $daftarPemeriksaanRadiologi = PemeriksaanRadiologi::where('aktif', true)->pluck('id');

        $selesai = 0;
        $orderLab = 0;
        $antreLab = 0;
        $orderRadiologi = 0;
        $antreRadiologi = 0;
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

                // Order radiologi yang sengaja berhenti di dua tahap: 4 menunggu
                // dikerjakan radiografer, 3 menunggu dibaca dokter radiologi.
                if ($indeks >= 11 && $indeks < 18 && $daftarPemeriksaanRadiologi->isNotEmpty()) {
                    $orderRad = $pemesananRadiologi->pesan(
                        $kunjungan,
                        [$daftarPemeriksaanRadiologi->random()],
                        $dokterUser,
                        'Menunggu pemeriksaan pencitraan.'
                    );
                    $antreRadiologi++;

                    if ($indeks >= 15) {
                        $pelaksanaan->kerjakan(
                            $orderRad, 'FILM-'.now()->format('Y').'-'.str_pad((string) $indeks, 4, '0', STR_PAD_LEFT),
                            $radiografer
                        );
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

            // Sebagian kunjungan disertai pencitraan. Sama seperti lab, urutannya
            // wajib tuntas sebelum kunjungan ditutup — aturan 50 menolak bila belum.
            if ($indeks % 5 === 0 && $daftarPemeriksaanRadiologi->isNotEmpty()) {
                $orderRad = $pemesananRadiologi->pesan(
                    $kunjungan,
                    [$daftarPemeriksaanRadiologi->random()],
                    $dokterUser,
                    'Menunjang diagnosa kerja.'
                );
                $orderRadiologi++;

                $pelaksanaan->kerjakan(
                    $orderRad, 'FILM-'.now()->format('Y').'-'.str_pad((string) $indeks, 4, '0', STR_PAD_LEFT),
                    $radiografer
                );

                $bacaan = [];

                foreach ($orderRad->refresh()->detail as $detail) {
                    $bacaan[$detail->id] = [
                        'temuan' => 'Tidak tampak kelainan bermakna pada '.strtolower($detail->pemeriksaan->nama).'.',
                        'kesan' => 'Dalam batas normal.',
                        'saran' => null,
                    ];
                }

                $ekspertise->tulis($orderRad->refresh(), $bacaan, $dokterRadiologi);
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

        [$dirawat, $sudahPulang] = $this->isiRawatInap(
            $dokter, $penjamin, $konsultasi, $icd, $admisi, $perawat, $dokterUser
        );

        // Dijalankan paling akhir supaya kunjungan rawat inap pun ikut ber-SEP.
        // Kalau dipanggil lebih dulu, masa rawatnya belum ada sehingga seluruh
        // SEP tercatat sebagai rawat jalan dan tidak satu pun klaim rawat inap
        // tersusun — persis keadaan yang membuat kasus paling menarik tak terlihat.
        [$jumlahSep, $jumlahKlaim] = $this->isiKlaim();

        $menungguApotek = \App\Models\Resep::where('status', \App\Enums\StatusResep::Dibuat)->count();
        $belumLunas = \App\Models\Tagihan::where('status', \App\Enums\StatusTagihan::BelumBayar)->count();

        $this->command?->info(
            "Kunjungan dummy: {$selesai} selesai, {$orderLab} order lab, {$orderRadiologi} order radiologi, "
            ."{$disiapkan} resep disiapkan, {$diserahkan} obat diserahkan, {$dibayar} dibayar, "
            ."{$menungguApotek} resep menunggu apotek, {$belumLunas} tagihan menunggu kasir, "
            ."{$antreLab} order lab dan {$antreRadiologi} order radiologi masih mengantre, "
            ."{$dirawat} pasien sedang dirawat inap dan {$sudahPulang} sudah pulang, "
            ."{$jumlahSep} SEP terbit dan {$jumlahKlaim} berkas klaim tersusun."
        );
    }

    /**
     * Mengisi bangsal: sebagian pasien masih dirawat supaya papan bed ada isinya,
     * sebagian sudah pulang lengkap dengan tagihan berisi baris kamar. Satu di
     * antaranya sengaja pindah kelas, supaya perhitungan berpenggal terlihat
     * nyata saat sistem didemokan.
     *
     * @return array{0: int, 1: int} jumlah yang sedang dirawat dan yang sudah pulang
     */
    private function isiRawatInap(
        $dokter, $penjamin, $konsultasi, $icd, $admisi, $perawat, $dokterUser
    ): array {
        $kelas = KelasKamar::orderBy('urutan')->get();

        if ($kelas->isEmpty() || Bed::count() === 0) {
            return [0, 0];
        }

        $pendaftaran = app(PendaftaranKunjungan::class);
        $klinis = app(PemeriksaanKlinis::class);
        $pelayanan = app(TindakanPelayanan::class);
        $perintah = app(PerintahRawatInap::class);
        $penempatan = app(PenempatanBed::class);
        $catatan = app(CatatanHarian::class);
        $pemulangan = app(PemulanganPasien::class);

        // Pasien yang masih punya kunjungan berjalan tidak boleh didaftarkan lagi
        // hari ini (aturan 5). Kandidatnya disaring lebih dulu ketimbang diambil
        // acak lalu ditolak di tengah jalan.
        $kandidat = Pasien::whereDoesntHave('kunjungan', fn ($q) => $q->whereNotIn('status', [
            StatusKunjungan::Selesai->value, StatusKunjungan::Batal->value,
        ]))->inRandomOrder()->limit(9)->get();

        $dirawat = 0;
        $pulang = 0;

        // 9 masa rawat: 6 masih berjalan, 3 sudah pulang (satu di antaranya pindah kelas).
        foreach (range(0, 8) as $urutan) {
            $bedKosong = Bed::kosong()->inRandomOrder()->first();
            $orang = $kandidat->get($urutan);

            if ($bedKosong === null || $orang === null) {
                break;
            }

            // Sebagian sengaja ditaruh di poli dokter demo, supaya akun
            // dokter@rs.test benar-benar punya pasien rawat inap sendiri saat
            // sistem dibuka — bukan hanya milik dokter lain yang tak bisa ia buka.
            $dokterTerpilih = $urutan % 3 === 0 && $dokterUser->dokter !== null
                ? $dokterUser->dokter
                : $dokter->random();
            $pakaiBpjs = $urutan % 2 === 0;

            $kunjungan = $pendaftaran->daftarkan([
                'pasien_id' => $orang->id,
                'poli_id' => $dokterTerpilih->poli_id,
                'dokter_id' => $dokterTerpilih->id,
                'penjamin_id' => $pakaiBpjs ? $penjamin['BPJS'] : $penjamin['UMUM'],
                'no_kartu_penjamin' => $pakaiBpjs ? fake()->numerify('#############') : null,
                'tanggal' => now()->toDateString(),
            ], $admisi);

            $klinis->catatVital($kunjungan, [
                'sistolik' => rand(95, 140), 'diastolik' => rand(55, 90), 'nadi' => rand(70, 110),
                'suhu' => rand(370, 395) / 10, 'respirasi' => rand(16, 26),
                'berat_badan' => rand(400, 900) / 10, 'tinggi_badan' => rand(145, 180),
                'keluhan_awal' => 'Keluhan berat sehingga dirujuk untuk rawat inap.',
            ], $perawat);

            $klinis->catatSoap($kunjungan, [
                'subjective' => 'Keluhan menetap dan memberat sejak beberapa hari.',
                'objective' => 'Tanda vital menunjukkan perlunya pemantauan berkelanjutan.',
                'assessment' => 'Diagnosa kerja sesuai kode ICD-10 terlampir.',
                'plan' => 'Rawat inap untuk observasi dan terapi.',
            ], $dokterUser);

            $klinis->tambahDiagnosa($kunjungan, $icd->random()->id, JenisDiagnosa::Primer);
            $pelayanan->tambah($kunjungan, $konsultasi->random()->id, 1, $dokterUser);

            $rawatInap = $perintah->terbitkan(
                $kunjungan->refresh(), $dokterUser, 'Perlu pemantauan berkelanjutan.', $kelas->random()
            );

            // Dua pasien pertama sengaja dibiarkan menunggu penempatan, supaya
            // daftar "Menunggu Penempatan" di papan bed tidak kosong.
            if ($urutan < 2) {
                $dirawat++;

                continue;
            }

            $penempatan->tempatkan($rawatInap, $bedKosong, $admisi);

            // Tanggal masuk dimundurkan supaya lama rawatnya lebih dari sehari.
            // Cara ini dipilih ketimbang menggeser jam sistem, yang berisiko
            // merembet ke seluruh proses seed.
            $lama = rand(2, 6);
            $mulai = now()->subDays($lama)->toDateString();
            $rawatInap->okupansi()->berjalan()->update(['mulai' => $mulai]);
            $rawatInap->update(['waktu_masuk' => now()->subDays($lama)]);

            foreach (range(1, min($lama, 3)) as $hari) {
                $catatan->tulis($rawatInap->refresh(), [
                    'subjective' => "Hari perawatan ke-{$hari}: keluhan berangsur berkurang.",
                    'objective' => 'Tanda vital membaik, asupan cairan tercukupi.',
                    'assessment' => 'Perbaikan klinis.',
                    'plan' => 'Lanjutkan terapi, evaluasi besok.',
                ], $hari % 2 === 0 ? $dokterUser : $perawat);
            }

            // Satu pasien pindah kelas di tengah masa rawat.
            if ($urutan === 6) {
                $tujuan = Bed::kosong()
                    ->where('kelas_kamar_id', '!=', $bedKosong->kelas_kamar_id)
                    ->inRandomOrder()->first();

                if ($tujuan !== null) {
                    $penempatan->pindahkan(
                        $rawatInap->refresh(), $tujuan, $admisi, 'Permintaan keluarga, naik kelas.'
                    );
                }
            }

            if ($urutan >= 6) {
                $pemulangan->pulangkan(
                    $rawatInap->refresh(), $dokterUser, $icd->random()->id, CaraPulang::Sembuh,
                    'Kondisi membaik, dilanjutkan kontrol rawat jalan.'
                );
                $pulang++;

                continue;
            }

            $dirawat++;
        }

        return [$dirawat, $pulang];
    }

    /**
     * Menerbitkan SEP untuk seluruh kunjungan berpenjamin, lalu menyusun berkas
     * klaim untuk yang sudah selesai. Keempat status berkas dibuat ada isinya
     * supaya seluruh keadaan terlihat saat sistem didemokan.
     *
     * Di sistem yang berjalan, SEP terbit di awal kunjungan. Di sini ia disusulkan
     * di akhir seed semata supaya masa rawat inapnya sudah ada saat jenis
     * pelayanannya ditentukan.
     *
     * @return array{0: int, 1: int} jumlah SEP dan jumlah berkas klaim
     */
    private function isiKlaim(): array
    {
        $admisi = User::role(Peran::Admisi->value)->first();
        $rekamMedis = User::role(Peran::RekamMedis->value)->first();

        if ($admisi === null || $rekamMedis === null) {
            return [0, 0];
        }

        $penerbitan = app(PenerbitanSep::class);
        $penyusun = app(PenyusunBerkasKlaim::class);

        $berpenjamin = Kunjungan::whereHas('penjamin', fn ($q) => $q->where('jenis', 'penjamin'))
            ->whereNotNull('no_kartu_penjamin')
            ->whereDoesntHave('sep', fn ($q) => $q->berlaku())
            ->with('diagnosa.icd10')
            ->orderBy('id')
            ->get();

        $jumlahSep = 0;

        foreach ($berpenjamin as $kunjungan) {
            $diagnosaAwal = $kunjungan->diagnosa->first()?->icd10->nama_id
                ?? 'Keluhan sesuai anamnesis awal';

            $penerbitan->terbitkan($kunjungan, $admisi, $diagnosaAwal);
            $jumlahSep++;
        }

        $siapKlaim = Kunjungan::whereHas('penjamin', fn ($q) => $q->where('jenis', 'penjamin'))
            ->where('status', StatusKunjungan::Selesai->value)
            ->whereHas('sep', fn ($q) => $q->berlaku())
            ->whereHas('tagihan')
            ->whereHas('diagnosa', fn ($q) => $q->where('jenis', JenisDiagnosa::Primer->value))
            ->whereDoesntHave('berkasKlaim', fn ($q) => $q->berlaku())
            ->orderBy('id')
            ->get();

        $jumlahKlaim = 0;

        foreach ($siapKlaim as $urutan => $kunjungan) {
            $berkas = $penyusun->susun($kunjungan, $rekamMedis);
            $jumlahKlaim++;

            // Yang pertama dibiarkan draf; sisanya diajukan, lalu satu disetujui
            // dan satu ditolak.
            if ($urutan === 0) {
                continue;
            }

            $penyusun->ajukan($berkas, $rekamMedis);

            if ($urutan === 1) {
                $penyusun->tandaiHasil($berkas->refresh(), StatusBerkasKlaim::Disetujui, $rekamMedis, null);
            }

            if ($urutan === 2) {
                $penyusun->tandaiHasil(
                    $berkas->refresh(), StatusBerkasKlaim::Ditolak, $rekamMedis,
                    'Kode prosedur tidak sesuai diagnosa; mohon dikoreksi.'
                );
            }
        }

        return [$jumlahSep, $jumlahKlaim];
    }
}
