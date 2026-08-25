<?php

namespace Tests\Feature;

use App\Enums\JenisDiagnosa;
use App\Enums\JenisLayanan;
use App\Models\Icd10;
use App\Models\Kunjungan;
use App\Models\OrderRadiologi;
use App\Models\OrderRadiologiDetail;
use App\Models\PemeriksaanRadiologi;
use App\Models\Penjamin;
use App\Models\Tarif;
use App\Models\Tindakan;
use App\Models\TindakanKunjungan;
use App\Models\User;
use App\Services\PelaksanaanRadiologi;
use App\Services\PemeriksaanKlinis;
use App\Services\PemesananRadiologi;
use App\Services\PenulisanEkspertise;
use App\Services\TindakanPelayanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class TagihanRadiologiTest extends TestCase
{
    use RefreshDatabase;

    private Penjamin $umum;

    private PemeriksaanRadiologi $toraks;

    private Tindakan $konsultasi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $this->toraks = PemeriksaanRadiologi::factory()->create([
            'nama' => 'Rontgen Toraks PA', 'modalitas' => 'rontgen',
        ]);
        $this->konsultasi = Tindakan::factory()->create(['nama' => 'Konsultasi Dokter Umum']);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Radiologi, 'layanan_id' => $this->toraks->id,
            'penjamin_id' => $this->umum->id, 'harga' => 150000, 'berlaku_mulai' => '2026-01-01',
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
            'subjective' => 'Batuk lama', 'objective' => 'Ronki basah kasar',
            'assessment' => 'Suspek bronkitis', 'plan' => 'Rontgen toraks',
        ], $dokter);
        $klinis->tambahDiagnosa($kunjungan, Icd10::factory()->create()->id, JenisDiagnosa::Primer);

        return $klinis->selesaikan($kunjungan->refresh(), $dokter);
    }

    private function pesan(Kunjungan $kunjungan): OrderRadiologi
    {
        return app(PemesananRadiologi::class)
            ->pesan($kunjungan, [$this->toraks->id], User::factory()->create(), 'Batuk kronis');
    }

    private function jalankanSampaiEkspertise(Kunjungan $kunjungan): OrderRadiologi
    {
        $order = $this->pesan($kunjungan);

        app(PelaksanaanRadiologi::class)->kerjakan($order, 'FILM-1', User::factory()->create());
        app(PenulisanEkspertise::class)->tulis($order->refresh(), [
            $order->detail->first()->id => [
                'temuan' => 'Corakan bronkovaskular meningkat.',
                'kesan' => 'Bronkitis kronis.',
                'saran' => null,
            ],
        ], User::factory()->create());

        return $order->refresh();
    }

    public function test_biaya_radiologi_masuk_ke_tagihan_saat_kunjungan_diselesaikan(): void
    {
        $kunjungan = $this->kunjunganDenganKonsultasi();
        $this->jalankanSampaiEkspertise($kunjungan);

        $this->selesaikan($kunjungan->refresh());
        $tagihan = $kunjungan->refresh()->tagihan;

        // 50.000 konsultasi + 150.000 rontgen toraks
        $this->assertSame(200000, (int) $tagihan->total);
        $this->assertSame(200000, (int) $tagihan->ditagihkan_ke_pasien);
        $this->assertDatabaseHas('tagihan_detail', [
            'tagihan_id' => $tagihan->id,
            'sumber_tipe' => OrderRadiologiDetail::class,
            'deskripsi' => 'Rontgen Toraks PA',
            'tarif_satuan' => 150000,
        ]);
    }

    public function test_rincian_tagihan_memuat_tindakan_dan_radiologi_sebagai_sumber_berbeda(): void
    {
        $kunjungan = $this->kunjunganDenganKonsultasi();
        $this->jalankanSampaiEkspertise($kunjungan);
        $this->selesaikan($kunjungan->refresh());

        $ringkasan = $kunjungan->refresh()->tagihan->detail()
            ->selectRaw('sumber_tipe, SUM(subtotal) AS total')
            ->groupBy('sumber_tipe')
            ->pluck('total', 'sumber_tipe');

        $this->assertSame(50000, (int) $ringkasan[TindakanKunjungan::class]);
        $this->assertSame(150000, (int) $ringkasan[OrderRadiologiDetail::class]);
    }

    public function test_order_yang_dibatalkan_sebelum_dikerjakan_tidak_ditagihkan(): void
    {
        $kunjungan = $this->kunjunganDenganKonsultasi();

        $order = $this->pesan($kunjungan);
        app(PemesananRadiologi::class)->batalkan($order, User::factory()->create(), 'Salah pesan');

        $this->selesaikan($kunjungan->refresh());
        $tagihan = $kunjungan->refresh()->tagihan;

        $this->assertSame(50000, (int) $tagihan->total);
        $this->assertSame(0, $tagihan->detail()->where('sumber_tipe', OrderRadiologiDetail::class)->count());
    }

    public function test_order_yang_dibatalkan_setelah_dikerjakan_tetap_ditagihkan(): void
    {
        $kunjungan = $this->kunjunganDenganKonsultasi();

        $order = $this->pesan($kunjungan);
        app(PelaksanaanRadiologi::class)->kerjakan($order, 'FILM-1', User::factory()->create());
        app(PemesananRadiologi::class)->batalkan($order->refresh(), User::factory()->create(), 'Citra tidak terbaca');

        $this->selesaikan($kunjungan->refresh());

        // Film dan waktu alatnya sudah terpakai, jadi tetap ditagihkan (aturan 57).
        $this->assertSame(200000, (int) $kunjungan->refresh()->tagihan->total);
    }

    public function test_kunjungan_tidak_bisa_diselesaikan_saat_ekspertise_belum_ada(): void
    {
        $kunjungan = $this->kunjunganDenganKonsultasi();
        $order = $this->pesan($kunjungan);

        app(PelaksanaanRadiologi::class)->kerjakan($order, 'FILM-1', User::factory()->create());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($order->no_order);

        $this->selesaikan($kunjungan->refresh());
    }
}
