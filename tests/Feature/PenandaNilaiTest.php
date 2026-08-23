<?php

namespace Tests\Feature;

use App\Enums\JenisKelamin;
use App\Enums\PenandaHasil;
use App\Models\ParameterLab;
use App\Models\PemeriksaanLab;
use App\Models\RujukanLab;
use App\Services\PenandaNilai;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PenandaNilaiTest extends TestCase
{
    use RefreshDatabase;

    private ParameterLab $hemoglobin;

    protected function setUp(): void
    {
        parent::setUp();

        $pemeriksaan = PemeriksaanLab::factory()->create(['nama' => 'Darah Rutin']);

        $this->hemoglobin = ParameterLab::factory()->create([
            'pemeriksaan_lab_id' => $pemeriksaan->id,
            'kode' => 'HB',
            'nama' => 'Hemoglobin',
            'satuan' => 'g/dL',
        ]);

        // Rentang normal hemoglobin memang berbeda antara laki-laki dan perempuan.
        RujukanLab::factory()->create([
            'parameter_lab_id' => $this->hemoglobin->id,
            'jenis_kelamin' => 'L', 'nilai_min' => 13.0, 'nilai_maks' => 17.0,
        ]);
        RujukanLab::factory()->create([
            'parameter_lab_id' => $this->hemoglobin->id,
            'jenis_kelamin' => 'P', 'nilai_min' => 12.0, 'nilai_maks' => 15.0,
        ]);
    }

    private function penanda(float $nilai, JenisKelamin $jk): ?PenandaHasil
    {
        return app(PenandaNilai::class)->untuk($this->hemoglobin, $nilai, $jk);
    }

    public function test_nilai_di_dalam_rentang_ditandai_normal(): void
    {
        $this->assertSame(PenandaHasil::Normal, $this->penanda(14.0, JenisKelamin::LakiLaki));
    }

    public function test_nilai_di_bawah_rentang_ditandai_rendah(): void
    {
        $this->assertSame(PenandaHasil::Rendah, $this->penanda(11.0, JenisKelamin::LakiLaki));
    }

    public function test_nilai_di_atas_rentang_ditandai_tinggi(): void
    {
        $this->assertSame(PenandaHasil::Tinggi, $this->penanda(18.5, JenisKelamin::LakiLaki));
    }

    public function test_batas_rentang_terhitung_normal(): void
    {
        $this->assertSame(PenandaHasil::Normal, $this->penanda(13.0, JenisKelamin::LakiLaki));
        $this->assertSame(PenandaHasil::Normal, $this->penanda(17.0, JenisKelamin::LakiLaki));
    }

    public function test_nilai_yang_sama_bisa_normal_bagi_pria_tapi_tinggi_bagi_wanita(): void
    {
        // Inilah alasan rujukan dibedakan menurut jenis kelamin. Rujukan tunggal
        // akan menandai salah satu dari keduanya secara keliru.
        $this->assertSame(PenandaHasil::Normal, $this->penanda(16.0, JenisKelamin::LakiLaki));
        $this->assertSame(PenandaHasil::Tinggi, $this->penanda(16.0, JenisKelamin::Perempuan));
    }

    public function test_rujukan_semua_dipakai_bila_tidak_ada_yang_khusus(): void
    {
        $pemeriksaan = PemeriksaanLab::factory()->create(['nama' => 'Kimia Klinik']);
        $glukosa = ParameterLab::factory()->create([
            'pemeriksaan_lab_id' => $pemeriksaan->id,
            'kode' => 'GDS', 'nama' => 'Gula Darah Sewaktu', 'satuan' => 'mg/dL',
        ]);

        RujukanLab::factory()->create([
            'parameter_lab_id' => $glukosa->id,
            'jenis_kelamin' => 'semua', 'nilai_min' => 70, 'nilai_maks' => 140,
        ]);

        $penanda = app(PenandaNilai::class);

        $this->assertSame(PenandaHasil::Normal, $penanda->untuk($glukosa, 100, JenisKelamin::LakiLaki));
        $this->assertSame(PenandaHasil::Tinggi, $penanda->untuk($glukosa, 200, JenisKelamin::Perempuan));
    }

    public function test_rujukan_khusus_didahulukan_dari_rujukan_semua(): void
    {
        // Bila keduanya ada, yang khusus jenis kelamin yang menang.
        RujukanLab::factory()->create([
            'parameter_lab_id' => $this->hemoglobin->id,
            'jenis_kelamin' => 'semua', 'nilai_min' => 0, 'nilai_maks' => 100,
        ]);

        $this->assertSame(PenandaHasil::Tinggi, $this->penanda(16.0, JenisKelamin::Perempuan));
    }

    public function test_parameter_tanpa_rujukan_menghasilkan_penanda_kosong(): void
    {
        $pemeriksaan = PemeriksaanLab::factory()->create();
        $tanpaRujukan = ParameterLab::factory()->create([
            'pemeriksaan_lab_id' => $pemeriksaan->id, 'kode' => 'XX', 'nama' => 'Tanpa Rujukan',
        ]);

        $this->assertNull(app(PenandaNilai::class)->untuk($tanpaRujukan, 5, JenisKelamin::LakiLaki));
    }

    public function test_ketiadaan_rujukan_dicatat_sebagai_peringatan(): void
    {
        $pemeriksaan = PemeriksaanLab::factory()->create();
        $tanpaRujukan = ParameterLab::factory()->create([
            'pemeriksaan_lab_id' => $pemeriksaan->id, 'kode' => 'YY', 'nama' => 'Tanpa Rujukan',
        ]);

        Log::spy();

        app(PenandaNilai::class)->untuk($tanpaRujukan, 5, JenisKelamin::LakiLaki);

        Log::shouldHaveReceived('warning')->once();
    }
}
