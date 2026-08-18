<?php

namespace Tests\Feature;

use App\Models\Antrian;
use App\Models\Dokter;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\Penjamin;
use App\Models\Poli;
use App\Services\PendaftaranKunjungan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AntrianTest extends TestCase
{
    use RefreshDatabase;

    private Poli $poli;
    private Dokter $dokter;
    private Penjamin $umum;

    protected function setUp(): void
    {
        parent::setUp();

        $this->poli = Poli::factory()->create(['kode' => 'UMU', 'nama' => 'Poli Umum']);
        $this->dokter = Dokter::factory()->create(['poli_id' => $this->poli->id]);
        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
    }

    private function daftarkan(array $ganti = []): Kunjungan
    {
        return app(PendaftaranKunjungan::class)->daftarkan(array_merge([
            'pasien_id' => Pasien::factory()->create()->id,
            'poli_id' => $this->poli->id,
            'dokter_id' => $this->dokter->id,
            'penjamin_id' => $this->umum->id,
            'tanggal' => '2026-08-18',
        ], $ganti));
    }

    public function test_antrian_pertama_pada_hari_itu_mendapat_nomor_1(): void
    {
        $this->assertSame(1, $this->daftarkan()->antrian->nomor);
    }

    public function test_nomor_antrian_berurutan_untuk_pasien_berikutnya(): void
    {
        $this->daftarkan();

        $this->assertSame(2, $this->daftarkan()->antrian->nomor);
    }

    public function test_sepuluh_pendaftaran_menghasilkan_sepuluh_nomor_antrian_berbeda(): void
    {
        $nomor = [];

        for ($i = 0; $i < 10; $i++) {
            $nomor[] = $this->daftarkan()->antrian->nomor;
        }

        $this->assertCount(10, array_unique($nomor));
    }

    public function test_nomor_antrian_mulai_dari_1_lagi_pada_hari_berikutnya(): void
    {
        $this->daftarkan(['tanggal' => '2026-08-18']);

        $this->assertSame(1, $this->daftarkan(['tanggal' => '2026-08-19'])->antrian->nomor);
    }

    public function test_setiap_poli_punya_urutan_antrian_sendiri(): void
    {
        $this->daftarkan();

        $poliGigi = Poli::factory()->create(['kode' => 'GIG']);
        $dokterGigi = Dokter::factory()->create(['poli_id' => $poliGigi->id]);

        $kunjungan = $this->daftarkan(['poli_id' => $poliGigi->id, 'dokter_id' => $dokterGigi->id]);

        $this->assertSame(1, $kunjungan->antrian->nomor);
    }

    public function test_database_menolak_dua_antrian_dengan_nomor_sama_pada_poli_dan_tanggal_sama(): void
    {
        $kunjungan = $this->daftarkan();

        $this->expectException(QueryException::class);

        Antrian::create([
            'kunjungan_id' => $kunjungan->id,
            'poli_id' => $this->poli->id,
            'tanggal' => '2026-08-18',
            'nomor' => 1,
        ]);
    }

    public function test_kode_antrian_memakai_kode_poli_dan_tiga_digit(): void
    {
        $this->assertSame('UMU-001', $this->daftarkan()->antrian->kode());
    }

    public function test_antrian_kemarin_tidak_ikut_tampil_di_daftar_hari_ini(): void
    {
        $this->daftarkan(['tanggal' => '2026-08-17']);
        $this->daftarkan(['tanggal' => '2026-08-18']);

        $hariIni = Antrian::whereDate('tanggal', '2026-08-18')->get();

        $this->assertCount(1, $hariIni);
    }
}
