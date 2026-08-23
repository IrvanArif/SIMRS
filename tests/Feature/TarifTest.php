<?php

namespace Tests\Feature;

use App\Enums\JenisLayanan;
use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\Penjamin;
use App\Models\Tarif;
use App\Models\Tindakan;
use App\Models\User;
use App\Services\PencariTarif;
use App\Services\TindakanPelayanan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class TarifTest extends TestCase
{
    use RefreshDatabase;

    private Tindakan $tindakan;
    private Obat $obat;
    private Penjamin $umum;
    private Penjamin $bpjs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tindakan = Tindakan::factory()->create(['nama' => 'Konsultasi Dokter Umum']);
        $this->obat = Obat::factory()->create(['nama' => 'Paracetamol 500 mg']);
        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $this->bpjs = Penjamin::factory()->create(['kode' => 'BPJS', 'jenis' => 'penjamin']);
    }

    private function tarif(
        JenisLayanan $jenis,
        int $layananId,
        Penjamin $penjamin,
        int $harga,
        string $berlakuMulai = '2026-01-01'
    ): void {
        Tarif::factory()->create([
            'jenis_layanan' => $jenis,
            'layanan_id' => $layananId,
            'penjamin_id' => $penjamin->id,
            'harga' => $harga,
            'berlaku_mulai' => $berlakuMulai,
        ]);
    }

    public function test_tarif_tindakan_diambil_sesuai_penjamin(): void
    {
        $this->tarif(JenisLayanan::Tindakan, $this->tindakan->id, $this->umum, 50000);
        $this->tarif(JenisLayanan::Tindakan, $this->tindakan->id, $this->bpjs, 35000);

        $this->assertSame(
            35000,
            app(PencariTarif::class)->untuk(JenisLayanan::Tindakan, $this->tindakan->id, $this->bpjs->id)
        );
    }

    public function test_harga_obat_diambil_sesuai_penjamin(): void
    {
        $this->tarif(JenisLayanan::Obat, $this->obat->id, $this->umum, 1500);
        $this->tarif(JenisLayanan::Obat, $this->obat->id, $this->bpjs, 1000);

        $this->assertSame(
            1000,
            app(PencariTarif::class)->untuk(JenisLayanan::Obat, $this->obat->id, $this->bpjs->id)
        );
    }

    public function test_layanan_berbeda_dengan_id_sama_tidak_tertukar(): void
    {
        // Tindakan #1 dan obat #1 adalah dua hal berbeda meski id-nya sama.
        $this->tarif(JenisLayanan::Tindakan, 1, $this->umum, 50000);
        $this->tarif(JenisLayanan::Obat, 1, $this->umum, 1500);

        $pencari = app(PencariTarif::class);

        $this->assertSame(50000, $pencari->untuk(JenisLayanan::Tindakan, 1, $this->umum->id));
        $this->assertSame(1500, $pencari->untuk(JenisLayanan::Obat, 1, $this->umum->id));
    }

    public function test_tarif_jatuh_tempo_ke_umum_bila_penjamin_belum_punya_tarif(): void
    {
        $this->tarif(JenisLayanan::Tindakan, $this->tindakan->id, $this->umum, 50000);

        $this->assertSame(
            50000,
            app(PencariTarif::class)->untuk(JenisLayanan::Tindakan, $this->tindakan->id, $this->bpjs->id)
        );
    }

    public function test_ketiadaan_tarif_penjamin_dicatat_sebagai_peringatan(): void
    {
        $this->tarif(JenisLayanan::Tindakan, $this->tindakan->id, $this->umum, 50000);
        Log::spy();

        app(PencariTarif::class)->untuk(JenisLayanan::Tindakan, $this->tindakan->id, $this->bpjs->id);

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_tarif_terbaru_yang_sudah_berlaku_yang_dipakai(): void
    {
        $this->tarif(JenisLayanan::Tindakan, $this->tindakan->id, $this->umum, 50000, '2026-01-01');
        $this->tarif(JenisLayanan::Tindakan, $this->tindakan->id, $this->umum, 60000, '2026-06-01');

        $pencari = app(PencariTarif::class);

        $this->assertSame(
            50000,
            $pencari->untuk(JenisLayanan::Tindakan, $this->tindakan->id, $this->umum->id, Carbon::parse('2026-03-01'))
        );
        $this->assertSame(
            60000,
            $pencari->untuk(JenisLayanan::Tindakan, $this->tindakan->id, $this->umum->id, Carbon::parse('2026-08-18'))
        );
    }

    public function test_tanpa_tarif_sama_sekali_maka_gagal_dengan_pesan_jelas(): void
    {
        $this->expectException(RuntimeException::class);

        app(PencariTarif::class)->untuk(JenisLayanan::Tindakan, $this->tindakan->id, $this->bpjs->id);
    }

    public function test_tarif_ganda_untuk_kombinasi_yang_sama_ditolak_database(): void
    {
        $this->tarif(JenisLayanan::Tindakan, $this->tindakan->id, $this->umum, 50000);

        $this->expectException(QueryException::class);

        $this->tarif(JenisLayanan::Tindakan, $this->tindakan->id, $this->umum, 60000);
    }

    public function test_tarif_disalin_ke_tindakan_kunjungan(): void
    {
        $this->tarif(JenisLayanan::Tindakan, $this->tindakan->id, $this->umum, 50000);
        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]);

        $baris = app(TindakanPelayanan::class)
            ->tambah($kunjungan, $this->tindakan->id, 1, User::factory()->create());

        $this->assertSame(50000, (int) $baris->tarif_satuan);
    }

    public function test_perubahan_master_tarif_tidak_mengubah_tindakan_yang_sudah_dicatat(): void
    {
        $this->tarif(JenisLayanan::Tindakan, $this->tindakan->id, $this->umum, 50000);
        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]);

        $baris = app(TindakanPelayanan::class)
            ->tambah($kunjungan, $this->tindakan->id, 1, User::factory()->create());

        Tarif::query()->update(['harga' => 99000]);

        $this->assertSame(50000, (int) $baris->refresh()->tarif_satuan);
    }

    public function test_jumlah_tindakan_minimal_satu(): void
    {
        $this->tarif(JenisLayanan::Tindakan, $this->tindakan->id, $this->umum, 50000);
        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(TindakanPelayanan::class)->tambah($kunjungan, $this->tindakan->id, 0, User::factory()->create());
    }
}
