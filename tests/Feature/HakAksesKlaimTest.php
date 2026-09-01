<?php

namespace Tests\Feature;

use App\Enums\JenisPelayanan;
use App\Enums\Peran;
use App\Enums\StatusBerkasKlaim;
use App\Models\BerkasKlaim;
use App\Models\Kunjungan;
use App\Models\Penjamin;
use App\Models\Sep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HakAksesKlaimTest extends TestCase
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

    private function sep(): Sep
    {
        $bpjs = Penjamin::firstOrCreate(
            ['kode' => 'BPJS'],
            ['nama' => 'BPJS Kesehatan', 'jenis' => 'penjamin', 'aktif' => true]
        );

        return Sep::create([
            'no_sep' => 'SEP-'.fake()->unique()->numerify('########'),
            'kunjungan_id' => Kunjungan::factory()->create(['penjamin_id' => $bpjs->id])->id,
            'no_kartu' => '0001234567890',
            'jenis_pelayanan' => JenisPelayanan::RawatJalan,
            'diagnosa_awal' => 'Faringitis',
            'tanggal' => now()->toDateString(),
            'status' => Sep::BERLAKU,
        ]);
    }

    private function berkas(StatusBerkasKlaim $status = StatusBerkasKlaim::Draf): BerkasKlaim
    {
        $sep = $this->sep();

        return BerkasKlaim::create([
            'no_berkas' => 'KL-'.fake()->unique()->numerify('########'),
            'kunjungan_id' => $sep->kunjungan_id,
            'sep_id' => $sep->id,
            'no_kartu' => $sep->no_kartu,
            'nama_peserta' => 'Budi Santoso',
            'jenis_pelayanan' => JenisPelayanan::RawatJalan,
            'tanggal_masuk' => now()->toDateString(),
            'total_biaya' => 50000,
            'status' => $status,
        ]);
    }

    public function test_hanya_admisi_yang_boleh_menerbitkan_sep(): void
    {
        $this->assertTrue(
            Gate::forUser($this->penggunaBerperan(Peran::Admisi->value))->allows('terbitkan', Sep::class)
        );

        foreach ([Peran::RekamMedis, Peran::Kasir, Peran::Dokter, Peran::Perawat] as $peran) {
            $this->assertFalse(
                Gate::forUser($this->penggunaBerperan($peran->value))->allows('terbitkan', Sep::class)
            );
        }
    }

    public function test_sep_yang_sudah_batal_tidak_bisa_dibatalkan_lagi(): void
    {
        $sep = $this->sep();
        $admisi = $this->penggunaBerperan(Peran::Admisi->value);

        $this->assertTrue(Gate::forUser($admisi)->allows('batalkan', $sep));

        $sep->update(['status' => Sep::BATAL]);

        $this->assertFalse(Gate::forUser($admisi)->allows('batalkan', $sep->refresh()));
    }

    public function test_hanya_rekam_medis_yang_boleh_menyusun_klaim(): void
    {
        $this->assertTrue(
            Gate::forUser($this->penggunaBerperan(Peran::RekamMedis->value))->allows('susun', BerkasKlaim::class)
        );

        foreach ([Peran::Kasir, Peran::Admisi, Peran::Dokter] as $peran) {
            $this->assertFalse(
                Gate::forUser($this->penggunaBerperan($peran->value))->allows('susun', BerkasKlaim::class)
            );
        }
    }

    public function test_kasir_tidak_bisa_mengajukan_klaim(): void
    {
        $this->assertFalse(
            Gate::forUser($this->penggunaBerperan(Peran::Kasir->value))->allows('ajukan', $this->berkas())
        );
    }

    public function test_kasir_boleh_melihat_klaim_untuk_menjelaskan_tagihan(): void
    {
        $this->assertTrue(
            Gate::forUser($this->penggunaBerperan(Peran::Kasir->value))->allows('lihat', BerkasKlaim::class)
        );
    }

    public function test_berkas_yang_sudah_diajukan_tidak_bisa_diajukan_lagi(): void
    {
        $rekamMedis = $this->penggunaBerperan(Peran::RekamMedis->value);

        $this->assertTrue(Gate::forUser($rekamMedis)->allows('ajukan', $this->berkas()));
        $this->assertFalse(
            Gate::forUser($rekamMedis)->allows('ajukan', $this->berkas(StatusBerkasKlaim::Diajukan))
        );
    }

    public function test_hasil_verifikasi_hanya_pada_berkas_yang_sudah_diajukan(): void
    {
        $rekamMedis = $this->penggunaBerperan(Peran::RekamMedis->value);

        $this->assertFalse(Gate::forUser($rekamMedis)->allows('verifikasi', $this->berkas()));
        $this->assertTrue(
            Gate::forUser($rekamMedis)->allows('verifikasi', $this->berkas(StatusBerkasKlaim::Diajukan))
        );
    }

    public function test_apoteker_dan_analis_tidak_bisa_menyentuh_klaim(): void
    {
        foreach ([Peran::Apoteker, Peran::Analis, Peran::Radiografer] as $peran) {
            $user = $this->penggunaBerperan($peran->value);

            $this->assertFalse(Gate::forUser($user)->allows('lihat', BerkasKlaim::class));
            $this->assertFalse(Gate::forUser($user)->allows('terbitkan', Sep::class));
        }
    }
}
