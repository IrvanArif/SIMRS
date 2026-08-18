<?php

namespace Tests\Feature;

use App\Models\HargaObat;
use App\Models\Obat;
use App\Models\Penjamin;
use App\Services\PencariHargaObat;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class HargaObatTest extends TestCase
{
    use RefreshDatabase;

    private Obat $obat;
    private Penjamin $umum;
    private Penjamin $bpjs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->obat = Obat::factory()->create(['nama' => 'Paracetamol 500 mg']);
        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $this->bpjs = Penjamin::factory()->create(['kode' => 'BPJS', 'jenis' => 'penjamin']);
    }

    private function harga(Penjamin $penjamin, int $nilai, string $berlakuMulai = '2026-01-01'): void
    {
        HargaObat::factory()->create([
            'obat_id' => $this->obat->id,
            'penjamin_id' => $penjamin->id,
            'harga' => $nilai,
            'berlaku_mulai' => $berlakuMulai,
        ]);
    }

    public function test_harga_diambil_sesuai_penjamin(): void
    {
        $this->harga($this->umum, 1500);
        $this->harga($this->bpjs, 1000);

        $this->assertSame(1000, app(PencariHargaObat::class)->untuk($this->obat->id, $this->bpjs->id));
    }

    public function test_harga_jatuh_tempo_ke_umum_bila_penjamin_belum_punya_harga(): void
    {
        $this->harga($this->umum, 1500);

        $this->assertSame(1500, app(PencariHargaObat::class)->untuk($this->obat->id, $this->bpjs->id));
    }

    public function test_ketiadaan_harga_penjamin_dicatat_sebagai_peringatan(): void
    {
        $this->harga($this->umum, 1500);
        Log::spy();

        app(PencariHargaObat::class)->untuk($this->obat->id, $this->bpjs->id);

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_harga_terbaru_yang_sudah_berlaku_yang_dipakai(): void
    {
        $this->harga($this->umum, 1500, '2026-01-01');
        $this->harga($this->umum, 1800, '2026-06-01');

        $pencari = app(PencariHargaObat::class);

        $this->assertSame(1500, $pencari->untuk($this->obat->id, $this->umum->id, Carbon::parse('2026-03-01')));
        $this->assertSame(1800, $pencari->untuk($this->obat->id, $this->umum->id, Carbon::parse('2026-08-18')));
    }

    public function test_tanpa_harga_sama_sekali_maka_gagal_dengan_pesan_jelas(): void
    {
        $this->expectException(RuntimeException::class);

        app(PencariHargaObat::class)->untuk($this->obat->id, $this->bpjs->id);
    }

    public function test_harga_ganda_untuk_penjamin_dan_tanggal_berlaku_sama_ditolak_database(): void
    {
        $this->harga($this->umum, 1500, '2026-01-01');

        $this->expectException(QueryException::class);

        $this->harga($this->umum, 1600, '2026-01-01');
    }
}
