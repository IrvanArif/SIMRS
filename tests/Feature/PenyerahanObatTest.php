<?php

namespace Tests\Feature;

use App\Enums\JenisLayanan;
use App\Enums\JenisMutasiStok;
use App\Enums\MetodePembayaran;
use App\Enums\StatusResep;
use App\Enums\StatusTagihan;
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
use App\Services\PenyerahanObat;
use App\Services\PenyiapanResep;
use App\Services\ProsesPembayaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class PenyerahanObatTest extends TestCase
{
    use RefreshDatabase;

    private Obat $obat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->obat = Obat::factory()->create(['nama' => 'Amoksisilin 500 mg']);
    }

    /** @return array{0: Tagihan, 1: Resep, 2: BatchObat} */
    private function resepSiap(string $jenisPenjamin): array
    {
        $penjamin = Penjamin::factory()->create([
            'kode' => $jenisPenjamin === 'tunai' ? 'UMUM' : 'BPJS',
            'jenis' => $jenisPenjamin,
        ]);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Obat,
            'layanan_id' => $this->obat->id, 'penjamin_id' => $penjamin->id,
            'harga' => 2000, 'berlaku_mulai' => '2026-01-01',
        ]);

        $batch = BatchObat::factory()->create([
            'obat_id' => $this->obat->id, 'tanggal_kedaluwarsa' => '2029-01-31',
            'jumlah_awal' => 100, 'jumlah_tersisa' => 100, 'harga_beli' => 1200,
        ]);

        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $penjamin->id]);

        $tagihan = Tagihan::factory()->create([
            'kunjungan_id' => $kunjungan->id, 'penjamin_id' => $penjamin->id,
            'status' => $jenisPenjamin === 'penjamin'
                ? StatusTagihan::DitanggungPenjamin
                : StatusTagihan::BelumBayar,
        ]);

        $resep = app(PenulisanResep::class)->tulis($kunjungan, [[
            'obat_id' => $this->obat->id, 'jumlah' => 10, 'aturan_pakai' => '3x1',
        ]], User::factory()->create());

        $resep = app(PenyiapanResep::class)->siapkan($resep, User::factory()->create());

        return [$tagihan->refresh(), $resep, $batch];
    }

    public function test_obat_pasien_umum_tidak_bisa_diserahkan_sebelum_lunas(): void
    {
        [$tagihan, $resep] = $this->resepSiap('tunai');

        $this->expectException(RuntimeException::class);

        app(PenyerahanObat::class)->serahkan($resep, User::factory()->create());
    }

    public function test_obat_pasien_umum_bisa_diserahkan_setelah_lunas(): void
    {
        [$tagihan, $resep] = $this->resepSiap('tunai');

        app(ProsesPembayaran::class)->bayar(
            $tagihan, MetodePembayaran::Tunai, (int) $tagihan->ditagihkan_ke_pasien, User::factory()->create()
        );

        $diserahkan = app(PenyerahanObat::class)->serahkan($resep, User::factory()->create());

        $this->assertSame(StatusResep::Diserahkan, $diserahkan->status);
        $this->assertNotNull($diserahkan->diserahkan_pada);
    }

    public function test_obat_pasien_bpjs_bisa_diserahkan_tanpa_ke_kasir(): void
    {
        [$tagihan, $resep] = $this->resepSiap('penjamin');

        $diserahkan = app(PenyerahanObat::class)->serahkan($resep, User::factory()->create());

        $this->assertSame(StatusResep::Diserahkan, $diserahkan->status);
        $this->assertSame(StatusTagihan::DitanggungPenjamin, $tagihan->refresh()->status);
        $this->assertSame(0, $tagihan->pembayaran()->count());
    }

    public function test_resep_yang_belum_disiapkan_tidak_bisa_diserahkan(): void
    {
        $umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $umum->id]);

        $resep = app(PenulisanResep::class)->tulis($kunjungan, [[
            'obat_id' => $this->obat->id, 'jumlah' => 5, 'aturan_pakai' => '2x1',
        ]], User::factory()->create());

        $this->expectException(RuntimeException::class);

        app(PenyerahanObat::class)->serahkan($resep, User::factory()->create());
    }

    public function test_resep_yang_sudah_diserahkan_tidak_bisa_dibatalkan(): void
    {
        [$tagihan, $resep] = $this->resepSiap('penjamin');
        $diserahkan = app(PenyerahanObat::class)->serahkan($resep, User::factory()->create());

        $this->expectException(RuntimeException::class);

        app(PenyiapanResep::class)->batalkan($diserahkan, User::factory()->create(), 'Salah pasien');
    }

    public function test_pembatalan_penyiapan_mengembalikan_stok_ke_batch_asal(): void
    {
        [$tagihan, $resep, $batch] = $this->resepSiap('tunai');

        $this->assertSame(90, (int) $batch->refresh()->jumlah_tersisa);

        $dibatalkan = app(PenyiapanResep::class)
            ->batalkan($resep, User::factory()->create(), 'Pasien menolak obat');

        $this->assertSame(100, (int) $batch->refresh()->jumlah_tersisa);
        $this->assertSame(StatusResep::Dibuat, $dibatalkan->status);
        $this->assertSame(0, (int) $dibatalkan->detail->first()->jumlah_diserahkan);
    }

    public function test_pembatalan_mencatat_mutasi_pengembalian(): void
    {
        [$tagihan, $resep, $batch] = $this->resepSiap('tunai');

        app(PenyiapanResep::class)->batalkan($resep, User::factory()->create(), 'Pasien menolak obat');

        $mutasi = MutasiStok::where('jenis', JenisMutasiStok::Pengembalian)->first();

        $this->assertSame(10, (int) $mutasi->jumlah);
        $this->assertSame(100, (int) $mutasi->stok_sesudah);
    }

    public function test_pembatalan_menghapus_baris_obat_dari_tagihan(): void
    {
        [$tagihan, $resep] = $this->resepSiap('tunai');

        $this->assertSame(20000, (int) $tagihan->refresh()->total);

        app(PenyiapanResep::class)->batalkan($resep, User::factory()->create(), 'Pasien menolak obat');

        $this->assertSame(0, (int) $tagihan->refresh()->total);
        $this->assertSame(0, $tagihan->detail()->whereNotNull('resep_detail_id')->count());
    }

    public function test_pembatalan_wajib_menyertakan_alasan(): void
    {
        [$tagihan, $resep] = $this->resepSiap('tunai');

        $this->expectException(ValidationException::class);

        app(PenyiapanResep::class)->batalkan($resep, User::factory()->create(), '   ');
    }

    public function test_alasan_pembatalan_tercatat_di_audit_log(): void
    {
        [$tagihan, $resep] = $this->resepSiap('tunai');

        app(PenyiapanResep::class)->batalkan($resep, User::factory()->create(), 'Stok salah hitung');

        $this->assertDatabaseHas('audit_logs', ['alasan' => 'Stok salah hitung']);
    }

    public function test_resep_yang_dibatalkan_bisa_disiapkan_ulang(): void
    {
        [$tagihan, $resep, $batch] = $this->resepSiap('tunai');

        $dibatalkan = app(PenyiapanResep::class)
            ->batalkan($resep, User::factory()->create(), 'Salah hitung');

        $ulang = app(PenyiapanResep::class)->siapkan($dibatalkan, User::factory()->create());

        $this->assertSame(StatusResep::Disiapkan, $ulang->status);
        $this->assertSame(90, (int) $batch->refresh()->jumlah_tersisa);
        $this->assertSame(20000, (int) $tagihan->refresh()->total);
    }
}
