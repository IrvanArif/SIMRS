<?php

namespace Tests\Feature;

use App\Models\Kunjungan;
use App\Models\Penjamin;
use App\Models\TarifTindakan;
use App\Models\Tindakan;
use App\Models\User;
use App\Services\PencariTarif;
use App\Services\TindakanPelayanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class TarifTest extends TestCase
{
    use RefreshDatabase;

    private Tindakan $tindakan;
    private Penjamin $umum;
    private Penjamin $bpjs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tindakan = Tindakan::factory()->create(['nama' => 'Konsultasi Dokter Umum']);
        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $this->bpjs = Penjamin::factory()->create(['kode' => 'BPJS', 'jenis' => 'penjamin']);
    }

    private function tarif(Penjamin $penjamin, int $nilai, string $berlakuMulai = '2026-01-01'): void
    {
        TarifTindakan::factory()->create([
            'tindakan_id' => $this->tindakan->id,
            'penjamin_id' => $penjamin->id,
            'tarif' => $nilai,
            'berlaku_mulai' => $berlakuMulai,
        ]);
    }

    public function test_tarif_diambil_sesuai_penjamin_kunjungan(): void
    {
        $this->tarif($this->umum, 50000);
        $this->tarif($this->bpjs, 35000);

        $this->assertSame(35000, app(PencariTarif::class)->untuk($this->tindakan->id, $this->bpjs->id));
    }

    public function test_tarif_jatuh_tempo_ke_umum_bila_penjamin_belum_punya_tarif(): void
    {
        $this->tarif($this->umum, 50000);

        $this->assertSame(50000, app(PencariTarif::class)->untuk($this->tindakan->id, $this->bpjs->id));
    }

    public function test_ketiadaan_tarif_penjamin_dicatat_sebagai_peringatan(): void
    {
        $this->tarif($this->umum, 50000);
        Log::spy();

        app(PencariTarif::class)->untuk($this->tindakan->id, $this->bpjs->id);

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_tarif_terbaru_yang_sudah_berlaku_yang_dipakai(): void
    {
        $this->tarif($this->umum, 50000, '2026-01-01');
        $this->tarif($this->umum, 60000, '2026-06-01');

        $pencari = app(PencariTarif::class);

        $this->assertSame(50000, $pencari->untuk($this->tindakan->id, $this->umum->id, Carbon::parse('2026-03-01')));
        $this->assertSame(60000, $pencari->untuk($this->tindakan->id, $this->umum->id, Carbon::parse('2026-08-18')));
    }

    public function test_tanpa_tarif_sama_sekali_maka_gagal_dengan_pesan_jelas(): void
    {
        $this->expectException(RuntimeException::class);

        app(PencariTarif::class)->untuk($this->tindakan->id, $this->bpjs->id);
    }

    public function test_tarif_disalin_ke_tindakan_kunjungan(): void
    {
        $this->tarif($this->umum, 50000);
        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]);

        $baris = app(TindakanPelayanan::class)
            ->tambah($kunjungan, $this->tindakan->id, 1, User::factory()->create());

        $this->assertSame(50000, (int) $baris->tarif_satuan);
    }

    public function test_perubahan_master_tarif_tidak_mengubah_tindakan_yang_sudah_dicatat(): void
    {
        $this->tarif($this->umum, 50000);
        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]);

        $baris = app(TindakanPelayanan::class)
            ->tambah($kunjungan, $this->tindakan->id, 1, User::factory()->create());

        TarifTindakan::where('tindakan_id', $this->tindakan->id)->update(['tarif' => 99000]);

        $this->assertSame(50000, (int) $baris->refresh()->tarif_satuan);
    }

    public function test_jumlah_tindakan_minimal_satu(): void
    {
        $this->tarif($this->umum, 50000);
        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(TindakanPelayanan::class)->tambah($kunjungan, $this->tindakan->id, 0, User::factory()->create());
    }
}
