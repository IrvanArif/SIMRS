<?php

namespace Tests\Feature;

use App\Enums\JenisMutasiStok;
use App\Models\BatchObat;
use App\Models\MutasiStok;
use App\Models\Obat;
use App\Models\User;
use App\Services\PenerimaanObat;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StokObatTest extends TestCase
{
    use RefreshDatabase;

    private function terima(array $ganti = []): BatchObat
    {
        return app(PenerimaanObat::class)->terima(array_merge([
            'obat_id' => Obat::factory()->create()->id,
            'no_batch' => 'B2026001',
            'tanggal_kedaluwarsa' => '2027-12-31',
            'jumlah' => 100,
            'harga_beli' => 800,
        ], $ganti), User::factory()->create());
    }

    public function test_penerimaan_obat_menambah_stok_dan_mencatat_mutasi_masuk(): void
    {
        $batch = $this->terima();

        $this->assertSame(100, (int) $batch->jumlah_awal);
        $this->assertSame(100, (int) $batch->jumlah_tersisa);

        $mutasi = MutasiStok::where('batch_obat_id', $batch->id)->first();

        $this->assertSame(JenisMutasiStok::Masuk, $mutasi->jenis);
        $this->assertSame(100, (int) $mutasi->jumlah);
        $this->assertSame(100, (int) $mutasi->stok_sesudah);
        $this->assertNotNull($mutasi->dilakukan_oleh);
    }

    public function test_nomor_batch_ganda_untuk_obat_yang_sama_ditolak_database(): void
    {
        $batch = $this->terima();

        $this->expectException(QueryException::class);

        BatchObat::create([
            'obat_id' => $batch->obat_id,
            'no_batch' => $batch->no_batch,
            'tanggal_kedaluwarsa' => '2028-01-01',
            'jumlah_awal' => 10,
            'jumlah_tersisa' => 10,
            'harga_beli' => 900,
            'diterima_pada' => now(),
        ]);
    }

    public function test_nomor_batch_sama_boleh_dipakai_obat_berbeda(): void
    {
        $this->terima(['no_batch' => 'B2026001']);
        $kedua = $this->terima(['no_batch' => 'B2026001']);

        $this->assertSame('B2026001', $kedua->no_batch);
    }

    public function test_jumlah_penerimaan_minimal_satu(): void
    {
        $this->expectException(ValidationException::class);

        $this->terima(['jumlah' => 0]);
    }

    public function test_tanggal_kedaluwarsa_di_masa_lalu_ditolak_saat_penerimaan(): void
    {
        $this->expectException(ValidationException::class);

        $this->terima(['tanggal_kedaluwarsa' => now()->subDay()->toDateString()]);
    }

    public function test_batch_kedaluwarsa_tidak_masuk_scope_layak_pakai(): void
    {
        $obat = Obat::factory()->create();

        BatchObat::factory()->create([
            'obat_id' => $obat->id, 'jumlah_tersisa' => 50,
            'tanggal_kedaluwarsa' => '2020-01-01',
        ]);
        BatchObat::factory()->create([
            'obat_id' => $obat->id, 'jumlah_tersisa' => 30,
            'tanggal_kedaluwarsa' => '2030-01-01',
        ]);

        $this->assertSame(1, BatchObat::layakPakai()->where('obat_id', $obat->id)->count());
        $this->assertSame(30, $obat->stokTersedia());
    }

    public function test_batch_habis_tidak_masuk_scope_layak_pakai(): void
    {
        $obat = Obat::factory()->create();

        BatchObat::factory()->create([
            'obat_id' => $obat->id, 'jumlah_tersisa' => 0,
            'tanggal_kedaluwarsa' => '2030-01-01',
        ]);

        $this->assertSame(0, BatchObat::layakPakai()->where('obat_id', $obat->id)->count());
    }

    public function test_obat_di_bawah_stok_minimum_masuk_daftar_menipis(): void
    {
        $menipis = Obat::factory()->create(['stok_minimum' => 20]);
        $aman = Obat::factory()->create(['stok_minimum' => 20]);

        BatchObat::factory()->create([
            'obat_id' => $menipis->id, 'jumlah_tersisa' => 5,
            'tanggal_kedaluwarsa' => '2030-01-01',
        ]);
        BatchObat::factory()->create([
            'obat_id' => $aman->id, 'jumlah_tersisa' => 100,
            'tanggal_kedaluwarsa' => '2030-01-01',
        ]);

        $hasil = Obat::menipis()->pluck('id');

        $this->assertTrue($hasil->contains($menipis->id));
        $this->assertFalse($hasil->contains($aman->id));
    }

    public function test_obat_tanpa_batch_sama_sekali_dianggap_menipis(): void
    {
        $obat = Obat::factory()->create(['stok_minimum' => 10]);

        $this->assertTrue(Obat::menipis()->pluck('id')->contains($obat->id));
    }

    public function test_penerimaan_batch_tercatat_di_audit_log(): void
    {
        $batch = $this->terima();

        $this->assertDatabaseHas('audit_logs', [
            'model_tipe' => BatchObat::class,
            'model_id' => $batch->id,
            'aksi' => 'create',
        ]);
    }
}
