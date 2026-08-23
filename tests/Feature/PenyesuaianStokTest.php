<?php

namespace Tests\Feature;

use App\Enums\JenisMutasiStok;
use App\Models\BatchObat;
use App\Models\MutasiStok;
use App\Models\User;
use App\Services\PenyesuaianStok;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PenyesuaianStokTest extends TestCase
{
    use RefreshDatabase;

    private function batch(int $tersisa = 50): BatchObat
    {
        return BatchObat::factory()->create([
            'jumlah_awal' => 100, 'jumlah_tersisa' => $tersisa,
            'tanggal_kedaluwarsa' => '2029-01-31',
        ]);
    }

    public function test_penyesuaian_turun_mencatat_selisih_negatif(): void
    {
        $batch = $this->batch(50);

        app(PenyesuaianStok::class)->sesuaikan($batch, 45, 'Selisih hasil opname', User::factory()->create());

        $this->assertSame(45, (int) $batch->refresh()->jumlah_tersisa);

        $mutasi = MutasiStok::where('jenis', JenisMutasiStok::Penyesuaian)->latest('id')->first();

        $this->assertSame(-5, (int) $mutasi->jumlah);
        $this->assertSame(45, (int) $mutasi->stok_sesudah);
    }

    public function test_penyesuaian_naik_mencatat_selisih_positif(): void
    {
        $batch = $this->batch(50);

        app(PenyesuaianStok::class)->sesuaikan($batch, 58, 'Temuan opname', User::factory()->create());

        $mutasi = MutasiStok::where('jenis', JenisMutasiStok::Penyesuaian)->latest('id')->first();

        $this->assertSame(8, (int) $mutasi->jumlah);
        $this->assertSame(58, (int) $mutasi->stok_sesudah);
    }

    public function test_penyesuaian_tanpa_alasan_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        app(PenyesuaianStok::class)->sesuaikan($this->batch(), 40, '  ', User::factory()->create());
    }

    public function test_jumlah_baru_negatif_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        app(PenyesuaianStok::class)->sesuaikan($this->batch(), -1, 'Salah input', User::factory()->create());
    }

    public function test_jumlah_yang_sama_tidak_menghasilkan_mutasi(): void
    {
        $batch = $this->batch(50);

        app(PenyesuaianStok::class)->sesuaikan($batch, 50, 'Cocok dengan fisik', User::factory()->create());

        $this->assertSame(0, MutasiStok::where('jenis', JenisMutasiStok::Penyesuaian)->count());
    }

    public function test_alasan_penyesuaian_tercatat_di_audit_log(): void
    {
        $batch = $this->batch(50);

        app(PenyesuaianStok::class)->sesuaikan($batch, 45, 'Selisih hasil opname', User::factory()->create());

        $this->assertDatabaseHas('audit_logs', ['alasan' => 'Selisih hasil opname']);
    }
}
