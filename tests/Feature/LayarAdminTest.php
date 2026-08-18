<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Livewire\Admin\KelolaUser;
use App\Livewire\Admin\PenampilAuditLog;
use App\Livewire\Master\DaftarPoli;
use App\Models\Pasien;
use App\Models\Poli;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LayarAdminTest extends TestCase
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

    private function admin(): User
    {
        return $this->penggunaBerperan(Peran::Admin->value);
    }

    public function test_admin_bisa_menambah_poli(): void
    {
        Livewire::actingAs($this->admin())
            ->test(DaftarPoli::class)
            ->set('kode', 'ANK')->set('nama', 'Poli Anak')->set('lokasi', 'Lantai 2')
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('poli', ['kode' => 'ANK', 'nama' => 'Poli Anak']);
    }

    public function test_kode_poli_ganda_ditolak(): void
    {
        Poli::factory()->create(['kode' => 'ANK']);

        Livewire::actingAs($this->admin())
            ->test(DaftarPoli::class)
            ->set('kode', 'ANK')->set('nama', 'Poli Anak Duplikat')
            ->call('simpan')
            ->assertHasErrors('kode');
    }

    public function test_admin_bisa_membuat_pengguna_dengan_peran(): void
    {
        Livewire::actingAs($this->admin())
            ->test(KelolaUser::class)
            ->set('name', 'Kasir Satu')->set('email', 'kasir1@rs.test')
            ->set('password', 'rahasia123')->set('peran', Peran::Kasir->value)
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertTrue(User::where('email', 'kasir1@rs.test')->first()->hasRole(Peran::Kasir->value));
    }

    public function test_penampil_audit_log_menunjukkan_perubahan_beserta_pelakunya(): void
    {
        $petugas = $this->penggunaBerperan(Peran::Admisi->value, ['name' => 'Petugas Admisi']);
        $this->actingAs($petugas);
        $pasien = Pasien::factory()->create(['nama' => 'Pasien Uji']);
        $pasien->update(['nama' => 'Pasien Uji Diperbarui']);

        Livewire::actingAs($this->admin())
            ->test(PenampilAuditLog::class)
            ->assertSee('Petugas Admisi')
            ->assertSee('update');
    }

    public function test_kasir_tidak_bisa_membuka_audit_log(): void
    {
        $this->actingAs($this->penggunaBerperan(Peran::Kasir->value))
            ->get(route('admin.audit'))
            ->assertForbidden();
    }
}
