<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Livewire\RekamMedis\KoreksiPasien;
use App\Livewire\RekamMedis\PenelusuranRekamMedis;
use App\Livewire\RekamMedis\RekapKunjunganHarian;
use App\Models\AuditLog;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LayarRekamMedisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Peran::semua() as $peran) {
            Role::findOrCreate($peran);
        }
    }

    private function petugasRekamMedis(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Peran::RekamMedis->value);

        return $user;
    }

    public function test_petugas_bisa_menelusuri_rekam_medis_berdasarkan_nomor_rm(): void
    {
        $pasien = Pasien::factory()->create(['nama' => 'Siti Aminah']);

        Livewire::actingAs($this->petugasRekamMedis())
            ->test(PenelusuranRekamMedis::class)
            ->set('kata', $pasien->no_rm)
            ->assertSee('Siti Aminah');
    }

    public function test_koreksi_data_pasien_wajib_menyertakan_alasan(): void
    {
        $pasien = Pasien::factory()->create();

        Livewire::actingAs($this->petugasRekamMedis())
            ->test(KoreksiPasien::class, ['pasien' => $pasien])
            ->set('nama', 'Nama Terkoreksi')
            ->set('alasan', '')
            ->call('simpan')
            ->assertHasErrors('alasan');
    }

    public function test_koreksi_data_pasien_tercatat_beserta_alasannya(): void
    {
        $pasien = Pasien::factory()->create(['nama' => 'Nama Salah Ketik']);

        Livewire::actingAs($this->petugasRekamMedis())
            ->test(KoreksiPasien::class, ['pasien' => $pasien])
            ->set('nama', 'Nama Benar')
            ->set('alasan', 'Salah ketik saat pendaftaran')
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertSame('Nama Benar', $pasien->refresh()->nama);
        $this->assertSame(
            'Salah ketik saat pendaftaran',
            AuditLog::where('aksi', 'update')->latest('id')->first()->alasan
        );
    }

    public function test_rekap_kunjungan_harian_hanya_menghitung_hari_yang_dipilih(): void
    {
        Kunjungan::factory()->create(['tanggal' => '2026-08-18']);
        Kunjungan::factory()->create(['tanggal' => '2026-08-18']);
        Kunjungan::factory()->create(['tanggal' => '2026-08-17']);

        Livewire::actingAs($this->petugasRekamMedis())
            ->test(RekapKunjunganHarian::class)
            ->set('tanggal', '2026-08-18')
            ->assertSet('jumlahKunjungan', 2);
    }

    public function test_kasir_tidak_bisa_membuka_penelusuran_rekam_medis(): void
    {
        $kasir = User::factory()->create();
        $kasir->assignRole(Peran::Kasir->value);

        $this->actingAs($kasir)
            ->get(route('rekam-medis.telusur'))
            ->assertForbidden();
    }
}
