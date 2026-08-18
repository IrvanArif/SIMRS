<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Enums\StatusKunjungan;
use App\Livewire\Poli\FormSoap;
use App\Livewire\Poli\FormVital;
use App\Models\Dokter;
use App\Models\Icd10;
use App\Models\Kunjungan;
use App\Models\Penjamin;
use App\Models\Poli;
use App\Models\TarifTindakan;
use App\Models\Tindakan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LayarPoliTest extends TestCase
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

    private function kunjunganSiapPeriksa(): Kunjungan
    {
        $umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $poli = Poli::factory()->create(['kode' => 'UMU']);
        $dokter = Dokter::factory()->create(['poli_id' => $poli->id]);

        return Kunjungan::factory()->create([
            'poli_id' => $poli->id,
            'dokter_id' => $dokter->id,
            'penjamin_id' => $umum->id,
            'tanggal' => now()->toDateString(),
        ]);
    }

    private function tindakanBertarif(Kunjungan $kunjungan, int $tarif = 50000): Tindakan
    {
        $tindakan = Tindakan::factory()->create(['nama' => 'Konsultasi Dokter Umum']);

        TarifTindakan::factory()->create([
            'tindakan_id' => $tindakan->id,
            'penjamin_id' => $kunjungan->penjamin_id,
            'tarif' => $tarif,
            'berlaku_mulai' => '2026-01-01',
        ]);

        return $tindakan;
    }

    public function test_perawat_mengisi_vital_lewat_layar(): void
    {
        $kunjungan = $this->kunjunganSiapPeriksa();
        $perawat = $this->penggunaBerperan(Peran::Perawat->value);

        Livewire::actingAs($perawat)
            ->test(FormVital::class, ['kunjungan' => $kunjungan])
            ->set('sistolik', 120)->set('diastolik', 80)->set('nadi', 78)
            ->set('suhu', 36.7)->set('respirasi', 18)
            ->set('berat_badan', 62.5)->set('tinggi_badan', 165)
            ->set('keluhan_awal', 'Batuk tiga hari')
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertSame(StatusKunjungan::DiperiksaPerawat, $kunjungan->refresh()->status);
    }

    public function test_suhu_tidak_wajar_menampilkan_pesan_validasi_di_layar(): void
    {
        $kunjungan = $this->kunjunganSiapPeriksa();

        Livewire::actingAs($this->penggunaBerperan(Peran::Perawat->value))
            ->test(FormVital::class, ['kunjungan' => $kunjungan])
            ->set('sistolik', 120)->set('diastolik', 80)->set('nadi', 78)
            ->set('suhu', 55)->set('respirasi', 18)
            ->set('berat_badan', 62.5)->set('tinggi_badan', 165)
            ->set('keluhan_awal', 'Batuk tiga hari')
            ->call('simpan')
            ->assertHasErrors('suhu');
    }

    public function test_kasir_tidak_bisa_membuka_form_soap(): void
    {
        $kunjungan = $this->kunjunganSiapPeriksa();

        $this->actingAs($this->penggunaBerperan(Peran::Kasir->value))
            ->get(route('poli.soap', $kunjungan))
            ->assertForbidden();
    }

    public function test_dokter_poli_lain_tidak_bisa_membuka_form_soap(): void
    {
        $kunjungan = $this->kunjunganSiapPeriksa();
        $poliLain = Poli::factory()->create(['kode' => 'GIG']);
        $dokterLain = Dokter::factory()->create(['poli_id' => $poliLain->id]);
        $user = $this->penggunaBerperan(Peran::Dokter->value, ['dokter_id' => $dokterLain->id]);

        $this->actingAs($user)->get(route('poli.soap', $kunjungan))->assertForbidden();
    }

    public function test_dokter_menyelesaikan_kunjungan_lewat_layar_dan_tagihan_terbentuk(): void
    {
        $kunjungan = $this->kunjunganSiapPeriksa();
        $tindakan = $this->tindakanBertarif($kunjungan);
        $icd = Icd10::factory()->create();
        $dokter = $this->penggunaBerperan(Peran::Dokter->value, ['dokter_id' => $kunjungan->dokter_id]);

        Livewire::actingAs($dokter)
            ->test(FormSoap::class, ['kunjungan' => $kunjungan])
            ->set('subjective', 'Batuk tiga hari')->set('objective', 'Faring hiperemis')
            ->set('assessment', 'ISPA')->set('plan', 'Antibiotik')
            ->call('simpanSoap')
            ->set('icd10_id', $icd->id)
            ->call('tambahDiagnosaPrimer')
            ->set('tindakan_id', $tindakan->id)
            ->set('jumlah_tindakan', 1)
            ->call('tambahTindakan')
            ->call('selesaikan')
            ->assertHasNoErrors();

        $kunjungan->refresh();

        $this->assertSame(StatusKunjungan::Selesai, $kunjungan->status);
        $this->assertSame(50000, (int) $kunjungan->tagihan->total);
    }

    public function test_menyelesaikan_tanpa_diagnosa_menampilkan_pesan_kesalahan(): void
    {
        $kunjungan = $this->kunjunganSiapPeriksa();
        $dokter = $this->penggunaBerperan(Peran::Dokter->value, ['dokter_id' => $kunjungan->dokter_id]);

        Livewire::actingAs($dokter)
            ->test(FormSoap::class, ['kunjungan' => $kunjungan])
            ->set('subjective', 'Batuk')->set('objective', 'Faring hiperemis')
            ->set('assessment', 'ISPA')->set('plan', 'Antibiotik')
            ->call('simpanSoap')
            ->call('selesaikan')
            ->assertHasErrors('penyelesaian');

        $this->assertSame(StatusKunjungan::DiperiksaDokter, $kunjungan->refresh()->status);
    }
}
