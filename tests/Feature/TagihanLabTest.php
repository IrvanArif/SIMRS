<?php

namespace Tests\Feature;

use App\Enums\JenisDiagnosa;
use App\Enums\JenisLayanan;
use App\Models\Icd10;
use App\Models\Kunjungan;
use App\Models\OrderLab;
use App\Models\OrderLabDetail;
use App\Models\ParameterLab;
use App\Models\PemeriksaanLab;
use App\Models\Penjamin;
use App\Models\RujukanLab;
use App\Models\Tarif;
use App\Models\Tindakan;
use App\Models\TindakanKunjungan;
use App\Models\User;
use App\Services\PemeriksaanKlinis;
use App\Services\PemeriksaanLaboratorium;
use App\Services\PemesananLab;
use App\Services\TindakanPelayanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagihanLabTest extends TestCase
{
    use RefreshDatabase;

    private Penjamin $umum;
    private PemeriksaanLab $darahRutin;
    private ParameterLab $hemoglobin;
    private Tindakan $konsultasi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);

        $this->darahRutin = PemeriksaanLab::factory()->create(['nama' => 'Darah Rutin']);
        $this->hemoglobin = ParameterLab::factory()->create([
            'pemeriksaan_lab_id' => $this->darahRutin->id,
            'kode' => 'HB', 'nama' => 'Hemoglobin', 'satuan' => 'g/dL',
        ]);
        RujukanLab::factory()->create([
            'parameter_lab_id' => $this->hemoglobin->id,
            'jenis_kelamin' => 'semua', 'nilai_min' => 12.0, 'nilai_maks' => 17.0,
        ]);

        $this->konsultasi = Tindakan::factory()->create(['nama' => 'Konsultasi Dokter Umum']);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Lab, 'layanan_id' => $this->darahRutin->id,
            'penjamin_id' => $this->umum->id, 'harga' => 75000, 'berlaku_mulai' => '2026-01-01',
        ]);
        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Tindakan, 'layanan_id' => $this->konsultasi->id,
            'penjamin_id' => $this->umum->id, 'harga' => 50000, 'berlaku_mulai' => '2026-01-01',
        ]);
    }

    private function kunjunganDenganKonsultasi(): Kunjungan
    {
        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]);

        app(TindakanPelayanan::class)
            ->tambah($kunjungan, $this->konsultasi->id, 1, User::factory()->create());

        return $kunjungan->refresh();
    }

    private function selesaikan(Kunjungan $kunjungan): Kunjungan
    {
        $dokter = User::factory()->create(['dokter_id' => $kunjungan->dokter_id]);
        $klinis = app(PemeriksaanKlinis::class);

        $klinis->catatSoap($kunjungan, [
            'subjective' => 'Lemas', 'objective' => 'Konjungtiva pucat',
            'assessment' => 'Anemia', 'plan' => 'Terapi besi',
        ], $dokter);
        $klinis->tambahDiagnosa($kunjungan, Icd10::factory()->create()->id, JenisDiagnosa::Primer);

        return $klinis->selesaikan($kunjungan->refresh(), $dokter);
    }

    private function jalankanLabSampaiValidasi(Kunjungan $kunjungan): OrderLab
    {
        $analis = User::factory()->create();
        $lab = app(PemeriksaanLaboratorium::class);

        $order = app(PemesananLab::class)
            ->pesan($kunjungan, [$this->darahRutin->id], User::factory()->create());

        $lab->ambilSampel($order, $analis);
        $lab->entriHasil($order->refresh(), [$this->hemoglobin->id => 14.0], $analis);
        $lab->validasi($order->refresh(), $analis);

        return $order->refresh();
    }

    public function test_biaya_lab_masuk_ke_tagihan_saat_kunjungan_diselesaikan(): void
    {
        $kunjungan = $this->kunjunganDenganKonsultasi();
        $this->jalankanLabSampaiValidasi($kunjungan);

        $this->selesaikan($kunjungan->refresh());
        $tagihan = $kunjungan->refresh()->tagihan;

        // 50.000 konsultasi + 75.000 darah rutin
        $this->assertSame(125000, (int) $tagihan->total);
        $this->assertSame(125000, (int) $tagihan->ditagihkan_ke_pasien);
        $this->assertDatabaseHas('tagihan_detail', [
            'tagihan_id' => $tagihan->id,
            'sumber_tipe' => OrderLabDetail::class,
            'deskripsi' => 'Darah Rutin',
            'tarif_satuan' => 75000,
        ]);
    }

    public function test_rincian_tagihan_memuat_tindakan_dan_lab_sebagai_sumber_berbeda(): void
    {
        $kunjungan = $this->kunjunganDenganKonsultasi();
        $this->jalankanLabSampaiValidasi($kunjungan);
        $this->selesaikan($kunjungan->refresh());

        $ringkasan = $kunjungan->refresh()->tagihan->detail()
            ->selectRaw('sumber_tipe, SUM(subtotal) AS total')
            ->groupBy('sumber_tipe')
            ->pluck('total', 'sumber_tipe');

        $this->assertSame(50000, (int) $ringkasan[TindakanKunjungan::class]);
        $this->assertSame(75000, (int) $ringkasan[OrderLabDetail::class]);
    }

    public function test_order_yang_dibatalkan_sebelum_sampel_tidak_ditagihkan(): void
    {
        $kunjungan = $this->kunjunganDenganKonsultasi();

        $order = app(PemesananLab::class)
            ->pesan($kunjungan, [$this->darahRutin->id], User::factory()->create());
        app(PemesananLab::class)->batalkan($order, User::factory()->create(), 'Salah pesan');

        $this->selesaikan($kunjungan->refresh());
        $tagihan = $kunjungan->refresh()->tagihan;

        $this->assertSame(50000, (int) $tagihan->total);
        $this->assertSame(0, $tagihan->detail()->where('sumber_tipe', OrderLabDetail::class)->count());
    }

    public function test_order_yang_dibatalkan_setelah_sampel_tetap_ditagihkan(): void
    {
        $kunjungan = $this->kunjunganDenganKonsultasi();

        $order = app(PemesananLab::class)
            ->pesan($kunjungan, [$this->darahRutin->id], User::factory()->create());
        app(PemeriksaanLaboratorium::class)->ambilSampel($order, User::factory()->create());
        app(PemesananLab::class)->batalkan($order->refresh(), User::factory()->create(), 'Sampel rusak');

        $this->selesaikan($kunjungan->refresh());

        // Bahan dan waktu kerjanya sudah terpakai, jadi tetap ditagihkan (aturan 46).
        $this->assertSame(125000, (int) $kunjungan->refresh()->tagihan->total);
    }

    public function test_kunjungan_tanpa_lab_tetap_tertagih_seperti_biasa(): void
    {
        $kunjungan = $this->kunjunganDenganKonsultasi();

        $this->selesaikan($kunjungan->refresh());

        $this->assertSame(50000, (int) $kunjungan->refresh()->tagihan->total);
    }
}
