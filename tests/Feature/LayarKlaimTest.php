<?php

namespace Tests\Feature;

use App\Enums\JenisDiagnosa;
use App\Enums\JenisLayanan;
use App\Enums\Peran;
use App\Enums\StatusBerkasKlaim;
use App\Livewire\Klaim\DaftarBerkas;
use App\Livewire\Klaim\DaftarSep;
use App\Models\BerkasKlaim;
use App\Models\Icd10;
use App\Models\Icd9;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\Penjamin;
use App\Models\Sep;
use App\Models\Tarif;
use App\Models\Tindakan;
use App\Models\User;
use App\Services\PemeriksaanKlinis;
use App\Services\PenerbitanSep;
use App\Services\PenyusunBerkasKlaim;
use App\Services\TindakanPelayanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LayarKlaimTest extends TestCase
{
    use RefreshDatabase;

    private Penjamin $bpjs;

    private Tindakan $infus;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Peran::semua() as $peran) {
            Role::findOrCreate($peran);
        }

        $this->bpjs = Penjamin::factory()->create([
            'kode' => 'BPJS', 'nama' => 'BPJS Kesehatan', 'jenis' => 'penjamin',
        ]);

        $icd9 = Icd9::factory()->create(['kode' => '38.93', 'nama' => 'Pemasangan infus']);
        $this->infus = Tindakan::factory()->create(['nama' => 'Pemasangan Infus', 'icd9_id' => $icd9->id]);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Tindakan, 'layanan_id' => $this->infus->id,
            'penjamin_id' => $this->bpjs->id, 'harga' => 75000, 'berlaku_mulai' => '2026-01-01',
        ]);
    }

    private function penggunaBerperan(string $peran): User
    {
        $user = User::factory()->create();
        $user->assignRole($peran);

        return $user;
    }

    private function kunjunganBpjs(string $nama = 'Budi Santoso'): Kunjungan
    {
        return Kunjungan::factory()->create([
            'pasien_id' => Pasien::factory()->create(['nama' => $nama])->id,
            'penjamin_id' => $this->bpjs->id,
            'no_kartu_penjamin' => '0001234567890',
        ]);
    }

    private function kunjunganSelesai(string $nama = 'Budi Santoso'): Kunjungan
    {
        $kunjungan = $this->kunjunganBpjs($nama);
        $dokter = User::factory()->create(['dokter_id' => $kunjungan->dokter_id]);

        app(PenerbitanSep::class)->terbitkan($kunjungan, $this->penggunaBerperan(Peran::Admisi->value), 'Demam tifoid');
        app(TindakanPelayanan::class)->tambah($kunjungan, $this->infus->id, 1, $dokter);

        $klinis = app(PemeriksaanKlinis::class);
        $klinis->catatSoap($kunjungan, ['subjective' => 'a', 'objective' => 'b', 'assessment' => 'c', 'plan' => 'd'], $dokter);
        $klinis->tambahDiagnosa($kunjungan, Icd10::firstOrCreate(['kode' => 'A01.0'], ['nama_id' => 'Demam tifoid'])->id, JenisDiagnosa::Primer);
        $klinis->selesaikan($kunjungan->refresh(), $dokter);

        return $kunjungan->refresh();
    }

    public function test_daftar_sep_menampilkan_kunjungan_bpjs_yang_belum_ber_sep(): void
    {
        $kunjungan = $this->kunjunganBpjs('Siti Aminah');

        Livewire::actingAs($this->penggunaBerperan(Peran::Admisi->value))
            ->test(DaftarSep::class)
            ->assertSee('Siti Aminah')
            ->assertSee($kunjungan->no_kunjungan);
    }

    public function test_pasien_umum_tidak_muncul_di_daftar_menunggu_sep(): void
    {
        $umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $kunjungan = Kunjungan::factory()->create([
            'pasien_id' => Pasien::factory()->create(['nama' => 'Pasien Tunai'])->id,
            'penjamin_id' => $umum->id,
        ]);

        Livewire::actingAs($this->penggunaBerperan(Peran::Admisi->value))
            ->test(DaftarSep::class)
            ->assertDontSee($kunjungan->no_kunjungan);
    }

    public function test_admisi_menerbitkan_sep_lewat_layar(): void
    {
        $kunjungan = $this->kunjunganBpjs();

        Livewire::actingAs($this->penggunaBerperan(Peran::Admisi->value))
            ->test(DaftarSep::class)
            ->set('kunjungan_id', $kunjungan->id)
            ->set('diagnosa_awal', 'Demam tifoid')
            ->call('terbitkan')
            ->assertHasNoErrors();

        $this->assertNotNull($kunjungan->refresh()->sepBerlaku());
    }

    public function test_sep_tanpa_diagnosa_awal_menampilkan_pesan_di_layar(): void
    {
        $kunjungan = $this->kunjunganBpjs();

        Livewire::actingAs($this->penggunaBerperan(Peran::Admisi->value))
            ->test(DaftarSep::class)
            ->set('kunjungan_id', $kunjungan->id)
            ->set('diagnosa_awal', '')
            ->call('terbitkan')
            ->assertHasErrors('diagnosa_awal');

        $this->assertNull($kunjungan->refresh()->sepBerlaku());
    }

    public function test_pembatalan_sep_tanpa_alasan_menampilkan_pesan_di_layar(): void
    {
        $kunjungan = $this->kunjunganBpjs();
        $sep = app(PenerbitanSep::class)
            ->terbitkan($kunjungan, $this->penggunaBerperan(Peran::Admisi->value), 'Demam tifoid');

        Livewire::actingAs($this->penggunaBerperan(Peran::Admisi->value))
            ->test(DaftarSep::class)
            ->set('sep_id', $sep->id)
            ->set('alasanBatal', '')
            ->call('batalkan')
            ->assertHasErrors('alasan');

        $this->assertTrue($sep->refresh()->berlaku());
    }

    public function test_rekam_medis_menyusun_klaim_lewat_layar(): void
    {
        $kunjungan = $this->kunjunganSelesai();

        Livewire::actingAs($this->penggunaBerperan(Peran::RekamMedis->value))
            ->test(DaftarBerkas::class)
            ->call('susun', $kunjungan->id)
            ->assertHasNoErrors();

        $this->assertSame(1, BerkasKlaim::count());
        $this->assertSame(StatusBerkasKlaim::Draf, BerkasKlaim::first()->status);
    }

    public function test_berkas_kurang_lengkap_menampilkan_daftar_kekurangan_di_layar(): void
    {
        // Kunjungan selesai tetapi tanpa SEP dan tanpa diagnosa primer.
        $kunjungan = $this->kunjunganBpjs('Kurang Lengkap');
        $dokter = User::factory()->create(['dokter_id' => $kunjungan->dokter_id]);

        app(TindakanPelayanan::class)->tambah($kunjungan, $this->infus->id, 1, $dokter);
        app(PemeriksaanKlinis::class)->catatSoap($kunjungan, [
            'subjective' => 'a', 'objective' => 'b', 'assessment' => 'c', 'plan' => 'd',
        ], $dokter);
        $kunjungan->update(['status' => \App\Enums\StatusKunjungan::Selesai]);

        Livewire::actingAs($this->penggunaBerperan(Peran::RekamMedis->value))
            ->test(DaftarBerkas::class)
            ->call('susun', $kunjungan->id)
            ->assertHasErrors('berkas');

        $this->assertSame(0, BerkasKlaim::count());
    }

    public function test_rekam_medis_mengajukan_dan_memverifikasi_lewat_layar(): void
    {
        $kunjungan = $this->kunjunganSelesai();
        $rekamMedis = $this->penggunaBerperan(Peran::RekamMedis->value);
        $berkas = app(PenyusunBerkasKlaim::class)->susun($kunjungan, $rekamMedis);

        Livewire::actingAs($rekamMedis)
            ->test(DaftarBerkas::class)
            ->call('ajukan', $berkas->id)
            ->assertHasNoErrors();

        $this->assertSame(StatusBerkasKlaim::Diajukan, $berkas->refresh()->status);

        Livewire::actingAs($rekamMedis)
            ->test(DaftarBerkas::class)
            ->set('berkas_id', $berkas->id)
            ->set('hasil', StatusBerkasKlaim::Disetujui->value)
            ->call('tandaiHasil')
            ->assertHasNoErrors();

        $this->assertSame(StatusBerkasKlaim::Disetujui, $berkas->refresh()->status);
    }

    public function test_penolakan_tanpa_catatan_menampilkan_pesan_di_layar(): void
    {
        $rekamMedis = $this->penggunaBerperan(Peran::RekamMedis->value);
        $berkas = app(PenyusunBerkasKlaim::class)->susun($this->kunjunganSelesai(), $rekamMedis);
        app(PenyusunBerkasKlaim::class)->ajukan($berkas, $rekamMedis);

        Livewire::actingAs($rekamMedis)
            ->test(DaftarBerkas::class)
            ->set('berkas_id', $berkas->id)
            ->set('hasil', StatusBerkasKlaim::Ditolak->value)
            ->set('catatanVerifikasi', '')
            ->call('tandaiHasil')
            ->assertHasErrors('catatan_verifikasi');

        $this->assertSame(StatusBerkasKlaim::Diajukan, $berkas->refresh()->status);
    }

    public function test_ekspor_csv_bisa_diunduh_rekam_medis(): void
    {
        $rekamMedis = $this->penggunaBerperan(Peran::RekamMedis->value);
        $berkas = app(PenyusunBerkasKlaim::class)->susun($this->kunjunganSelesai(), $rekamMedis);
        app(PenyusunBerkasKlaim::class)->ajukan($berkas, $rekamMedis);

        $balasan = $this->actingAs($rekamMedis)->get(route('klaim.ekspor'));

        $balasan->assertOk();
        $this->assertStringContainsString('text/csv', $balasan->headers->get('content-type'));
        $this->assertStringContainsString('Budi Santoso', $balasan->streamedContent());
    }

    public function test_kasir_tidak_bisa_mengunduh_ekspor_klaim(): void
    {
        $this->actingAs($this->penggunaBerperan(Peran::Kasir->value))
            ->get(route('klaim.ekspor'))
            ->assertForbidden();
    }

    public function test_apoteker_tidak_bisa_membuka_layar_klaim(): void
    {
        foreach (['klaim.sep', 'klaim.berkas'] as $rute) {
            $this->actingAs($this->penggunaBerperan(Peran::Apoteker->value))
                ->get(route($rute))
                ->assertForbidden();
        }
    }

    public function test_admisi_bisa_membuka_daftar_sep(): void
    {
        $this->actingAs($this->penggunaBerperan(Peran::Admisi->value))
            ->get(route('klaim.sep'))
            ->assertOk();
    }
}
