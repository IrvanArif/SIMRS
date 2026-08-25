<?php

namespace Tests\Feature;

use App\Enums\JenisLayanan;
use App\Models\PemeriksaanRadiologi;
use App\Models\Penjamin;
use App\Models\Tarif;
use App\Services\PencariTarif;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterRadiologiTest extends TestCase
{
    use RefreshDatabase;

    public function test_pemeriksaan_radiologi_punya_modalitas(): void
    {
        $pemeriksaan = PemeriksaanRadiologi::factory()->create([
            'nama' => 'Rontgen Toraks PA', 'modalitas' => 'rontgen',
        ]);

        $this->assertSame('rontgen', $pemeriksaan->modalitas);
        $this->assertTrue($pemeriksaan->aktif);
    }

    public function test_kode_pemeriksaan_ganda_ditolak_database(): void
    {
        PemeriksaanRadiologi::factory()->create(['kode' => 'RAD001']);

        $this->expectException(QueryException::class);

        PemeriksaanRadiologi::factory()->create(['kode' => 'RAD001']);
    }

    public function test_tarif_radiologi_memakai_tabel_tarif_yang_sama(): void
    {
        $pemeriksaan = PemeriksaanRadiologi::factory()->create();
        $umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Radiologi,
            'layanan_id' => $pemeriksaan->id,
            'penjamin_id' => $umum->id,
            'harga' => 150000,
            'berlaku_mulai' => '2026-01-01',
        ]);

        $this->assertSame(
            150000,
            app(PencariTarif::class)->untuk(JenisLayanan::Radiologi, $pemeriksaan->id, $umum->id)
        );
    }

    public function test_nama_layanan_pada_tarif_menampilkan_nama_pemeriksaan(): void
    {
        $pemeriksaan = PemeriksaanRadiologi::factory()->create(['nama' => 'USG Abdomen']);
        $umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);

        $tarif = Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Radiologi,
            'layanan_id' => $pemeriksaan->id,
            'penjamin_id' => $umum->id,
            'harga' => 200000,
            'berlaku_mulai' => '2026-01-01',
        ]);

        $this->assertSame('USG Abdomen', $tarif->namaLayanan());
    }
}
