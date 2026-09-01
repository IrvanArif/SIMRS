<?php

namespace Tests\Feature;

use App\Enums\JenisDiagnosa;
use App\Enums\JenisLayanan;
use App\Enums\Peran;
use App\Livewire\Laporan\Indikator;
use App\Livewire\Laporan\Morbiditas;
use App\Livewire\Laporan\Pendapatan;
use App\Livewire\Laporan\RekapKunjungan;
use App\Models\Bed;
use App\Models\Icd10;
use App\Models\KelasKamar;
use App\Models\Kunjungan;
use App\Models\Penjamin;
use App\Models\Poli;
use App\Models\Ruang;
use App\Models\Tarif;
use App\Models\Tindakan;
use App\Models\User;
use App\Services\PemeriksaanKlinis;
use App\Services\TindakanPelayanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LayarLaporanTest extends TestCase
{
    use RefreshDatabase;

    private Penjamin $umum;

    private Tindakan $konsultasi;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Peran::semua() as $peran) {
            Role::findOrCreate($peran);
        }

        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'nama' => 'Umum (Tunai)', 'jenis' => 'tunai']);
        $this->konsultasi = Tindakan::factory()->create(['nama' => 'Konsultasi Dokter Umum']);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Tindakan, 'layanan_id' => $this->konsultasi->id,
            'penjamin_id' => $this->umum->id, 'harga' => 50000, 'berlaku_mulai' => '2026-01-01',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function penggunaBerperan(string $peran): User
    {
        $user = User::factory()->create();
        $user->assignRole($peran);

        return $user;
    }

    private function kunjunganSelesai(string $tanggal, string $kodeIcd): Kunjungan
    {
        Carbon::setTestNow($tanggal.' 09:00:00');

        $kunjungan = Kunjungan::factory()->create([
            'penjamin_id' => $this->umum->id,
            'poli_id' => Poli::factory()->create(['nama' => 'Poli Umum'])->id,
            'tanggal' => $tanggal,
        ]);
        $dokter = User::factory()->create(['dokter_id' => $kunjungan->dokter_id]);
        $klinis = app(PemeriksaanKlinis::class);

        app(TindakanPelayanan::class)->tambah($kunjungan, $this->konsultasi->id, 1, $dokter);
        $klinis->catatSoap($kunjungan, ['subjective' => 'a', 'objective' => 'b', 'assessment' => 'c', 'plan' => 'd'], $dokter);
        $klinis->tambahDiagnosa(
            $kunjungan,
            Icd10::firstOrCreate(['kode' => $kodeIcd], ['nama_id' => 'Diagnosa '.$kodeIcd])->id,
            JenisDiagnosa::Primer
        );
        $klinis->selesaikan($kunjungan->refresh(), $dokter);

        return $kunjungan->refresh();
    }

    public function test_layar_indikator_menampilkan_bor_los_toi_bto(): void
    {
        Bed::factory()->create([
            'ruang_id' => Ruang::factory()->create()->id,
            'kelas_kamar_id' => KelasKamar::factory()->create()->id,
        ]);

        Livewire::actingAs($this->penggunaBerperan(Peran::Admin->value))
            ->test(Indikator::class)
            ->set('awal', '2026-06-01')
            ->set('akhir', '2026-06-30')
            ->assertSee('BOR')
            ->assertSee('LOS')
            ->assertSee('TOI')
            ->assertSee('BTO')
            ->assertHasNoErrors();
    }

    public function test_layar_laporan_menolak_rentang_terbalik_dengan_pesan(): void
    {
        // Laporan yang mengembalikan nol karena tanggalnya tertukar terlihat
        // seperti periode yang memang sepi.
        Livewire::actingAs($this->penggunaBerperan(Peran::Admin->value))
            ->test(Indikator::class)
            ->set('awal', '2026-06-30')
            ->set('akhir', '2026-06-01')
            ->assertSee('tidak boleh mendahului');
    }

    public function test_layar_morbiditas_menampilkan_sepuluh_besar(): void
    {
        $this->kunjunganSelesai('2026-06-01', 'J06.9');
        $this->kunjunganSelesai('2026-06-02', 'J06.9');

        Livewire::actingAs($this->penggunaBerperan(Peran::RekamMedis->value))
            ->test(Morbiditas::class)
            ->set('awal', '2026-06-01')
            ->set('akhir', '2026-06-30')
            ->assertSee('J06.9');
    }

    public function test_layar_pendapatan_menampilkan_pemisahan_status_tagihan(): void
    {
        $this->kunjunganSelesai('2026-06-01', 'J06.9');

        Livewire::actingAs($this->penggunaBerperan(Peran::Kasir->value))
            ->test(Pendapatan::class)
            ->set('awal', '2026-06-01')
            ->set('akhir', '2026-06-30')
            ->assertSee('Umum (Tunai)')
            ->assertSee('Ditanggung Penjamin')
            ->assertSee('50.000');
    }

    public function test_layar_rekap_kunjungan_menampilkan_per_poli(): void
    {
        $this->kunjunganSelesai('2026-06-01', 'J06.9');

        Livewire::actingAs($this->penggunaBerperan(Peran::RekamMedis->value))
            ->test(RekapKunjungan::class)
            ->set('awal', '2026-06-01')
            ->set('akhir', '2026-06-30')
            ->assertSee('Poli Umum');
    }

    public function test_kasir_boleh_melihat_laporan_pendapatan(): void
    {
        $this->actingAs($this->penggunaBerperan(Peran::Kasir->value))
            ->get(route('laporan.pendapatan'))
            ->assertOk();
    }

    public function test_kasir_tidak_bisa_melihat_laporan_morbiditas(): void
    {
        $this->actingAs($this->penggunaBerperan(Peran::Kasir->value))
            ->get(route('laporan.morbiditas'))
            ->assertForbidden();
    }

    public function test_apoteker_tidak_bisa_melihat_laporan_apa_pun(): void
    {
        foreach (['laporan.indikator', 'laporan.morbiditas', 'laporan.pendapatan', 'laporan.kunjungan'] as $rute) {
            $this->actingAs($this->penggunaBerperan(Peran::Apoteker->value))
                ->get(route($rute))
                ->assertForbidden();
        }
    }

    public function test_admin_bisa_membuka_seluruh_laporan(): void
    {
        foreach (['laporan.indikator', 'laporan.morbiditas', 'laporan.pendapatan', 'laporan.kunjungan'] as $rute) {
            $this->actingAs($this->penggunaBerperan(Peran::Admin->value))
                ->get(route($rute))
                ->assertOk();
        }
    }
}
