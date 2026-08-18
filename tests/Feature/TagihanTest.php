<?php

namespace Tests\Feature;

use App\Enums\JenisDiagnosa;
use App\Enums\StatusTagihan;
use App\Models\Icd10;
use App\Models\Kunjungan;
use App\Models\Penjamin;
use App\Models\TarifTindakan;
use App\Models\Tindakan;
use App\Models\User;
use App\Services\PemeriksaanKlinis;
use App\Services\PenyusunTagihan;
use App\Services\TindakanPelayanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagihanTest extends TestCase
{
    use RefreshDatabase;

    private Penjamin $umum;
    private Penjamin $bpjs;
    private Tindakan $konsultasi;
    private Tindakan $suntik;

    protected function setUp(): void
    {
        parent::setUp();

        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $this->bpjs = Penjamin::factory()->create(['kode' => 'BPJS', 'jenis' => 'penjamin']);
        $this->konsultasi = Tindakan::factory()->create(['nama' => 'Konsultasi Dokter Umum']);
        $this->suntik = Tindakan::factory()->create(['nama' => 'Injeksi Intramuskular']);

        foreach ([[$this->konsultasi, 50000, 35000], [$this->suntik, 25000, 15000]] as [$tindakan, $tarifUmum, $tarifBpjs]) {
            TarifTindakan::factory()->create([
                'tindakan_id' => $tindakan->id, 'penjamin_id' => $this->umum->id,
                'tarif' => $tarifUmum, 'berlaku_mulai' => '2026-01-01',
            ]);
            TarifTindakan::factory()->create([
                'tindakan_id' => $tindakan->id, 'penjamin_id' => $this->bpjs->id,
                'tarif' => $tarifBpjs, 'berlaku_mulai' => '2026-01-01',
            ]);
        }
    }

    private function kunjunganDenganTindakan(Penjamin $penjamin): Kunjungan
    {
        $kunjungan = Kunjungan::factory()->create([
            'penjamin_id' => $penjamin->id,
            'tanggal' => '2026-08-18',
            'no_kartu_penjamin' => $penjamin->ditanggung() ? '0001234567890' : null,
        ]);

        $petugas = User::factory()->create();
        app(TindakanPelayanan::class)->tambah($kunjungan, $this->konsultasi->id, 1, $petugas);
        app(TindakanPelayanan::class)->tambah($kunjungan, $this->suntik->id, 2, $petugas);

        return $kunjungan->refresh();
    }

    public function test_tagihan_disusun_dari_tindakan_dikali_tarif_sesuai_penjamin(): void
    {
        $tagihan = app(PenyusunTagihan::class)->susun($this->kunjunganDenganTindakan($this->umum));

        $this->assertSame(100000, (int) $tagihan->total);
        $this->assertSame(100000, (int) $tagihan->ditagihkan_ke_pasien);
        $this->assertSame(StatusTagihan::BelumBayar, $tagihan->status);
    }

    public function test_tagihan_pasien_bpjs_ditagihkan_nol_tapi_total_tetap_tercatat_penuh(): void
    {
        $tagihan = app(PenyusunTagihan::class)->susun($this->kunjunganDenganTindakan($this->bpjs));

        $this->assertSame(65000, (int) $tagihan->total);
        $this->assertSame(65000, (int) $tagihan->ditanggung_penjamin);
        $this->assertSame(0, (int) $tagihan->ditagihkan_ke_pasien);
        $this->assertSame(StatusTagihan::DitanggungPenjamin, $tagihan->status);
    }

    public function test_rincian_tagihan_memuat_setiap_tindakan_dengan_subtotalnya(): void
    {
        $tagihan = app(PenyusunTagihan::class)->susun($this->kunjunganDenganTindakan($this->umum));

        $this->assertSame(2, $tagihan->detail()->count());
        $this->assertDatabaseHas('tagihan_detail', [
            'tagihan_id' => $tagihan->id,
            'deskripsi' => 'Injeksi Intramuskular',
            'jumlah' => 2,
            'tarif_satuan' => 25000,
            'subtotal' => 50000,
        ]);
    }

    public function test_nomor_tagihan_berformat_tanggal_kunjungan(): void
    {
        $tagihan = app(PenyusunTagihan::class)->susun($this->kunjunganDenganTindakan($this->umum));

        $this->assertSame('TG-20260818-0001', $tagihan->no_tagihan);
    }

    public function test_tagihan_hanya_disusun_sekali(): void
    {
        $kunjungan = $this->kunjunganDenganTindakan($this->umum);
        $layanan = app(PenyusunTagihan::class);

        $pertama = $layanan->susun($kunjungan);
        $kedua = $layanan->susun($kunjungan->refresh());

        $this->assertSame($pertama->id, $kedua->id);
        $this->assertSame(1, $kunjungan->refresh()->tagihan()->count());
    }

    public function test_kunjungan_tanpa_tindakan_menghasilkan_tagihan_nol(): void
    {
        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]);

        $tagihan = app(PenyusunTagihan::class)->susun($kunjungan);

        $this->assertSame(0, (int) $tagihan->total);
    }

    public function test_tagihan_terbentuk_otomatis_saat_kunjungan_diselesaikan(): void
    {
        $kunjungan = $this->kunjunganDenganTindakan($this->umum);
        $dokter = User::factory()->create(['dokter_id' => $kunjungan->dokter_id]);
        $klinis = app(PemeriksaanKlinis::class);

        $klinis->catatSoap($kunjungan, [
            'subjective' => 'Demam dua hari', 'objective' => 'Suhu 38,5 °C',
            'assessment' => 'Demam tifoid', 'plan' => 'Antibiotik',
        ], $dokter);
        $klinis->tambahDiagnosa($kunjungan, Icd10::factory()->create()->id, JenisDiagnosa::Primer);
        $klinis->selesaikan($kunjungan, $dokter);

        $this->assertSame(100000, (int) $kunjungan->refresh()->tagihan->total);
    }
}
