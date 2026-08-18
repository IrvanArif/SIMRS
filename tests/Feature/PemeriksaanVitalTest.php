<?php

namespace Tests\Feature;

use App\Enums\StatusKunjungan;
use App\Models\Kunjungan;
use App\Models\User;
use App\Services\PemeriksaanKlinis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class PemeriksaanVitalTest extends TestCase
{
    use RefreshDatabase;

    private function vital(array $ganti = []): array
    {
        return array_merge([
            'sistolik' => 120,
            'diastolik' => 80,
            'nadi' => 78,
            'suhu' => 36.7,
            'respirasi' => 18,
            'berat_badan' => 62.5,
            'tinggi_badan' => 165,
            'keluhan_awal' => 'Batuk sejak tiga hari',
        ], $ganti);
    }

    public function test_perawat_mencatat_tanda_vital_dan_status_kunjungan_berubah(): void
    {
        $kunjungan = Kunjungan::factory()->create();
        $perawat = User::factory()->create();

        $pemeriksaan = app(PemeriksaanKlinis::class)->catatVital($kunjungan, $this->vital(), $perawat);

        $this->assertSame(120, $pemeriksaan->sistolik);
        $this->assertSame($perawat->id, $pemeriksaan->dicatat_perawat_id);
        $this->assertNotNull($pemeriksaan->waktu_perawat);
        $this->assertSame(StatusKunjungan::DiperiksaPerawat, $kunjungan->refresh()->status);
    }

    public function test_pencatatan_kedua_memperbarui_baris_yang_sama_bukan_membuat_baru(): void
    {
        $kunjungan = Kunjungan::factory()->create();
        $perawat = User::factory()->create();
        $layanan = app(PemeriksaanKlinis::class);

        $layanan->catatVital($kunjungan, $this->vital(), $perawat);
        $layanan->catatVital($kunjungan, $this->vital(['nadi' => 88]), $perawat);

        $this->assertSame(1, $kunjungan->refresh()->pemeriksaan()->count());
        $this->assertSame(88, $kunjungan->pemeriksaan->nadi);
    }

    public function test_suhu_di_luar_rentang_wajar_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        app(PemeriksaanKlinis::class)->catatVital(
            Kunjungan::factory()->create(),
            $this->vital(['suhu' => 55]),
            User::factory()->create()
        );
    }

    public function test_tekanan_darah_wajib_berupa_angka(): void
    {
        $this->expectException(ValidationException::class);

        app(PemeriksaanKlinis::class)->catatVital(
            Kunjungan::factory()->create(),
            $this->vital(['sistolik' => 'seratus']),
            User::factory()->create()
        );
    }

    public function test_kunjungan_yang_sudah_selesai_tidak_bisa_diisi_vital(): void
    {
        $kunjungan = Kunjungan::factory()->create(['status' => StatusKunjungan::Selesai]);

        $this->expectException(RuntimeException::class);

        app(PemeriksaanKlinis::class)->catatVital($kunjungan, $this->vital(), User::factory()->create());
    }

    public function test_kunjungan_yang_dibatalkan_tidak_bisa_diisi_vital(): void
    {
        $kunjungan = Kunjungan::factory()->create(['status' => StatusKunjungan::Batal]);

        $this->expectException(RuntimeException::class);

        app(PemeriksaanKlinis::class)->catatVital($kunjungan, $this->vital(), User::factory()->create());
    }
}
