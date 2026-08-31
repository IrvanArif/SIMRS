<?php

namespace Tests\Feature;

use App\Models\Icd9;
use App\Models\Tindakan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterIcd9Test extends TestCase
{
    use RefreshDatabase;

    public function test_icd9_menyimpan_kode_dan_nama(): void
    {
        $icd9 = Icd9::factory()->create(['kode' => '89.52', 'nama' => 'Elektrokardiogram']);

        $this->assertSame('89.52', $icd9->kode);
        $this->assertSame('Elektrokardiogram', $icd9->nama);
    }

    public function test_kode_icd9_ganda_ditolak_database(): void
    {
        Icd9::factory()->create(['kode' => '89.52']);

        $this->expectException(QueryException::class);

        Icd9::factory()->create(['kode' => '89.52']);
    }

    public function test_tindakan_bisa_dipetakan_ke_icd9(): void
    {
        $icd9 = Icd9::factory()->create(['kode' => '93.54', 'nama' => 'Pemasangan bidai']);
        $tindakan = Tindakan::factory()->create(['nama' => 'Pemasangan Bidai', 'icd9_id' => $icd9->id]);

        $this->assertSame('93.54', $tindakan->icd9->kode);
    }

    public function test_tindakan_tanpa_pemetaan_tetap_sah(): void
    {
        // Tidak semua tindakan punya padanan ICD-9-CM, dan yang tidak punya
        // tidak boleh menggagalkan klaim (aturan 88).
        $tindakan = Tindakan::factory()->create(['icd9_id' => null]);

        $this->assertNull($tindakan->icd9);
        $this->assertNotNull($tindakan->id);
    }
}
