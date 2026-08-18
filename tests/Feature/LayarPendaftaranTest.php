<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Livewire\Pendaftaran\CariPasien;
use App\Livewire\Pendaftaran\FormKunjungan;
use App\Livewire\Pendaftaran\FormPasien;
use App\Models\Dokter;
use App\Models\Pasien;
use App\Models\Penjamin;
use App\Models\Poli;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LayarPendaftaranTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Peran::semua() as $peran) {
            Role::findOrCreate($peran);
        }
    }

    private function admisi(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Peran::Admisi->value);

        return $user;
    }

    public function test_petugas_admisi_bisa_membuka_layar_pencarian_pasien(): void
    {
        $this->actingAs($this->admisi())
            ->get(route('pendaftaran.pasien'))
            ->assertSuccessful();
    }

    public function test_dokter_tidak_bisa_membuka_layar_pendaftaran(): void
    {
        $dokter = User::factory()->create();
        $dokter->assignRole(Peran::Dokter->value);

        $this->actingAs($dokter)
            ->get(route('pendaftaran.pasien'))
            ->assertForbidden();
    }

    public function test_pendaftaran_pasien_baru_lewat_layar_membuat_pasien_bernomor_rm(): void
    {
        Livewire::actingAs($this->admisi())
            ->test(FormPasien::class)
            ->set('nik', '3202011203900001')
            ->set('nama', 'Siti Aminah')
            ->set('tempat_lahir', 'Kabupaten Sampel')
            ->set('tanggal_lahir', '1990-03-12')
            ->set('jenis_kelamin', 'P')
            ->set('alamat', 'Jl. Melati No. 12')
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('pasien', ['nik' => '3202011203900001', 'no_rm' => '000001']);
    }

    public function test_nik_tidak_sah_menampilkan_pesan_validasi(): void
    {
        Livewire::actingAs($this->admisi())
            ->test(FormPasien::class)
            ->set('nik', '123')
            ->set('nama', 'Siti Aminah')
            ->set('tanggal_lahir', '1990-03-12')
            ->set('jenis_kelamin', 'P')
            ->set('alamat', 'Jl. Melati No. 12')
            ->call('simpan')
            ->assertHasErrors('nik');
    }

    public function test_membuat_kunjungan_lewat_layar_menghasilkan_nomor_antrian(): void
    {
        $pasien = Pasien::factory()->create();
        $poli = Poli::factory()->create(['kode' => 'UMU']);
        $dokter = Dokter::factory()->create(['poli_id' => $poli->id]);
        $umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);

        Livewire::actingAs($this->admisi())
            ->test(FormKunjungan::class, ['pasien' => $pasien])
            ->set('poli_id', $poli->id)
            ->set('dokter_id', $dokter->id)
            ->set('penjamin_id', $umum->id)
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('antrian', ['poli_id' => $poli->id, 'nomor' => 1]);
    }

    public function test_pasien_bpjs_tanpa_nomor_kartu_menampilkan_pesan_validasi(): void
    {
        $pasien = Pasien::factory()->create();
        $poli = Poli::factory()->create(['kode' => 'UMU']);
        $dokter = Dokter::factory()->create(['poli_id' => $poli->id]);
        $bpjs = Penjamin::factory()->create(['kode' => 'BPJS', 'jenis' => 'penjamin']);

        Livewire::actingAs($this->admisi())
            ->test(FormKunjungan::class, ['pasien' => $pasien])
            ->set('poli_id', $poli->id)
            ->set('dokter_id', $dokter->id)
            ->set('penjamin_id', $bpjs->id)
            ->call('simpan')
            ->assertHasErrors('no_kartu_penjamin');
    }

    public function test_layar_ubah_pasien_bisa_dibuka_meski_kolom_opsional_kosong(): void
    {
        $pasien = Pasien::factory()->create(['rt' => null, 'rw' => null, 'no_hp' => null, 'tempat_lahir' => null]);

        $this->actingAs($this->admisi())
            ->get(route('pendaftaran.pasien.ubah', $pasien))
            ->assertSuccessful()
            ->assertSee($pasien->nama);
    }

    public function test_layar_ubah_pasien_memuat_data_yang_sudah_ada(): void
    {
        $pasien = Pasien::factory()->create(['nama' => 'Siti Aminah', 'rt' => '003', 'jenis_kelamin' => 'P']);

        Livewire::actingAs($this->admisi())
            ->test(FormPasien::class, ['pasien' => $pasien])
            ->assertSet('nama', 'Siti Aminah')
            ->assertSet('rt', '003')
            ->assertSet('jenis_kelamin', 'P')
            ->assertSet('tanggal_lahir', $pasien->tanggal_lahir->toDateString());
    }

    public function test_pencarian_pasien_bersifat_partial_dan_tidak_peduli_huruf_besar_kecil(): void
    {
        Pasien::factory()->create(['nama' => 'Siti Aminah']);

        Livewire::actingAs($this->admisi())
            ->test(CariPasien::class)
            ->set('kata', 'AMINAH')
            ->assertSee('Siti Aminah');
    }
}
