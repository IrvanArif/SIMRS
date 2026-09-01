<?php

namespace Tests\Feature;

use App\Enums\CaraPulang;
use App\Enums\JenisDiagnosa;
use App\Enums\JenisLayanan;
use App\Models\Bed;
use App\Models\Icd10;
use App\Models\KelasKamar;
use App\Models\Kunjungan;
use App\Models\Penjamin;
use App\Models\RawatInap;
use App\Models\Ruang;
use App\Models\Tarif;
use App\Models\User;
use App\Services\IndikatorRawatInap;
use App\Services\PemeriksaanKlinis;
use App\Services\PemulanganPasien;
use App\Services\PenempatanBed;
use App\Services\PerintahRawatInap;
use App\Services\RentangTanggal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class IndikatorRawatInapTest extends TestCase
{
    use RefreshDatabase;

    private Penjamin $umum;

    private KelasKamar $kelas;

    private Ruang $ruang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $this->ruang = Ruang::factory()->create(['nama' => 'Melati']);
        $this->kelas = KelasKamar::factory()->create(['nama' => 'Kelas 2']);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Kamar, 'layanan_id' => $this->kelas->id,
            'penjamin_id' => $this->umum->id, 'harga' => 300000, 'berlaku_mulai' => '2026-01-01',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function bed(string $nomor, bool $aktif = true): Bed
    {
        return Bed::factory()->create([
            'ruang_id' => $this->ruang->id, 'kelas_kamar_id' => $this->kelas->id,
            'nomor' => $nomor, 'aktif' => $aktif,
        ]);
    }

    private function rawat(string $masuk, ?string $pulang, Bed $bed): RawatInap
    {
        Carbon::setTestNow($masuk.' 08:00:00');

        $kunjungan = Kunjungan::factory()->create([
            'penjamin_id' => $this->umum->id, 'tanggal' => $masuk,
        ]);
        $dokter = User::factory()->create(['dokter_id' => $kunjungan->dokter_id]);
        $klinis = app(PemeriksaanKlinis::class);

        $klinis->catatSoap($kunjungan, [
            'subjective' => 'a', 'objective' => 'b', 'assessment' => 'c', 'plan' => 'd',
        ], $dokter);
        $klinis->tambahDiagnosa($kunjungan, Icd10::factory()->create()->id, JenisDiagnosa::Primer);

        $rawatInap = app(PerintahRawatInap::class)
            ->terbitkan($kunjungan->refresh(), $dokter, 'Observasi', $this->kelas);

        app(PenempatanBed::class)->tempatkan($rawatInap, $bed, User::factory()->create());

        if ($pulang !== null) {
            Carbon::setTestNow($pulang.' 10:00:00');

            app(PemulanganPasien::class)->pulangkan(
                $rawatInap->refresh(), $dokter, Icd10::factory()->create()->id, CaraPulang::Sembuh, null
            );
        }

        return $rawatInap->refresh();
    }

    private function hitung(string $awal, string $akhir): array
    {
        return app(IndikatorRawatInap::class)->hitung(RentangTanggal::dari($awal, $akhir));
    }

    public function test_rentang_tanggal_terbalik_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RentangTanggal::dari('2026-06-30', '2026-06-01');
    }

    public function test_rentang_satu_hari_sah_dan_berisi_satu_hari(): void
    {
        $this->assertSame(1, RentangTanggal::dari('2026-06-01', '2026-06-01')->hari());
    }

    public function test_rentang_menghitung_hari_secara_inklusif(): void
    {
        $this->assertSame(30, RentangTanggal::dari('2026-06-01', '2026-06-30')->hari());
    }

    public function test_hari_rawat_dihitung_dari_penggal_di_dalam_periode(): void
    {
        $this->bed('01');
        $this->rawat('2026-06-05', '2026-06-10', Bed::first());

        Carbon::setTestNow('2026-07-01 09:00:00');
        $hasil = $this->hitung('2026-06-01', '2026-06-30');

        $this->assertSame(5, $hasil['hari_rawat']);
        $this->assertSame(1, $hasil['pasien_keluar']);
    }

    public function test_penggal_yang_melampaui_periode_dipotong_pada_batas_periode(): void
    {
        $this->bed('01');
        // Masuk sebelum periode, pulang sesudahnya: hanya hari di dalam periode
        // yang dihitung. Tanpa pemotongan, BOR bisa melampaui 100%.
        $this->rawat('2026-05-20', '2026-07-10', Bed::first());

        Carbon::setTestNow('2026-08-01 09:00:00');
        $hasil = $this->hitung('2026-06-01', '2026-06-30');

        $this->assertSame(30, $hasil['hari_rawat']);
        // Pasiennya keluar di bulan Juli, jadi tidak dihitung sebagai pasien
        // keluar bulan Juni.
        $this->assertSame(0, $hasil['pasien_keluar']);
    }

    public function test_pasien_yang_masih_dirawat_ikut_menyumbang_hari_rawat(): void
    {
        $this->bed('01');
        $this->rawat('2026-06-25', null, Bed::first());

        Carbon::setTestNow('2026-06-28 09:00:00');
        $hasil = $this->hitung('2026-06-01', '2026-06-30');

        $this->assertSame(3, $hasil['hari_rawat']);
        $this->assertSame(0, $hasil['pasien_keluar']);
    }

    public function test_bor_dihitung_hanya_dari_bed_aktif(): void
    {
        $this->bed('01');
        $this->bed('02');
        $this->bed('03', aktif: false);

        $this->rawat('2026-06-01', '2026-06-11', Bed::where('nomor', '01')->first());

        Carbon::setTestNow('2026-07-01 09:00:00');
        $hasil = $this->hitung('2026-06-01', '2026-06-30');

        // 2 bed aktif × 30 hari = 60 hari tersedia; 10 hari terpakai.
        $this->assertSame(2, $hasil['bed_tersedia']);
        $this->assertSame(10, $hasil['hari_rawat']);
        $this->assertEqualsWithDelta(16.67, $hasil['bor'], 0.01);
    }

    public function test_bed_nonaktif_tidak_menambah_kapasitas(): void
    {
        $this->bed('01');
        $this->bed('02', aktif: false);

        Carbon::setTestNow('2026-07-01 09:00:00');

        $this->assertSame(1, $this->hitung('2026-06-01', '2026-06-30')['bed_tersedia']);
    }

    public function test_indikator_bernilai_nol_saat_tidak_ada_bed_aktif(): void
    {
        $this->bed('01', aktif: false);

        Carbon::setTestNow('2026-07-01 09:00:00');
        $hasil = $this->hitung('2026-06-01', '2026-06-30');

        // Bukan pembagian dengan nol.
        $this->assertSame(0.0, $hasil['bor']);
        $this->assertSame(0.0, $hasil['bto']);
        $this->assertSame(0, $hasil['bed_tersedia']);
    }

    public function test_indikator_bernilai_nol_saat_tidak_ada_pasien_keluar(): void
    {
        $this->bed('01');

        Carbon::setTestNow('2026-07-01 09:00:00');
        $hasil = $this->hitung('2026-06-01', '2026-06-30');

        $this->assertSame(0.0, $hasil['los']);
        $this->assertSame(0.0, $hasil['toi']);
    }

    public function test_los_dihitung_dari_pasien_yang_sudah_keluar(): void
    {
        $this->bed('01');
        $this->bed('02');

        $this->rawat('2026-06-01', '2026-06-05', Bed::where('nomor', '01')->first());
        $this->rawat('2026-06-10', '2026-06-16', Bed::where('nomor', '02')->first());

        Carbon::setTestNow('2026-07-01 09:00:00');
        $hasil = $this->hitung('2026-06-01', '2026-06-30');

        // 4 hari + 6 hari = 10 hari rawat, 2 pasien keluar.
        $this->assertSame(10, $hasil['hari_rawat']);
        $this->assertSame(2, $hasil['pasien_keluar']);
        $this->assertEqualsWithDelta(5.0, $hasil['los'], 0.001);
    }

    public function test_bto_menghitung_perputaran_bed(): void
    {
        $this->bed('01');
        $this->bed('02');

        $this->rawat('2026-06-01', '2026-06-05', Bed::where('nomor', '01')->first());
        $this->rawat('2026-06-10', '2026-06-16', Bed::where('nomor', '02')->first());

        Carbon::setTestNow('2026-07-01 09:00:00');

        // 2 pasien keluar ÷ 2 bed = 1 kali perputaran.
        $this->assertEqualsWithDelta(1.0, $this->hitung('2026-06-01', '2026-06-30')['bto'], 0.001);
    }

    public function test_toi_menghitung_rata_rata_bed_menganggur(): void
    {
        $this->bed('01');

        $this->rawat('2026-06-01', '2026-06-11', Bed::first());

        Carbon::setTestNow('2026-07-01 09:00:00');
        $hasil = $this->hitung('2026-06-01', '2026-06-30');

        // (1 bed × 30 hari − 10 hari rawat) ÷ 1 pasien keluar = 20 hari.
        $this->assertEqualsWithDelta(20.0, $hasil['toi'], 0.001);
    }

    public function test_toi_tidak_pernah_bernilai_negatif(): void
    {
        $this->bed('01');
        $this->rawat('2026-06-01', '2026-06-30', Bed::first());

        // Bednya dinonaktifkan setelah dipakai: kapasitasnya menyusut sementara
        // hari rawatnya sudah tercatat. TOI negatif tidak bermakna.
        Bed::query()->update(['aktif' => false]);
        $this->bed('02');

        Carbon::setTestNow('2026-07-01 09:00:00');

        $this->assertGreaterThanOrEqual(0, $this->hitung('2026-06-01', '2026-06-30')['toi']);
    }

    public function test_periode_tanpa_kegiatan_menghasilkan_seluruh_indikator_nol(): void
    {
        $this->bed('01');

        Carbon::setTestNow('2026-07-01 09:00:00');
        $hasil = $this->hitung('2026-01-01', '2026-01-31');

        $this->assertSame(0, $hasil['hari_rawat']);
        $this->assertSame(0.0, $hasil['bor']);
        $this->assertSame(0.0, $hasil['los']);
        $this->assertSame(0.0, $hasil['toi']);
        $this->assertSame(0.0, $hasil['bto']);
    }
}
