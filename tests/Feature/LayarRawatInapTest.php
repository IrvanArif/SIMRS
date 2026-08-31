<?php

namespace Tests\Feature;

use App\Enums\CaraPulang;
use App\Enums\JenisDiagnosa;
use App\Enums\JenisLayanan;
use App\Enums\Peran;
use App\Enums\StatusRawatInap;
use App\Livewire\Poli\FormSoap;
use App\Livewire\RawatInap\LayarPemulangan;
use App\Livewire\RawatInap\LayarPenempatan;
use App\Livewire\RawatInap\LayarPerawatan;
use App\Livewire\RawatInap\PapanBed;
use App\Models\Bed;
use App\Models\CatatanPerkembangan;
use App\Models\Icd10;
use App\Models\KelasKamar;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\Penjamin;
use App\Models\RawatInap;
use App\Models\Ruang;
use App\Models\Tarif;
use App\Models\Tindakan;
use App\Models\User;
use App\Services\PemeriksaanKlinis;
use App\Services\PenempatanBed;
use App\Services\PerintahRawatInap;
use App\Services\TindakanPelayanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LayarRawatInapTest extends TestCase
{
    use RefreshDatabase;

    private Penjamin $umum;

    private KelasKamar $kelas;

    private Ruang $ruang;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Peran::semua() as $peran) {
            Role::findOrCreate($peran);
        }

        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $this->ruang = Ruang::factory()->create(['nama' => 'Melati']);
        $this->kelas = KelasKamar::factory()->create(['nama' => 'Kelas 2']);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Kamar, 'layanan_id' => $this->kelas->id,
            'penjamin_id' => $this->umum->id, 'harga' => 300000, 'berlaku_mulai' => '2026-01-01',
        ]);
    }

    private function penggunaBerperan(string $peran, array $atribut = []): User
    {
        $user = User::factory()->create($atribut);
        $user->assignRole($peran);

        return $user;
    }

    private function bed(string $nomor = '01'): Bed
    {
        return Bed::factory()->create([
            'ruang_id' => $this->ruang->id, 'kelas_kamar_id' => $this->kelas->id, 'nomor' => $nomor,
        ]);
    }

    private function kunjunganSiap(): Kunjungan
    {
        $pasien = Pasien::factory()->create(['nama' => 'Siti Aminah']);
        $kunjungan = Kunjungan::factory()->create([
            'pasien_id' => $pasien->id, 'penjamin_id' => $this->umum->id,
        ]);
        $dokter = User::factory()->create(['dokter_id' => $kunjungan->dokter_id]);

        app(PemeriksaanKlinis::class)->catatSoap($kunjungan, [
            'subjective' => 'Muntah', 'objective' => 'Turgor menurun',
            'assessment' => 'Dehidrasi', 'plan' => 'Rawat inap',
        ], $dokter);
        app(PemeriksaanKlinis::class)
            ->tambahDiagnosa($kunjungan, Icd10::factory()->create()->id, JenisDiagnosa::Primer);

        return $kunjungan->refresh();
    }

    private function rawatInap(): RawatInap
    {
        $kunjungan = $this->kunjunganSiap();

        return app(PerintahRawatInap::class)->terbitkan(
            $kunjungan, User::factory()->create(['dokter_id' => $kunjungan->dokter_id]),
            'Dehidrasi sedang', $this->kelas
        );
    }

    private function pasienDiBed(?Bed $bed = null): RawatInap
    {
        $rawatInap = $this->rawatInap();

        app(PenempatanBed::class)->tempatkan(
            $rawatInap, $bed ?? $this->bed(), $this->penggunaBerperan(Peran::Admisi->value)
        );

        return $rawatInap->refresh();
    }

    public function test_papan_bed_menampilkan_bed_kosong_dan_terisi(): void
    {
        $this->bed('01');
        $rawatInap = $this->pasienDiBed($this->bed('02'));

        Livewire::actingAs($this->penggunaBerperan(Peran::Admisi->value))
            ->test(PapanBed::class)
            ->assertSee('Melati')
            ->assertSee('01')
            ->assertSee('02')
            ->assertSee('Siti Aminah')
            ->assertSee($rawatInap->no_rawat_inap);
    }

    public function test_papan_bed_menampilkan_pasien_yang_menunggu_penempatan(): void
    {
        $rawatInap = $this->rawatInap();

        Livewire::actingAs($this->penggunaBerperan(Peran::Admisi->value))
            ->test(PapanBed::class)
            ->assertSee($rawatInap->no_rawat_inap)
            ->assertSee('Menunggu Penempatan');
    }

    public function test_admisi_menempatkan_pasien_lewat_layar(): void
    {
        $rawatInap = $this->rawatInap();
        $bed = $this->bed('05');

        Livewire::actingAs($this->penggunaBerperan(Peran::Admisi->value))
            ->test(LayarPenempatan::class, ['rawatInap' => $rawatInap])
            ->set('bed_id', $bed->id)
            ->call('tempatkan')
            ->assertHasNoErrors();

        $this->assertSame($rawatInap->id, $bed->refresh()->rawat_inap_id);
    }

    public function test_menempatkan_di_bed_terisi_menampilkan_pesan_di_layar(): void
    {
        $bed = $this->bed('05');
        $this->pasienDiBed($bed);

        Livewire::actingAs($this->penggunaBerperan(Peran::Admisi->value))
            ->test(LayarPenempatan::class, ['rawatInap' => $this->rawatInap()])
            ->set('bed_id', $bed->id)
            ->call('tempatkan')
            ->assertHasErrors('penempatan');
    }

    public function test_layar_perawatan_menampilkan_catatan_dan_biaya_berjalan(): void
    {
        $rawatInap = $this->pasienDiBed();
        $perawat = $this->penggunaBerperan(Peran::Perawat->value);

        $visite = Tindakan::factory()->create(['nama' => 'Visite Dokter']);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Tindakan, 'layanan_id' => $visite->id,
            'penjamin_id' => $this->umum->id, 'harga' => 80000, 'berlaku_mulai' => '2026-01-01',
        ]);

        app(TindakanPelayanan::class)->tambah($rawatInap->kunjungan, $visite->id, 1, $perawat);

        Livewire::actingAs($perawat)
            ->test(LayarPerawatan::class, ['rawatInap' => $rawatInap])
            ->assertSee('Siti Aminah')
            ->assertSee('Melati')
            ->assertSee('Biaya sementara')
            ->assertSee('300.000');
    }

    public function test_perawat_menulis_catatan_lewat_layar(): void
    {
        $rawatInap = $this->pasienDiBed();

        Livewire::actingAs($this->penggunaBerperan(Peran::Perawat->value))
            ->test(LayarPerawatan::class, ['rawatInap' => $rawatInap])
            ->set('subjective', 'Mual berkurang')
            ->set('objective', 'TD 110/70')
            ->set('assessment', 'Perbaikan')
            ->set('plan', 'Lanjutkan cairan')
            ->call('simpanCatatan')
            ->assertHasNoErrors();

        $this->assertSame(1, CatatanPerkembangan::count());
        $this->assertSame('perawat', CatatanPerkembangan::first()->peran_penulis);
    }

    public function test_soap_tidak_lengkap_menampilkan_pesan_di_layar(): void
    {
        $rawatInap = $this->pasienDiBed();

        Livewire::actingAs($this->penggunaBerperan(Peran::Perawat->value))
            ->test(LayarPerawatan::class, ['rawatInap' => $rawatInap])
            ->set('subjective', 'Mual berkurang')
            ->set('objective', '')
            ->set('assessment', 'Perbaikan')
            ->set('plan', 'Lanjutkan cairan')
            ->call('simpanCatatan')
            ->assertHasErrors('objective');

        $this->assertSame(0, CatatanPerkembangan::count());
    }

    public function test_pindah_bed_lewat_layar_perawatan(): void
    {
        $rawatInap = $this->pasienDiBed($this->bed('01'));
        $tujuan = $this->bed('02');

        Livewire::actingAs($this->penggunaBerperan(Peran::Perawat->value))
            ->test(LayarPerawatan::class, ['rawatInap' => $rawatInap])
            ->set('bed_tujuan_id', $tujuan->id)
            ->set('alasanPindah', 'Ruangan direnovasi')
            ->call('pindahBed')
            ->assertHasNoErrors();

        $this->assertSame($tujuan->id, $rawatInap->refresh()->bedSekarang()->id);
    }

    public function test_pindah_bed_tanpa_alasan_menampilkan_pesan_di_layar(): void
    {
        $rawatInap = $this->pasienDiBed($this->bed('01'));

        Livewire::actingAs($this->penggunaBerperan(Peran::Perawat->value))
            ->test(LayarPerawatan::class, ['rawatInap' => $rawatInap])
            ->set('bed_tujuan_id', $this->bed('02')->id)
            ->set('alasanPindah', '')
            ->call('pindahBed')
            ->assertHasErrors('alasan');
    }

    public function test_dokter_memulangkan_pasien_lewat_layar(): void
    {
        $rawatInap = $this->pasienDiBed();
        $icd = Icd10::factory()->create();

        Livewire::actingAs($this->penggunaBerperan(Peran::Dokter->value))
            ->test(LayarPemulangan::class, ['rawatInap' => $rawatInap])
            ->set('diagnosa_akhir_id', $icd->id)
            ->set('cara_pulang', CaraPulang::Sembuh->value)
            ->set('ringkasan', 'Kondisi membaik.')
            ->call('pulangkan')
            ->assertHasNoErrors();

        $this->assertSame(StatusRawatInap::Pulang, $rawatInap->refresh()->status);
    }

    public function test_pemulangan_tanpa_diagnosa_menampilkan_pesan_di_layar(): void
    {
        $rawatInap = $this->pasienDiBed();

        Livewire::actingAs($this->penggunaBerperan(Peran::Dokter->value))
            ->test(LayarPemulangan::class, ['rawatInap' => $rawatInap])
            ->set('diagnosa_akhir_id', null)
            ->set('cara_pulang', CaraPulang::Sembuh->value)
            ->call('pulangkan')
            ->assertHasErrors('diagnosa_akhir_id');

        $this->assertSame(StatusRawatInap::Dirawat, $rawatInap->refresh()->status);
    }

    public function test_dokter_memerintahkan_rawat_inap_dari_layar_soap(): void
    {
        $kunjungan = $this->kunjunganSiap();
        $dokter = $this->penggunaBerperan(Peran::Dokter->value, ['dokter_id' => $kunjungan->dokter_id]);

        Livewire::actingAs($dokter)
            ->test(FormSoap::class, ['kunjungan' => $kunjungan])
            ->set('kelas_diminta_id', $this->kelas->id)
            ->set('indikasiRawatInap', 'Dehidrasi sedang')
            ->call('perintahkanRawatInap')
            ->assertHasNoErrors();

        $this->assertTrue($kunjungan->refresh()->sedangDirawatInap());
    }

    public function test_perintah_rawat_inap_tanpa_indikasi_menampilkan_pesan_di_layar(): void
    {
        $kunjungan = $this->kunjunganSiap();
        $dokter = $this->penggunaBerperan(Peran::Dokter->value, ['dokter_id' => $kunjungan->dokter_id]);

        Livewire::actingAs($dokter)
            ->test(FormSoap::class, ['kunjungan' => $kunjungan])
            ->set('kelas_diminta_id', $this->kelas->id)
            ->set('indikasiRawatInap', '')
            ->call('perintahkanRawatInap')
            ->assertHasErrors('indikasi');

        $this->assertFalse($kunjungan->refresh()->sedangDirawatInap());
    }

    public function test_papan_bed_menautkan_ke_layar_sesuai_kewenangan_pembaca(): void
    {
        $menunggu = $this->rawatInap();
        $dirawat = $this->pasienDiBed($this->bed('09'));

        // Tanpa tautan ini layar-layar tersebut tak terjangkau dari mana pun:
        // papan bed adalah satu-satunya daftar pasien rawat inap.
        Livewire::actingAs($this->penggunaBerperan(Peran::Admisi->value))
            ->test(PapanBed::class)
            ->assertSee(route('rawat-inap.tempatkan', $menunggu->id))
            ->assertDontSee(route('rawat-inap.pulangkan', $dirawat->id));

        Livewire::actingAs($this->penggunaBerperan(Peran::Dokter->value))
            ->test(PapanBed::class)
            ->assertSee(route('rawat-inap.rawat', $dirawat->id))
            ->assertSee(route('rawat-inap.pulangkan', $dirawat->id));

        Livewire::actingAs($this->penggunaBerperan(Peran::Perawat->value))
            ->test(PapanBed::class)
            ->assertSee(route('rawat-inap.rawat', $dirawat->id))
            ->assertDontSee(route('rawat-inap.pulangkan', $dirawat->id));
    }

    public function test_kasir_tidak_bisa_membuka_layar_perawatan(): void
    {
        $this->actingAs($this->penggunaBerperan(Peran::Kasir->value))
            ->get(route('rawat-inap.rawat', $this->pasienDiBed()->id))
            ->assertForbidden();
    }

    public function test_admisi_bisa_membuka_papan_bed(): void
    {
        $this->actingAs($this->penggunaBerperan(Peran::Admisi->value))
            ->get(route('rawat-inap.papan'))
            ->assertOk();
    }
}
