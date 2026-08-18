<?php

namespace Tests\Feature;

use App\Models\Antrian;
use App\Models\Kunjungan;
use App\Models\Pasien;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisplayAntrianTest extends TestCase
{
    use RefreshDatabase;

    private function antrianHariIni(string $namaPasien = 'Siti Aminah'): Antrian
    {
        $pasien = Pasien::factory()->create(['nama' => $namaPasien, 'nik' => '3202011203900001']);
        $kunjungan = Kunjungan::factory()->create(['pasien_id' => $pasien->id, 'tanggal' => today()]);

        return Antrian::factory()->create([
            'kunjungan_id' => $kunjungan->id,
            'poli_id' => $kunjungan->poli_id,
            'tanggal' => today(),
            'nomor' => 1,
        ]);
    }

    public function test_display_antrian_bisa_diakses_tanpa_login(): void
    {
        $this->get('/display/antrian')->assertSuccessful();
    }

    public function test_display_antrian_tidak_menampilkan_nama_pasien(): void
    {
        $this->antrianHariIni('Siti Aminah');

        $this->get('/display/antrian')->assertDontSee('Siti Aminah');
    }

    public function test_endpoint_api_mengembalikan_nomor_poli_dan_dokter(): void
    {
        $antrian = $this->antrianHariIni();

        $this->getJson('/api/antrian')
            ->assertSuccessful()
            ->assertJsonPath('data.0.nomor', 1)
            ->assertJsonPath('data.0.kode', $antrian->kode())
            ->assertJsonStructure(['data' => [['nomor', 'kode', 'poli', 'dokter', 'status']]]);
    }

    public function test_endpoint_api_tidak_memuat_data_pasien(): void
    {
        $this->antrianHariIni('Siti Aminah');

        $respons = $this->getJson('/api/antrian')->getContent();

        $this->assertStringNotContainsString('Siti Aminah', $respons);
        $this->assertStringNotContainsString('3202011203900001', $respons);
    }

    public function test_hanya_antrian_hari_ini_yang_ditampilkan(): void
    {
        $kemarin = $this->antrianHariIni();
        $kemarin->update(['tanggal' => today()->subDay()]);

        $this->getJson('/api/antrian')->assertJsonCount(0, 'data');
    }
}
