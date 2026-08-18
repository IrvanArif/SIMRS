<?php

namespace Tests\Feature;

use App\Models\Dokter;
use App\Models\Penjamin;
use App\Models\TarifTindakan;
use App\Models\Tindakan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_dokter_terhubung_ke_poli_tempatnya_bertugas(): void
    {
        $dokter = Dokter::factory()->create();

        $this->assertNotNull($dokter->poli);
        $this->assertSame($dokter->poli_id, $dokter->poli->id);
    }

    public function test_penjamin_berjenis_penjamin_dianggap_menanggung_biaya(): void
    {
        $bpjs = Penjamin::factory()->create(['kode' => 'BPJS', 'jenis' => 'penjamin']);
        $umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);

        $this->assertTrue($bpjs->ditanggung());
        $this->assertFalse($umum->ditanggung());
    }

    public function test_satu_tindakan_bisa_punya_tarif_berbeda_per_penjamin(): void
    {
        $tindakan = Tindakan::factory()->create();
        $umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $bpjs = Penjamin::factory()->create(['kode' => 'BPJS', 'jenis' => 'penjamin']);

        TarifTindakan::factory()->create([
            'tindakan_id' => $tindakan->id, 'penjamin_id' => $umum->id, 'tarif' => 50000,
        ]);
        TarifTindakan::factory()->create([
            'tindakan_id' => $tindakan->id, 'penjamin_id' => $bpjs->id, 'tarif' => 35000,
        ]);

        $this->assertSame(2, $tindakan->tarif()->count());
    }

    public function test_tarif_ganda_untuk_penjamin_dan_tanggal_berlaku_sama_ditolak_database(): void
    {
        $tindakan = Tindakan::factory()->create();
        $penjamin = Penjamin::factory()->create();

        TarifTindakan::factory()->create([
            'tindakan_id' => $tindakan->id,
            'penjamin_id' => $penjamin->id,
            'berlaku_mulai' => '2026-01-01',
        ]);

        $this->expectException(QueryException::class);

        TarifTindakan::factory()->create([
            'tindakan_id' => $tindakan->id,
            'penjamin_id' => $penjamin->id,
            'berlaku_mulai' => '2026-01-01',
        ]);
    }
}
