<?php

namespace Tests\Feature;

use App\Enums\JenisLayanan;
use App\Enums\MetodePembayaran;
use App\Enums\StatusTagihan;
use App\Models\BatchObat;
use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\Penjamin;
use App\Models\Tarif;
use App\Models\Resep;
use App\Models\Tagihan;
use App\Models\User;
use App\Services\PenulisanResep;
use App\Services\PenyiapanResep;
use App\Services\ProsesPembayaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class TagihanObatTest extends TestCase
{
    use RefreshDatabase;

    private Obat $obat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->obat = Obat::factory()->create(['nama' => 'Paracetamol 500 mg']);
    }

    /** @return array{0: Kunjungan, 1: Tagihan, 2: Resep} */
    private function siapkanSkenario(string $jenisPenjamin): array
    {
        $penjamin = Penjamin::factory()->create([
            'kode' => $jenisPenjamin === 'tunai' ? 'UMUM' : 'BPJS',
            'jenis' => $jenisPenjamin,
        ]);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Obat,
            'layanan_id' => $this->obat->id, 'penjamin_id' => $penjamin->id,
            'harga' => 1500, 'berlaku_mulai' => '2026-01-01',
        ]);

        BatchObat::factory()->create([
            'obat_id' => $this->obat->id, 'tanggal_kedaluwarsa' => '2029-01-31',
            'jumlah_awal' => 100, 'jumlah_tersisa' => 100, 'harga_beli' => 800,
        ]);

        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $penjamin->id]);

        $tagihan = Tagihan::factory()->create([
            'kunjungan_id' => $kunjungan->id,
            'penjamin_id' => $penjamin->id,
            'total' => 50000,
            'ditanggung_penjamin' => $jenisPenjamin === 'penjamin' ? 50000 : 0,
            'ditagihkan_ke_pasien' => $jenisPenjamin === 'penjamin' ? 0 : 50000,
            'status' => $jenisPenjamin === 'penjamin'
                ? StatusTagihan::DitanggungPenjamin
                : StatusTagihan::BelumBayar,
        ]);

        // Baris tindakan harus benar-benar ada: hitungUlang() menjumlahkan dari
        // rincian, bukan dari kolom total. Tagihan bertotal tanpa rincian adalah
        // keadaan yang tidak pernah terjadi di sistem sungguhan.
        $tagihan->detail()->create([
            'deskripsi' => 'Konsultasi Dokter Umum',
            'jumlah' => 1,
            'tarif_satuan' => 50000,
            'subtotal' => 50000,
        ]);

        $resep = app(PenulisanResep::class)->tulis($kunjungan, [[
            'obat_id' => $this->obat->id, 'jumlah' => 10, 'aturan_pakai' => '3x1',
        ]], User::factory()->create());

        return [$kunjungan, $tagihan, $resep];
    }

    public function test_biaya_obat_masuk_ke_tagihan_kunjungan_yang_sama(): void
    {
        [$kunjungan, $tagihan, $resep] = $this->siapkanSkenario('tunai');

        app(PenyiapanResep::class)->siapkan($resep, User::factory()->create());

        $tagihan->refresh();

        $this->assertSame(65000, (int) $tagihan->total);
        $this->assertSame(65000, (int) $tagihan->ditagihkan_ke_pasien);
        $this->assertDatabaseHas('tagihan_detail', [
            'tagihan_id' => $tagihan->id,
            'deskripsi' => 'Paracetamol 500 mg',
            'jumlah' => 10,
            'tarif_satuan' => 1500,
            'subtotal' => 15000,
        ]);
    }

    public function test_obat_pasien_bpjs_menambah_total_tapi_tetap_tidak_ditagihkan(): void
    {
        [$kunjungan, $tagihan, $resep] = $this->siapkanSkenario('penjamin');

        app(PenyiapanResep::class)->siapkan($resep, User::factory()->create());

        $tagihan->refresh();

        $this->assertSame(65000, (int) $tagihan->total);
        $this->assertSame(65000, (int) $tagihan->ditanggung_penjamin);
        $this->assertSame(0, (int) $tagihan->ditagihkan_ke_pasien);
        $this->assertSame(StatusTagihan::DitanggungPenjamin, $tagihan->status);
    }

    public function test_tagihan_yang_sudah_lunas_tidak_bisa_ditambahi_baris_obat(): void
    {
        [$kunjungan, $tagihan, $resep] = $this->siapkanSkenario('tunai');
        $tagihan->update(['status' => StatusTagihan::Lunas]);

        $this->expectException(RuntimeException::class);

        app(PenyiapanResep::class)->siapkan($resep, User::factory()->create());
    }

    public function test_stok_tidak_berkurang_bila_pembebanan_tagihan_ditolak(): void
    {
        [$kunjungan, $tagihan, $resep] = $this->siapkanSkenario('tunai');
        $tagihan->update(['status' => StatusTagihan::Lunas]);
        $batch = BatchObat::where('obat_id', $this->obat->id)->first();

        try {
            app(PenyiapanResep::class)->siapkan($resep, User::factory()->create());
        } catch (RuntimeException) {
            // diabaikan; yang diuji adalah keadaan stok sesudahnya
        }

        $this->assertSame(100, (int) $batch->refresh()->jumlah_tersisa);
    }

    public function test_tagihan_tidak_bisa_dilunasi_saat_resep_belum_disiapkan(): void
    {
        [$kunjungan, $tagihan, $resep] = $this->siapkanSkenario('tunai');
        $kasir = User::factory()->create();

        try {
            app(ProsesPembayaran::class)->bayar($tagihan, MetodePembayaran::Tunai, 50000, $kasir);
            $this->fail('Pembayaran seharusnya ditolak karena resep belum disiapkan.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString($resep->no_resep, $e->getMessage());
        }

        $this->assertSame(StatusTagihan::BelumBayar, $tagihan->refresh()->status);
    }

    public function test_tagihan_bisa_dilunasi_setelah_resep_disiapkan(): void
    {
        [$kunjungan, $tagihan, $resep] = $this->siapkanSkenario('tunai');

        app(PenyiapanResep::class)->siapkan($resep, User::factory()->create());
        $tagihan->refresh();

        app(ProsesPembayaran::class)->bayar($tagihan, MetodePembayaran::Tunai, 65000, User::factory()->create());

        $this->assertSame(StatusTagihan::Lunas, $tagihan->refresh()->status);
    }

    public function test_kunjungan_tanpa_resep_tetap_bisa_dilunasi(): void
    {
        $umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $umum->id]);

        $tagihan = Tagihan::factory()->create([
            'kunjungan_id' => $kunjungan->id, 'penjamin_id' => $umum->id,
            'total' => 50000, 'ditagihkan_ke_pasien' => 50000,
            'status' => StatusTagihan::BelumBayar,
        ]);

        app(ProsesPembayaran::class)->bayar($tagihan, MetodePembayaran::Tunai, 50000, User::factory()->create());

        $this->assertSame(StatusTagihan::Lunas, $tagihan->refresh()->status);
    }
}
