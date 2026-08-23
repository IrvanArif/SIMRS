<?php

namespace Tests\Feature;

use App\Enums\JenisLayanan;
use App\Enums\JenisMutasiStok;
use App\Enums\StatusResep;
use App\Exceptions\SeluruhBatchKedaluwarsa;
use App\Exceptions\StokTidakCukup;
use App\Models\BatchObat;
use App\Models\Kunjungan;
use App\Models\MutasiStok;
use App\Models\Obat;
use App\Models\Penjamin;
use App\Models\Tarif;
use App\Models\Resep;
use App\Models\Tagihan;
use App\Models\User;
use App\Services\PenulisanResep;
use App\Services\PenyiapanResep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PenyiapanResepTest extends TestCase
{
    use RefreshDatabase;

    private Penjamin $umum;
    private Obat $obat;
    private Kunjungan $kunjungan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $this->obat = Obat::factory()->create(['nama' => 'Paracetamol 500 mg']);
        $this->kunjungan = Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]);

        // Tagihan sudah ada sejak dokter menyelesaikan kunjungan — apotek hanya
        // menambahinya. Penyiapan bergantung pada tagihan ini.
        Tagihan::factory()->create([
            'kunjungan_id' => $this->kunjungan->id,
            'penjamin_id' => $this->umum->id,
        ]);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Obat,
            'layanan_id' => $this->obat->id,
            'penjamin_id' => $this->umum->id,
            'harga' => 1500,
            'berlaku_mulai' => '2026-01-01',
        ]);
    }

    private function batch(string $noBatch, string $kedaluwarsa, int $jumlah): BatchObat
    {
        return BatchObat::factory()->create([
            'obat_id' => $this->obat->id,
            'no_batch' => $noBatch,
            'tanggal_kedaluwarsa' => $kedaluwarsa,
            'jumlah_awal' => $jumlah,
            'jumlah_tersisa' => $jumlah,
            'harga_beli' => 800,
        ]);
    }

    private function resep(int $jumlah = 10): Resep
    {
        return app(PenulisanResep::class)->tulis($this->kunjungan, [[
            'obat_id' => $this->obat->id,
            'jumlah' => $jumlah,
            'aturan_pakai' => '3x1 sesudah makan',
        ]], User::factory()->create());
    }

    private function apoteker(): User
    {
        return User::factory()->create();
    }

    public function test_penyiapan_mengambil_batch_yang_paling_dekat_kedaluwarsa(): void
    {
        $lama = $this->batch('LAMA', '2027-01-31', 100);
        $baru = $this->batch('BARU', '2029-01-31', 100);

        app(PenyiapanResep::class)->siapkan($this->resep(10), $this->apoteker());

        $this->assertSame(90, (int) $lama->refresh()->jumlah_tersisa);
        $this->assertSame(100, (int) $baru->refresh()->jumlah_tersisa);
    }

    public function test_satu_baris_resep_bisa_ditarik_dari_dua_batch(): void
    {
        $lama = $this->batch('LAMA', '2027-01-31', 6);
        $baru = $this->batch('BARU', '2029-01-31', 50);

        $resep = app(PenyiapanResep::class)->siapkan($this->resep(10), $this->apoteker());

        $this->assertSame(0, (int) $lama->refresh()->jumlah_tersisa);
        $this->assertSame(46, (int) $baru->refresh()->jumlah_tersisa);

        $pengambilan = $resep->detail->first()->pengambilan;

        $this->assertCount(2, $pengambilan);
        $this->assertSame(6, (int) $pengambilan->firstWhere('batch_obat_id', $lama->id)->jumlah);
        $this->assertSame(4, (int) $pengambilan->firstWhere('batch_obat_id', $baru->id)->jumlah);
    }

    public function test_batch_kedaluwarsa_tidak_ikut_dialokasikan(): void
    {
        $kedaluwarsa = $this->batch('KEDALUWARSA', now()->subDay()->toDateString(), 100);
        $layak = $this->batch('LAYAK', '2029-01-31', 100);

        app(PenyiapanResep::class)->siapkan($this->resep(10), $this->apoteker());

        $this->assertSame(100, (int) $kedaluwarsa->refresh()->jumlah_tersisa);
        $this->assertSame(90, (int) $layak->refresh()->jumlah_tersisa);
    }

    public function test_stok_kurang_menolak_penyiapan_dan_tidak_mengubah_stok(): void
    {
        $batch = $this->batch('SATU', '2029-01-31', 4);
        $resep = $this->resep(10);

        try {
            app(PenyiapanResep::class)->siapkan($resep, $this->apoteker());
            $this->fail('Penyiapan seharusnya ditolak karena stok kurang.');
        } catch (StokTidakCukup $e) {
            $this->assertStringContainsString('Paracetamol 500 mg', $e->getMessage());
        }

        $this->assertSame(4, (int) $batch->refresh()->jumlah_tersisa);
        $this->assertSame(StatusResep::Dibuat, $resep->refresh()->status);
        $this->assertSame(0, MutasiStok::where('jenis', JenisMutasiStok::Keluar)->count());
    }

    public function test_seluruh_batch_kedaluwarsa_ditolak_dengan_pesan_berbeda(): void
    {
        $this->batch('KEDALUWARSA', now()->subDay()->toDateString(), 100);

        $this->expectException(SeluruhBatchKedaluwarsa::class);

        app(PenyiapanResep::class)->siapkan($this->resep(10), $this->apoteker());
    }

    public function test_penyiapan_menyalin_harga_sesuai_penjamin_kunjungan(): void
    {
        $this->batch('SATU', '2029-01-31', 100);

        $resep = app(PenyiapanResep::class)->siapkan($this->resep(10), $this->apoteker());
        $baris = $resep->detail->first();

        $this->assertSame(1500, (int) $baris->harga_satuan);
        $this->assertSame(10, (int) $baris->jumlah_diserahkan);
        $this->assertSame(15000, $baris->subtotal());
    }

    public function test_perubahan_master_harga_tidak_mengubah_resep_yang_sudah_disiapkan(): void
    {
        $this->batch('SATU', '2029-01-31', 100);
        $resep = app(PenyiapanResep::class)->siapkan($this->resep(10), $this->apoteker());

        Tarif::query()->update(['harga' => 9999]);

        $this->assertSame(1500, (int) $resep->detail->first()->refresh()->harga_satuan);
    }

    public function test_penyiapan_mencatat_mutasi_keluar_bernilai_negatif(): void
    {
        $batch = $this->batch('SATU', '2029-01-31', 100);

        $resep = app(PenyiapanResep::class)->siapkan($this->resep(10), $this->apoteker());

        $mutasi = MutasiStok::where('jenis', JenisMutasiStok::Keluar)->first();

        $this->assertSame(-10, (int) $mutasi->jumlah);
        $this->assertSame(90, (int) $mutasi->stok_sesudah);
        $this->assertSame($batch->id, $mutasi->batch_obat_id);
        $this->assertSame($resep->id, $mutasi->resep_id);
    }

    public function test_status_resep_berubah_menjadi_disiapkan(): void
    {
        $this->batch('SATU', '2029-01-31', 100);

        $resep = app(PenyiapanResep::class)->siapkan($this->resep(10), $this->apoteker());

        $this->assertSame(StatusResep::Disiapkan, $resep->status);
        $this->assertNotNull($resep->disiapkan_pada);
        $this->assertNotNull($resep->disiapkan_oleh);
    }

    public function test_resep_yang_sudah_disiapkan_tidak_bisa_disiapkan_ulang(): void
    {
        $this->batch('SATU', '2029-01-31', 100);
        $layanan = app(PenyiapanResep::class);
        $resep = $layanan->siapkan($this->resep(10), $this->apoteker());

        $this->expectException(\RuntimeException::class);

        $layanan->siapkan($resep, $this->apoteker());
    }
}
