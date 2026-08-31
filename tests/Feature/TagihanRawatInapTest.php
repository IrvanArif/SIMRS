<?php

namespace Tests\Feature;

use App\Enums\CaraPulang;
use App\Enums\JenisDiagnosa;
use App\Enums\JenisLayanan;
use App\Enums\StatusResep;
use App\Models\BatchObat;
use App\Models\Bed;
use App\Models\Icd10;
use App\Models\KelasKamar;
use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\OkupansiBed;
use App\Models\OrderLabDetail;
use App\Models\OrderRadiologiDetail;
use App\Models\PemeriksaanLab;
use App\Models\PemeriksaanRadiologi;
use App\Models\Penjamin;
use App\Models\RawatInap;
use App\Models\Resep;
use App\Models\ResepDetail;
use App\Models\Ruang;
use App\Models\Tarif;
use App\Models\Tindakan;
use App\Models\TindakanKunjungan;
use App\Models\User;
use App\Services\PelaksanaanRadiologi;
use App\Services\PemeriksaanKlinis;
use App\Services\PemeriksaanLaboratorium;
use App\Services\PemesananLab;
use App\Services\PemesananRadiologi;
use App\Services\PemulanganPasien;
use App\Services\PenempatanBed;
use App\Services\PenulisanEkspertise;
use App\Services\PenulisanResep;
use App\Services\PenyerahanObat;
use App\Services\PenyiapanResep;
use App\Services\PenyusunTagihan;
use App\Services\PerintahRawatInap;
use App\Services\TindakanPelayanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TagihanRawatInapTest extends TestCase
{
    use RefreshDatabase;

    private Penjamin $umum;

    private KelasKamar $kelas2;

    private KelasKamar $vip;

    private Ruang $ruang;

    private Tindakan $visite;

    private PemeriksaanLab $darahRutin;

    private PemeriksaanRadiologi $toraks;

    protected function setUp(): void
    {
        parent::setUp();

        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $this->ruang = Ruang::factory()->create(['nama' => 'Melati']);
        $this->kelas2 = KelasKamar::factory()->create(['nama' => 'Kelas 2']);
        $this->vip = KelasKamar::factory()->create(['nama' => 'VIP']);
        $this->visite = Tindakan::factory()->create(['nama' => 'Visite Dokter']);
        $this->darahRutin = PemeriksaanLab::factory()->create(['nama' => 'Darah Rutin']);
        $this->toraks = PemeriksaanRadiologi::factory()->create(['nama' => 'Rontgen Toraks PA']);

        $tarif = [
            [JenisLayanan::Kamar, $this->kelas2->id, 300000],
            [JenisLayanan::Kamar, $this->vip->id, 750000],
            [JenisLayanan::Tindakan, $this->visite->id, 80000],
            [JenisLayanan::Lab, $this->darahRutin->id, 75000],
            [JenisLayanan::Radiologi, $this->toraks->id, 150000],
        ];

        foreach ($tarif as [$jenis, $layananId, $harga]) {
            Tarif::factory()->create([
                'jenis_layanan' => $jenis, 'layanan_id' => $layananId,
                'penjamin_id' => $this->umum->id, 'harga' => $harga, 'berlaku_mulai' => '2026-01-01',
            ]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function bed(string $nomor, ?KelasKamar $kelas = null): Bed
    {
        return Bed::factory()->create([
            'ruang_id' => $this->ruang->id,
            'kelas_kamar_id' => ($kelas ?? $this->kelas2)->id,
            'nomor' => $nomor,
        ]);
    }

    private function masuk(string $tanggal, Bed $bed): RawatInap
    {
        Carbon::setTestNow($tanggal.' 08:00:00');

        $kunjungan = Kunjungan::factory()->create([
            'penjamin_id' => $this->umum->id, 'tanggal' => $tanggal,
        ]);
        $dokter = User::factory()->create(['dokter_id' => $kunjungan->dokter_id]);

        app(PemeriksaanKlinis::class)->catatSoap($kunjungan, [
            'subjective' => 'Demam lima hari', 'objective' => 'Suhu 39,1',
            'assessment' => 'Suspek demam tifoid', 'plan' => 'Rawat inap',
        ], $dokter);
        app(PemeriksaanKlinis::class)
            ->tambahDiagnosa($kunjungan, Icd10::factory()->create()->id, JenisDiagnosa::Primer);

        $rawatInap = app(PerintahRawatInap::class)
            ->terbitkan($kunjungan->refresh(), $dokter, 'Demam tifoid', $this->kelas2);

        app(PenempatanBed::class)->tempatkan($rawatInap, $bed, User::factory()->create());

        return $rawatInap->refresh();
    }

    private function resepDisiapkan(RawatInap $rawatInap, User $apoteker): Resep
    {
        $obat = Obat::factory()->create(['nama' => 'Parasetamol 500 mg']);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Obat, 'layanan_id' => $obat->id,
            'penjamin_id' => $this->umum->id, 'harga' => 2500, 'berlaku_mulai' => '2026-01-01',
        ]);

        BatchObat::factory()->create([
            'obat_id' => $obat->id, 'jumlah_awal' => 100, 'jumlah_tersisa' => 100,
            'tanggal_kedaluwarsa' => '2027-01-01',
        ]);

        $resep = app(PenulisanResep::class)->tulis($rawatInap->kunjungan, [[
            'obat_id' => $obat->id, 'jumlah' => 10, 'aturan_pakai' => '3x1',
        ]], User::factory()->create());

        return app(PenyiapanResep::class)->siapkan($resep, $apoteker);
    }

    private function pulang(RawatInap $rawatInap, string $tanggal): RawatInap
    {
        Carbon::setTestNow($tanggal.' 10:00:00');

        return app(PemulanganPasien::class)->pulangkan(
            $rawatInap,
            User::factory()->create(['dokter_id' => $rawatInap->kunjungan->dokter_id]),
            Icd10::factory()->create()->id,
            CaraPulang::Sembuh,
            null
        );
    }

    public function test_tagihan_rawat_inap_memuat_kamar_tindakan_obat_lab_dan_radiologi(): void
    {
        $rawatInap = $this->masuk('2026-03-01', $this->bed('01'));
        $kunjungan = $rawatInap->kunjungan;
        $petugas = User::factory()->create();

        app(TindakanPelayanan::class)->tambah($kunjungan, $this->visite->id, 3, $petugas);

        $orderLab = app(PemesananLab::class)->pesan($kunjungan, [$this->darahRutin->id], $petugas);
        $lab = app(PemeriksaanLaboratorium::class);
        $lab->ambilSampel($orderLab, $petugas);
        $lab->entriHasil($orderLab->refresh(), [], $petugas);
        $lab->validasi($orderLab->refresh(), $petugas);

        $orderRad = app(PemesananRadiologi::class)
            ->pesan($kunjungan, [$this->toraks->id], $petugas, 'Curiga pneumonia');
        app(PelaksanaanRadiologi::class)->kerjakan($orderRad, 'FILM-1', $petugas);
        app(PenulisanEkspertise::class)->tulis($orderRad->refresh(), [
            $orderRad->detail->first()->id => [
                'temuan' => 'Tidak tampak infiltrat.', 'kesan' => 'Toraks normal.', 'saran' => null,
            ],
        ], $petugas);

        $this->pulang($rawatInap->refresh(), '2026-03-04');

        $ringkasan = $kunjungan->refresh()->tagihan->detail()
            ->selectRaw('sumber_tipe, SUM(subtotal) AS total')
            ->groupBy('sumber_tipe')
            ->pluck('total', 'sumber_tipe');

        // 3 hari kamar Kelas 2 + 3 visite + darah rutin + rontgen toraks
        $this->assertSame(3 * 300000, (int) $ringkasan[OkupansiBed::class]);
        $this->assertSame(3 * 80000, (int) $ringkasan[TindakanKunjungan::class]);
        $this->assertSame(75000, (int) $ringkasan[OrderLabDetail::class]);
        $this->assertSame(150000, (int) $ringkasan[OrderRadiologiDetail::class]);
        $this->assertSame(1_365_000, (int) $kunjungan->refresh()->tagihan->total);
    }

    public function test_baris_kamar_menyebut_kelas_ruang_dan_jumlah_harinya(): void
    {
        $rawatInap = $this->masuk('2026-03-01', $this->bed('03'));
        $this->pulang($rawatInap->refresh(), '2026-03-04');

        $this->assertDatabaseHas('tagihan_detail', [
            'sumber_tipe' => OkupansiBed::class,
            'deskripsi' => 'Kamar Kelas 2 — Melati 03 (3 hari)',
            'jumlah' => 3,
            'tarif_satuan' => 300000,
            'subtotal' => 900000,
        ]);
    }

    public function test_pindah_kelas_menghasilkan_dua_baris_kamar_pada_tagihan(): void
    {
        $rawatInap = $this->masuk('2026-03-01', $this->bed('01'));

        Carbon::setTestNow('2026-03-04 09:00:00');
        app(PenempatanBed::class)->pindahkan(
            $rawatInap->refresh(), $this->bed('02', $this->vip), User::factory()->create(), 'Naik kelas'
        );

        $this->pulang($rawatInap->refresh(), '2026-03-06');

        $baris = $rawatInap->refresh()->kunjungan->tagihan->detail()
            ->where('sumber_tipe', OkupansiBed::class)->orderBy('id')->get();

        $this->assertCount(2, $baris);
        $this->assertSame(900000, (int) $baris[0]->subtotal);
        $this->assertSame(1_500_000, (int) $baris[1]->subtotal);
        $this->assertSame(2_400_000, (int) $rawatInap->kunjungan->tagihan->total);
    }

    public function test_kunjungan_rawat_jalan_tidak_mendapat_baris_kamar(): void
    {
        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]);
        $dokter = User::factory()->create(['dokter_id' => $kunjungan->dokter_id]);

        app(TindakanPelayanan::class)->tambah($kunjungan, $this->visite->id, 1, $dokter);
        app(PemeriksaanKlinis::class)->catatSoap($kunjungan, [
            'subjective' => 'Batuk', 'objective' => 'Faring hiperemis',
            'assessment' => 'Faringitis', 'plan' => 'Obat',
        ], $dokter);
        app(PemeriksaanKlinis::class)
            ->tambahDiagnosa($kunjungan, Icd10::factory()->create()->id, JenisDiagnosa::Primer);
        app(PemeriksaanKlinis::class)->selesaikan($kunjungan->refresh(), $dokter);

        $tagihan = $kunjungan->refresh()->tagihan;

        $this->assertSame(80000, (int) $tagihan->total);
        $this->assertSame(0, $tagihan->detail()->where('sumber_tipe', OkupansiBed::class)->count());
    }

    public function test_obat_bisa_diserahkan_ke_pasien_rawat_inap_sebelum_tagihan_ada(): void
    {
        $rawatInap = $this->masuk('2026-03-01', $this->bed('01'));
        $apoteker = User::factory()->create();

        $resep = $this->resepDisiapkan($rawatInap, $apoteker);

        // Aturan 30 menahan obat sampai tagihan lunas, tetapi itu berlaku untuk
        // pasien yang akan berjalan keluar pintu. Pasien rawat inap tidak bisa
        // membayar lebih dulu: tagihannya baru ada saat ia pulang.
        app(PenyerahanObat::class)->serahkan($resep->refresh(), $apoteker);

        $this->assertSame(StatusResep::Diserahkan, $resep->refresh()->status);
    }

    public function test_biaya_obat_selama_dirawat_masuk_ke_tagihan_saat_pasien_pulang(): void
    {
        $rawatInap = $this->masuk('2026-03-01', $this->bed('01'));
        $this->resepDisiapkan($rawatInap, User::factory()->create());

        $this->pulang($rawatInap->refresh(), '2026-03-04');

        $tagihan = $rawatInap->refresh()->kunjungan->tagihan;

        // 3 hari kamar + 10 butir obat seharga 2.500
        $this->assertSame(25000, (int) $tagihan->detail()->where('sumber_tipe', ResepDetail::class)->sum('subtotal'));
        $this->assertSame(900000 + 25000, (int) $tagihan->total);
    }

    public function test_rincian_sementara_menampilkan_total_berjalan(): void
    {
        $rawatInap = $this->masuk('2026-03-01', $this->bed('01'));
        app(TindakanPelayanan::class)
            ->tambah($rawatInap->kunjungan, $this->visite->id, 2, User::factory()->create());

        Carbon::setTestNow('2026-03-04 09:00:00');

        $rincian = app(PenyusunTagihan::class)->rincianSementara($rawatInap->kunjungan->refresh());

        // 3 hari kamar + 2 visite
        $this->assertSame(900000 + 160000, $rincian['total']);
        $this->assertNotEmpty($rincian['baris']);
    }

    public function test_rincian_sementara_tidak_membuat_tagihan(): void
    {
        $rawatInap = $this->masuk('2026-03-01', $this->bed('01'));

        Carbon::setTestNow('2026-03-04 09:00:00');
        app(PenyusunTagihan::class)->rincianSementara($rawatInap->kunjungan->refresh());

        // Keluarga pasien boleh bertanya berkali-kali; bertanya bukan menutup berkas.
        $this->assertNull($rawatInap->kunjungan->refresh()->tagihan);
        $this->assertSame(0, \App\Models\Tagihan::count());
    }
}
