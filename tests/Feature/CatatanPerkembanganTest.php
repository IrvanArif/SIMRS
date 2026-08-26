<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\CatatanPerkembangan;
use App\Models\KelasKamar;
use App\Models\Kunjungan;
use App\Models\RawatInap;
use App\Models\User;
use App\Services\CatatanHarian;
use App\Services\PerintahRawatInap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CatatanPerkembanganTest extends TestCase
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

    private function soap(array $ganti = []): array
    {
        return array_merge([
            'subjective' => 'Pasien mengaku mual berkurang.',
            'objective' => 'Tekanan darah 110/70, suhu 36,8.',
            'assessment' => 'Perbaikan klinis.',
            'plan' => 'Lanjutkan cairan, evaluasi besok.',
        ], $ganti);
    }

    public function test_catatan_perkembangan_tersimpan_lengkap(): void
    {
        $rawatInap = $this->rawatInap();
        $dokter = $this->penggunaBerperan(Peran::Dokter->value);

        $catatan = app(CatatanHarian::class)->tulis($rawatInap, $this->soap(), $dokter);

        $this->assertSame('Perbaikan klinis.', $catatan->assessment);
        $this->assertSame($rawatInap->id, $catatan->rawat_inap_id);
        $this->assertNotNull($catatan->waktu);
    }

    public function test_catatan_perkembangan_wajib_soap_lengkap(): void
    {
        $rawatInap = $this->rawatInap();
        $dokter = $this->penggunaBerperan(Peran::Dokter->value);

        foreach (['subjective', 'objective', 'assessment', 'plan'] as $kolom) {
            try {
                app(CatatanHarian::class)->tulis($rawatInap, $this->soap([$kolom => '   ']), $dokter);
                $this->fail("Catatan tanpa {$kolom} seharusnya ditolak.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($kolom, $e->errors());
            }
        }

        $this->assertSame(0, CatatanPerkembangan::count());
    }

    public function test_catatan_mencatat_penulis_dan_perannya(): void
    {
        $perawat = $this->penggunaBerperan(Peran::Perawat->value);

        $catatan = app(CatatanHarian::class)->tulis($this->rawatInap(), $this->soap(), $perawat);

        $this->assertSame($perawat->id, $catatan->ditulis_oleh);
        $this->assertSame('perawat', $catatan->peran_penulis);
    }

    public function test_perawat_dan_dokter_sama_sama_boleh_menulis(): void
    {
        $rawatInap = $this->rawatInap();
        $layanan = app(CatatanHarian::class);

        $layanan->tulis($rawatInap, $this->soap(), $this->penggunaBerperan(Peran::Perawat->value));
        $layanan->tulis($rawatInap, $this->soap(), $this->penggunaBerperan(Peran::Dokter->value));

        // Satu berkas dibaca bersama, bukan dua buku terpisah yang tidak pernah
        // saling bertemu.
        $this->assertSame(
            ['perawat', 'dokter'],
            CatatanPerkembangan::orderBy('id')->pluck('peran_penulis')->all()
        );
    }

    public function test_beberapa_catatan_dalam_satu_masa_rawat_tersimpan_berurutan(): void
    {
        $rawatInap = $this->rawatInap();
        $dokter = $this->penggunaBerperan(Peran::Dokter->value);
        $layanan = app(CatatanHarian::class);

        foreach (['Hari 1', 'Hari 2', 'Hari 3'] as $hari) {
            $layanan->tulis($rawatInap, $this->soap(['assessment' => $hari]), $dokter);
        }

        $this->assertSame(
            ['Hari 1', 'Hari 2', 'Hari 3'],
            $rawatInap->refresh()->catatan->pluck('assessment')->all()
        );
    }

    public function test_catatan_tidak_bisa_ditulis_pada_masa_rawat_yang_sudah_batal(): void
    {
        $rawatInap = $this->rawatInap();
        app(PerintahRawatInap::class)->batalkan($rawatInap, User::factory()->create(), 'Pasien menolak');

        $this->expectException(RuntimeException::class);

        app(CatatanHarian::class)->tulis(
            $rawatInap->refresh(), $this->soap(), $this->penggunaBerperan(Peran::Dokter->value)
        );
    }

    public function test_koreksi_catatan_wajib_beralasan(): void
    {
        $dokter = $this->penggunaBerperan(Peran::Dokter->value);
        $catatan = app(CatatanHarian::class)->tulis($this->rawatInap(), $this->soap(), $dokter);

        $this->expectException(ValidationException::class);

        app(CatatanHarian::class)->koreksi($catatan, $this->soap(['assessment' => 'Memburuk.']), $dokter, '   ');
    }

    public function test_koreksi_mengubah_isi_dan_tercatat_di_audit_log(): void
    {
        $dokter = $this->penggunaBerperan(Peran::Dokter->value);
        $catatan = app(CatatanHarian::class)->tulis($this->rawatInap(), $this->soap(), $dokter);

        app(CatatanHarian::class)->koreksi(
            $catatan, $this->soap(['assessment' => 'Memburuk.']), $dokter, 'Salah menilai kondisi'
        );

        $this->assertSame('Memburuk.', $catatan->refresh()->assessment);
        $this->assertDatabaseHas('audit_logs', ['alasan' => 'Salah menilai kondisi']);
    }

    public function test_koreksi_tidak_mengubah_penulis_aslinya(): void
    {
        $perawat = $this->penggunaBerperan(Peran::Perawat->value);
        $dokter = $this->penggunaBerperan(Peran::Dokter->value);

        $catatan = app(CatatanHarian::class)->tulis($this->rawatInap(), $this->soap(), $perawat);
        app(CatatanHarian::class)->koreksi($catatan, $this->soap(['plan' => 'Ubah terapi.']), $dokter, 'Perbaikan');

        // Catatan klinis adalah pernyataan seseorang. Menimpa namanya saat
        // mengoreksi akan memalsukan siapa yang menyatakannya; siapa mengoreksi
        // tercatat di audit log, bukan di kolom penulis.
        $this->assertSame($perawat->id, $catatan->refresh()->ditulis_oleh);
        $this->assertSame('perawat', $catatan->refresh()->peran_penulis);
    }

    public function test_koreksi_wajib_tetap_memuat_soap_lengkap(): void
    {
        $dokter = $this->penggunaBerperan(Peran::Dokter->value);
        $catatan = app(CatatanHarian::class)->tulis($this->rawatInap(), $this->soap(), $dokter);

        $this->expectException(ValidationException::class);

        app(CatatanHarian::class)->koreksi($catatan, $this->soap(['plan' => '']), $dokter, 'Perbaikan');
    }
}
