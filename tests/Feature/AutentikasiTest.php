<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Dokter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AutentikasiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Peran::semua() as $peran) {
            Role::findOrCreate($peran);
        }
    }

    public function test_pengguna_terdaftar_bisa_masuk(): void
    {
        $user = User::factory()->create(['password' => bcrypt('rahasia123')]);

        $this->post('/masuk', ['email' => $user->email, 'password' => 'rahasia123'])
            ->assertRedirect('/beranda');

        $this->assertAuthenticatedAs($user);
    }

    public function test_sandi_salah_ditolak_dengan_pesan_bahasa_indonesia(): void
    {
        $user = User::factory()->create(['password' => bcrypt('rahasia123')]);

        $this->from('/masuk')
            ->post('/masuk', ['email' => $user->email, 'password' => 'salah'])
            ->assertRedirect('/masuk')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_tamu_tidak_bisa_membuka_beranda(): void
    {
        $this->get('/beranda')->assertRedirect('/masuk');
    }

    public function test_akun_nonaktif_ditolak_masuk(): void
    {
        $user = User::factory()->create(['password' => bcrypt('rahasia123'), 'aktif' => false]);

        $this->from('/masuk')
            ->post('/masuk', ['email' => $user->email, 'password' => 'rahasia123'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_peran_melekat_pada_pengguna(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Peran::Dokter->value);

        $this->assertTrue($user->hasRole(Peran::Dokter->value));
        $this->assertFalse($user->hasRole(Peran::Kasir->value));
    }

    public function test_pengguna_berperan_dokter_terhubung_ke_data_dokter(): void
    {
        $dokter = Dokter::factory()->create();
        $user = User::factory()->create(['dokter_id' => $dokter->id]);

        $this->assertSame($dokter->id, $user->dokter->id);
    }
}
