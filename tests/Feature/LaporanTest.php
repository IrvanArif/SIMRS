<?php

namespace Tests\Feature;

use App\Enums\JenisDiagnosa;
use App\Enums\JenisLayanan;
use App\Enums\MetodePembayaran;
use App\Enums\StatusKunjungan;
use App\Models\Icd10;
use App\Models\KelasKamar;
use App\Models\Kunjungan;
use App\Models\Penjamin;
use App\Models\Poli;
use App\Models\Tarif;
use App\Models\Tindakan;
use App\Models\User;
use App\Services\LaporanKunjungan;
use App\Services\LaporanMorbiditas;
use App\Services\LaporanPendapatan;
use App\Services\PemeriksaanKlinis;
use App\Services\PerintahRawatInap;
use App\Services\ProsesPembayaran;
use App\Services\RentangTanggal;
use App\Services\TindakanPelayanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class LaporanTest extends TestCase
{
    use RefreshDatabase;

    private Penjamin $umum;

    private Penjamin $bpjs;

    private Tindakan $konsultasi;

    private Poli $poliUmum;

    private Poli $poliAnak;

    protected function setUp(): void
    {
        parent::setUp();

        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'nama' => 'Umum (Tunai)', 'jenis' => 'tunai']);
        $this->bpjs = Penjamin::factory()->create(['kode' => 'BPJS', 'nama' => 'BPJS Kesehatan', 'jenis' => 'penjamin']);
        $this->poliUmum = Poli::factory()->create(['kode' => 'UMU', 'nama' => 'Poli Umum']);
        $this->poliAnak = Poli::factory()->create(['kode' => 'ANK', 'nama' => 'Poli Anak']);
        $this->konsultasi = Tindakan::factory()->create(['nama' => 'Konsultasi Dokter Umum']);

        foreach ([$this->umum, $this->bpjs] as $penjamin) {
            Tarif::factory()->create([
                'jenis_layanan' => JenisLayanan::Tindakan, 'layanan_id' => $this->konsultasi->id,
                'penjamin_id' => $penjamin->id, 'harga' => 50000, 'berlaku_mulai' => '2026-01-01',
            ]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function icd(string $kode): Icd10
    {
        return Icd10::firstOrCreate(['kode' => $kode], ['nama_id' => 'Diagnosa '.$kode]);
    }

    /** Kunjungan selesai lengkap dengan diagnosa primer, tindakan, dan tagihan. */
    private function kunjungan(
        string $tanggal,
        string $kodeIcd,
        ?Penjamin $penjamin = null,
        ?Poli $poli = null,
        bool $rawatInap = false,
        array $sekunder = []
    ): Kunjungan {
        Carbon::setTestNow($tanggal.' 09:00:00');

        $kunjungan = Kunjungan::factory()->create([
            'penjamin_id' => ($penjamin ?? $this->umum)->id,
            'poli_id' => ($poli ?? $this->poliUmum)->id,
            'tanggal' => $tanggal,
        ]);
        $dokter = User::factory()->create(['dokter_id' => $kunjungan->dokter_id]);
        $klinis = app(PemeriksaanKlinis::class);

        app(TindakanPelayanan::class)->tambah($kunjungan, $this->konsultasi->id, 1, $dokter);
        $klinis->catatSoap($kunjungan, [
            'subjective' => 'a', 'objective' => 'b', 'assessment' => 'c', 'plan' => 'd',
        ], $dokter);
        $klinis->tambahDiagnosa($kunjungan, $this->icd($kodeIcd)->id, JenisDiagnosa::Primer);

        foreach ($sekunder as $kodeSekunder) {
            $klinis->tambahDiagnosa($kunjungan, $this->icd($kodeSekunder)->id, JenisDiagnosa::Sekunder);
        }

        if ($rawatInap) {
            // Masa rawatnya dibiarkan berjalan; yang diuji laporan morbiditas
            // adalah pemisahan rawat jalan dan rawat inap, bukan alur pulangnya.
            app(PerintahRawatInap::class)->terbitkan(
                $kunjungan->refresh(), $dokter, 'Observasi', KelasKamar::factory()->create()
            );
            $kunjungan->refresh()->update(['status' => StatusKunjungan::Selesai]);

            return $kunjungan->refresh();
        }

        $klinis->selesaikan($kunjungan->refresh(), $dokter);

        return $kunjungan->refresh();
    }

    private function juni(): RentangTanggal
    {
        return RentangTanggal::dari('2026-06-01', '2026-06-30');
    }

    public function test_morbiditas_mengurutkan_dari_yang_terbanyak(): void
    {
        $this->kunjungan('2026-06-01', 'J06.9');
        $this->kunjungan('2026-06-02', 'J06.9');
        $this->kunjungan('2026-06-03', 'J06.9');
        $this->kunjungan('2026-06-04', 'A09');
        $this->kunjungan('2026-06-05', 'I10');

        $hasil = app(LaporanMorbiditas::class)->sepuluhBesar($this->juni());

        $this->assertSame('J06.9', $hasil->first()['kode']);
        $this->assertSame(3, $hasil->first()['jumlah']);
        $this->assertSame(3, $hasil->count());
    }

    public function test_morbiditas_hanya_menghitung_diagnosa_primer(): void
    {
        // Diagnosa sekunder adalah penyerta. Memasukkannya membuat satu pasien
        // terhitung beberapa kali sehingga urutannya tidak lagi menjawab
        // "penyakit apa yang paling sering datang".
        $this->kunjungan('2026-06-01', 'J06.9', sekunder: ['E86']);

        $hasil = app(LaporanMorbiditas::class)->sepuluhBesar($this->juni());

        $this->assertSame(['J06.9'], $hasil->pluck('kode')->all());
    }

    public function test_morbiditas_memisahkan_rawat_jalan_dan_rawat_inap(): void
    {
        $this->kunjungan('2026-06-01', 'J06.9');
        $this->kunjungan('2026-06-02', 'A01.0', rawatInap: true);

        $rentang = $this->juni();
        $laporan = app(LaporanMorbiditas::class);

        $this->assertSame(['J06.9'], $laporan->sepuluhBesar($rentang, rawatInap: false)->pluck('kode')->all());
        $this->assertSame(['A01.0'], $laporan->sepuluhBesar($rentang, rawatInap: true)->pluck('kode')->all());
        $this->assertSame(2, $laporan->sepuluhBesar($rentang)->count());
    }

    public function test_morbiditas_membatasi_sepuluh_teratas(): void
    {
        foreach (range(1, 12) as $ke) {
            $this->kunjungan('2026-06-'.str_pad((string) $ke, 2, '0', STR_PAD_LEFT), 'Z'.$ke.'.0');
        }

        $this->assertSame(10, app(LaporanMorbiditas::class)->sepuluhBesar($this->juni())->count());
    }

    public function test_morbiditas_di_luar_periode_tidak_ikut_terhitung(): void
    {
        $this->kunjungan('2026-05-31', 'J06.9');
        $this->kunjungan('2026-07-01', 'J06.9');
        $this->kunjungan('2026-06-15', 'A09');

        $hasil = app(LaporanMorbiditas::class)->sepuluhBesar($this->juni());

        $this->assertSame(['A09'], $hasil->pluck('kode')->all());
    }

    public function test_pendapatan_memisahkan_lunas_menunggu_dan_ditanggung_penjamin(): void
    {
        $lunas = $this->kunjungan('2026-06-01', 'J06.9', $this->umum);
        $this->kunjungan('2026-06-02', 'A09', $this->umum);
        $this->kunjungan('2026-06-03', 'I10', $this->bpjs);

        $tagihan = $lunas->refresh()->tagihan;
        app(ProsesPembayaran::class)->bayar(
            $tagihan, MetodePembayaran::Tunai, (int) $tagihan->ditagihkan_ke_pasien,
            User::factory()->create()
        );

        $hasil = app(LaporanPendapatan::class)->perPenjamin($this->juni())->keyBy('penjamin');

        $this->assertSame(50000, $hasil['Umum (Tunai)']['lunas']);
        $this->assertSame(50000, $hasil['Umum (Tunai)']['menunggu']);
        $this->assertSame(50000, $hasil['BPJS Kesehatan']['ditanggung_penjamin']);
        $this->assertSame(0, $hasil['BPJS Kesehatan']['lunas']);
    }

    public function test_yang_ditanggung_penjamin_tidak_dihitung_sebagai_uang_diterima(): void
    {
        $this->kunjungan('2026-06-03', 'I10', $this->bpjs);

        $hasil = app(LaporanPendapatan::class)->perPenjamin($this->juni());

        // Menjumlahkan seluruh tagihan sebagai pendapatan membuat manajemen
        // mengira punya uang yang sebenarnya masih piutang klaim.
        $this->assertSame(0, $hasil->sum('lunas'));
        $this->assertSame(50000, $hasil->sum('ditanggung_penjamin'));
        $this->assertSame(50000, $hasil->sum('total'));
    }

    public function test_rekap_kunjungan_memisahkan_rawat_jalan_dan_rawat_inap(): void
    {
        $this->kunjungan('2026-06-01', 'J06.9', poli: $this->poliUmum);
        $this->kunjungan('2026-06-02', 'A09', poli: $this->poliUmum);
        $this->kunjungan('2026-06-03', 'A01.0', poli: $this->poliAnak, rawatInap: true);

        $hasil = app(LaporanKunjungan::class)->perPoli($this->juni())->keyBy('poli');

        $this->assertSame(2, $hasil['Poli Umum']['rawat_jalan']);
        $this->assertSame(0, $hasil['Poli Umum']['rawat_inap']);
        $this->assertSame(0, $hasil['Poli Anak']['rawat_jalan']);
        $this->assertSame(1, $hasil['Poli Anak']['rawat_inap']);
        $this->assertSame(1, $hasil['Poli Anak']['total']);
    }

    public function test_rekap_kunjungan_di_luar_periode_tidak_terhitung(): void
    {
        $this->kunjungan('2026-05-30', 'J06.9');

        $this->assertSame(0, app(LaporanKunjungan::class)->perPoli($this->juni())->sum('total'));
    }

    public function test_seluruh_laporan_menolak_rentang_terbalik(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RentangTanggal::dari('2026-06-30', '2026-06-01');
    }

    public function test_periode_sepi_menghasilkan_laporan_kosong_tanpa_galat(): void
    {
        $rentang = RentangTanggal::dari('2026-01-01', '2026-01-31');

        $this->assertTrue(app(LaporanMorbiditas::class)->sepuluhBesar($rentang)->isEmpty());
        $this->assertTrue(app(LaporanPendapatan::class)->perPenjamin($rentang)->isEmpty());
        $this->assertTrue(app(LaporanKunjungan::class)->perPoli($rentang)->isEmpty());
    }
}
