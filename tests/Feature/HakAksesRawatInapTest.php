<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Bed;
use App\Models\KelasKamar;
use App\Models\Kunjungan;
use App\Models\RawatInap;
use App\Models\User;
use App\Services\PerintahRawatInap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HakAksesRawatInapTest extends TestCase
{
    use RefreshDatabase;

    private KelasKamar $kelas;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Peran::semua() as $peran) {
            Role::findOrCreate($peran);
        }

        $this->kelas = KelasKamar::factory()->create();
    }

    private function penggunaBerperan(string $peran): User
    {
        $user = User::factory()->create();
        $user->assignRole($peran);

        return $user;
    }

    private function rawatInap(): RawatInap
    {
        return app(PerintahRawatInap::class)->terbitkan(
            Kunjungan::factory()->create(), User::factory()->create(), 'Dehidrasi berat', $this->kelas
        );
    }

    public function test_hanya_dokter_yang_boleh_memerintahkan_rawat_inap(): void
    {
        $this->assertTrue(
            Gate::forUser($this->penggunaBerperan(Peran::Dokter->value))->allows('perintahkan', RawatInap::class)
        );

        foreach ([Peran::Perawat, Peran::Admisi, Peran::Kasir] as $peran) {
            $this->assertFalse(
                Gate::forUser($this->penggunaBerperan($peran->value))->allows('perintahkan', RawatInap::class)
            );
        }
    }

    public function test_hanya_admisi_yang_boleh_menempatkan_pasien_di_bed(): void
    {
        $rawatInap = $this->rawatInap();

        $this->assertTrue(
            Gate::forUser($this->penggunaBerperan(Peran::Admisi->value))->allows('tempatkan', $rawatInap)
        );
        $this->assertFalse(
            Gate::forUser($this->penggunaBerperan(Peran::Perawat->value))->allows('tempatkan', $rawatInap)
        );
    }

    public function test_perawat_dan_dokter_boleh_menulis_catatan_perkembangan(): void
    {
        $rawatInap = $this->rawatInap();

        foreach ([Peran::Perawat, Peran::Dokter] as $peran) {
            $this->assertTrue(
                Gate::forUser($this->penggunaBerperan($peran->value))->allows('rawat', $rawatInap)
            );
        }
    }

    public function test_admisi_tidak_bisa_menulis_catatan_perkembangan(): void
    {
        $this->assertFalse(
            Gate::forUser($this->penggunaBerperan(Peran::Admisi->value))->allows('rawat', $this->rawatInap())
        );
    }

    public function test_perawat_tidak_bisa_memulangkan_pasien(): void
    {
        // Memulangkan berarti menetapkan diagnosa akhir dan cara pulang;
        // keduanya keputusan medis.
        $this->assertFalse(
            Gate::forUser($this->penggunaBerperan(Peran::Perawat->value))->allows('pulangkan', $this->rawatInap())
        );
        $this->assertTrue(
            Gate::forUser($this->penggunaBerperan(Peran::Dokter->value))->allows('pulangkan', $this->rawatInap())
        );
    }

    public function test_masa_rawat_yang_sudah_pulang_tidak_bisa_dirawat_atau_dipulangkan_lagi(): void
    {
        $rawatInap = $this->rawatInap();
        app(PerintahRawatInap::class)->batalkan($rawatInap, User::factory()->create(), 'Pasien menolak');

        $dokter = $this->penggunaBerperan(Peran::Dokter->value);

        $this->assertFalse(Gate::forUser($dokter)->allows('rawat', $rawatInap->refresh()));
        $this->assertFalse(Gate::forUser($dokter)->allows('pulangkan', $rawatInap->refresh()));
    }

    public function test_kasir_boleh_melihat_tetapi_tidak_mengubah(): void
    {
        $rawatInap = $this->rawatInap();
        $kasir = $this->penggunaBerperan(Peran::Kasir->value);

        // Kasir perlu melihat rincian kamar untuk menjelaskan tagihan.
        $this->assertTrue(Gate::forUser($kasir)->allows('lihat', $rawatInap));
        $this->assertFalse(Gate::forUser($kasir)->allows('rawat', $rawatInap));
        $this->assertFalse(Gate::forUser($kasir)->allows('tempatkan', $rawatInap));
    }

    public function test_radiografer_tidak_bisa_menyentuh_rawat_inap(): void
    {
        $rawatInap = $this->rawatInap();
        $radiografer = $this->penggunaBerperan(Peran::Radiografer->value);

        foreach (['rawat', 'tempatkan', 'pulangkan'] as $aksi) {
            $this->assertFalse(Gate::forUser($radiografer)->allows($aksi, $rawatInap));
        }
    }
}
