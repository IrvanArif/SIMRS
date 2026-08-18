<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Dokter;
use App\Models\Kunjungan;
use App\Models\Pemeriksaan;
use App\Models\Poli;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HakAksesKlinisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Peran::semua() as $peran) {
            Role::findOrCreate($peran);
        }
    }

    private function penggunaBerperan(string $peran, array $atribut = []): User
    {
        $user = User::factory()->create($atribut);
        $user->assignRole($peran);

        return $user;
    }

    public function test_dokter_boleh_memeriksa_kunjungan_di_polinya(): void
    {
        $kunjungan = Kunjungan::factory()->create();
        $dokter = $this->penggunaBerperan(Peran::Dokter->value, ['dokter_id' => $kunjungan->dokter_id]);

        $this->assertTrue(Gate::forUser($dokter)->allows('periksa', $kunjungan));
    }

    public function test_dokter_tidak_bisa_memeriksa_kunjungan_poli_lain(): void
    {
        $kunjungan = Kunjungan::factory()->create();
        $poliLain = Poli::factory()->create(['kode' => 'GIG']);
        $dokterLain = Dokter::factory()->create(['poli_id' => $poliLain->id]);
        $user = $this->penggunaBerperan(Peran::Dokter->value, ['dokter_id' => $dokterLain->id]);

        $this->assertFalse(Gate::forUser($user)->allows('periksa', $kunjungan));
    }

    public function test_kasir_tidak_bisa_memeriksa_kunjungan(): void
    {
        $kunjungan = Kunjungan::factory()->create();
        $kasir = $this->penggunaBerperan(Peran::Kasir->value);

        $this->assertFalse(Gate::forUser($kasir)->allows('periksa', $kunjungan));
    }

    public function test_admin_tidak_bisa_mengubah_rekam_medis(): void
    {
        $pemeriksaan = Pemeriksaan::factory()->create();
        $admin = $this->penggunaBerperan(Peran::Admin->value);

        $this->assertFalse(Gate::forUser($admin)->allows('ubah', $pemeriksaan));
    }

    public function test_perawat_boleh_mengisi_pemeriksaan_yang_belum_selesai(): void
    {
        $pemeriksaan = Pemeriksaan::factory()->create();
        $perawat = $this->penggunaBerperan(Peran::Perawat->value);

        $this->assertTrue(Gate::forUser($perawat)->allows('ubah', $pemeriksaan));
    }
}
