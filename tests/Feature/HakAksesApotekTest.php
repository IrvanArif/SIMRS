<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\Pemeriksaan;
use App\Models\Resep;
use App\Models\Tagihan;
use App\Models\User;
use App\Services\PenulisanResep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HakAksesApotekTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Peran::semua() as $peran) {
            Role::findOrCreate($peran);
        }
    }

    private function penggunaBerperan(string $peran): User
    {
        $user = User::factory()->create();
        $user->assignRole($peran);

        return $user;
    }

    private function resep(): Resep
    {
        return app(PenulisanResep::class)->tulis(
            Kunjungan::factory()->create(),
            [['obat_id' => Obat::factory()->create()->id, 'jumlah' => 5, 'aturan_pakai' => '2x1']],
            User::factory()->create()
        );
    }

    public function test_apoteker_boleh_menyiapkan_dan_menyerahkan_resep(): void
    {
        $resep = $this->resep();
        $apoteker = $this->penggunaBerperan(Peran::Apoteker->value);

        $this->assertTrue(Gate::forUser($apoteker)->allows('siapkan', $resep));
        $this->assertTrue(Gate::forUser($apoteker)->allows('serahkan', $resep));
    }

    public function test_dokter_tidak_bisa_menyiapkan_resep(): void
    {
        $resep = $this->resep();
        $dokter = $this->penggunaBerperan(Peran::Dokter->value);

        $this->assertFalse(Gate::forUser($dokter)->allows('siapkan', $resep));
    }

    public function test_kasir_tidak_bisa_menyerahkan_obat(): void
    {
        $resep = $this->resep();
        $kasir = $this->penggunaBerperan(Peran::Kasir->value);

        $this->assertFalse(Gate::forUser($kasir)->allows('serahkan', $resep));
    }

    public function test_apoteker_tidak_bisa_mengubah_rekam_medis(): void
    {
        $pemeriksaan = Pemeriksaan::factory()->create();
        $apoteker = $this->penggunaBerperan(Peran::Apoteker->value);

        $this->assertFalse(Gate::forUser($apoteker)->allows('ubah', $pemeriksaan));
    }

    public function test_apoteker_tidak_bisa_memeriksa_kunjungan(): void
    {
        $kunjungan = Kunjungan::factory()->create();
        $apoteker = $this->penggunaBerperan(Peran::Apoteker->value);

        $this->assertFalse(Gate::forUser($apoteker)->allows('periksa', $kunjungan));
    }

    public function test_apoteker_tidak_bisa_memproses_pembayaran(): void
    {
        $tagihan = Tagihan::factory()->create();
        $apoteker = $this->penggunaBerperan(Peran::Apoteker->value);

        $this->assertFalse(Gate::forUser($apoteker)->allows('proses', $tagihan));
    }

    public function test_hanya_apoteker_yang_boleh_mengelola_stok(): void
    {
        $this->assertTrue(Gate::forUser($this->penggunaBerperan(Peran::Apoteker->value))->allows('kelolaStok', Resep::class));
        $this->assertFalse(Gate::forUser($this->penggunaBerperan(Peran::Perawat->value))->allows('kelolaStok', Resep::class));
    }
}
