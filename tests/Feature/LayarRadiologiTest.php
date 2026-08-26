<?php

namespace Tests\Feature;

use App\Enums\JenisLayanan;
use App\Enums\Peran;
use App\Enums\StatusOrderRadiologi;
use App\Livewire\Poli\FormSoap;
use App\Livewire\Radiologi\AntreanOrder;
use App\Livewire\Radiologi\LayarEkspertise;
use App\Livewire\Radiologi\LayarPelaksanaan;
use App\Models\EkspertiseRadiologi;
use App\Models\Kunjungan;
use App\Models\OrderRadiologi;
use App\Models\Pasien;
use App\Models\PemeriksaanRadiologi;
use App\Models\Penjamin;
use App\Models\Tarif;
use App\Models\User;
use App\Services\PelaksanaanRadiologi;
use App\Services\PemesananRadiologi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LayarRadiologiTest extends TestCase
{
    use RefreshDatabase;

    private Penjamin $umum;

    private PemeriksaanRadiologi $usg;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Peran::semua() as $peran) {
            Role::findOrCreate($peran);
        }

        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $this->usg = PemeriksaanRadiologi::factory()->create([
            'nama' => 'USG Abdomen',
            'modalitas' => 'usg',
            'persiapan' => 'Puasa 6 jam sebelum pemeriksaan',
        ]);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Radiologi, 'layanan_id' => $this->usg->id,
            'penjamin_id' => $this->umum->id, 'harga' => 220000, 'berlaku_mulai' => '2026-01-01',
        ]);
    }

    private function radiografer(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Peran::Radiografer->value);

        return $user;
    }

    private function dokter(?Kunjungan $kunjungan = null): User
    {
        $user = User::factory()->create(
            $kunjungan ? ['dokter_id' => $kunjungan->dokter_id] : []
        );
        $user->assignRole(Peran::Dokter->value);

        return $user;
    }

    private function order(): OrderRadiologi
    {
        $pasien = Pasien::factory()->create(['nama' => 'Siti Aminah']);
        $kunjungan = Kunjungan::factory()->create([
            'pasien_id' => $pasien->id, 'penjamin_id' => $this->umum->id,
        ]);

        return app(PemesananRadiologi::class)
            ->pesan($kunjungan, [$this->usg->id], User::factory()->create(), 'Nyeri perut kanan atas');
    }

    private function bacaan(OrderRadiologi $order): array
    {
        return [
            'temuan' => 'Kandung empedu berdinding tebal, tampak batu ukuran 8 mm.',
            'kesan' => 'Kolelitiasis dengan kolesistitis kronis.',
            'saran' => 'Konsul bedah digestif.',
        ];
    }

    public function test_antrean_menampilkan_order_yang_belum_dikerjakan(): void
    {
        $order = $this->order();

        Livewire::actingAs($this->radiografer())
            ->test(AntreanOrder::class)
            ->assertSee($order->no_order)
            ->assertSee('Siti Aminah')
            ->assertSee('USG Abdomen');
    }

    public function test_order_yang_sudah_selesai_tidak_muncul_di_antrean_dipesan(): void
    {
        $order = $this->order();
        $order->update(['status' => StatusOrderRadiologi::Selesai]);

        Livewire::actingAs($this->radiografer())
            ->test(AntreanOrder::class)
            ->assertDontSee($order->no_order);
    }

    public function test_radiografer_mengerjakan_pencitraan_lewat_layar(): void
    {
        $order = $this->order();

        Livewire::actingAs($this->radiografer())
            ->test(LayarPelaksanaan::class, ['order' => $order])
            ->set('no_film', 'FILM-2026-0007')
            ->call('kerjakan')
            ->assertHasNoErrors();

        $this->assertSame(StatusOrderRadiologi::Dikerjakan, $order->refresh()->status);
        $this->assertSame('FILM-2026-0007', $order->no_film);
    }

    public function test_nomor_film_kosong_menampilkan_pesan_di_layar(): void
    {
        $order = $this->order();

        Livewire::actingAs($this->radiografer())
            ->test(LayarPelaksanaan::class, ['order' => $order])
            ->set('no_film', '')
            ->call('kerjakan')
            ->assertHasErrors('no_film');

        $this->assertSame(StatusOrderRadiologi::Dipesan, $order->refresh()->status);
    }

    public function test_layar_pelaksanaan_menampilkan_indikasi_klinis_dan_persiapan(): void
    {
        Livewire::actingAs($this->radiografer())
            ->test(LayarPelaksanaan::class, ['order' => $this->order()])
            ->assertSee('Nyeri perut kanan atas')
            ->assertSee('Puasa 6 jam sebelum pemeriksaan');
    }

    public function test_dokter_menulis_ekspertise_lewat_layar(): void
    {
        $order = $this->order();
        app(PelaksanaanRadiologi::class)->kerjakan($order, 'FILM-1', $this->radiografer());

        $detailId = $order->refresh()->detail->first()->id;
        $isi = $this->bacaan($order);

        Livewire::actingAs($this->dokter())
            ->test(LayarEkspertise::class, ['order' => $order])
            ->set("bacaan.{$detailId}.temuan", $isi['temuan'])
            ->set("bacaan.{$detailId}.kesan", $isi['kesan'])
            ->set("bacaan.{$detailId}.saran", $isi['saran'])
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertSame(StatusOrderRadiologi::Selesai, $order->refresh()->status);
        $this->assertSame($isi['kesan'], EkspertiseRadiologi::first()->kesan);
    }

    public function test_temuan_kosong_menampilkan_pesan_di_layar(): void
    {
        $order = $this->order();
        app(PelaksanaanRadiologi::class)->kerjakan($order, 'FILM-1', $this->radiografer());

        $detailId = $order->refresh()->detail->first()->id;

        Livewire::actingAs($this->dokter())
            ->test(LayarEkspertise::class, ['order' => $order])
            ->set("bacaan.{$detailId}.temuan", '')
            ->set("bacaan.{$detailId}.kesan", 'Normal.')
            ->call('simpan')
            ->assertHasErrors();

        $this->assertSame(StatusOrderRadiologi::Dikerjakan, $order->refresh()->status);
    }

    public function test_koreksi_tanpa_alasan_menampilkan_pesan_di_layar(): void
    {
        $order = $this->order();
        app(PelaksanaanRadiologi::class)->kerjakan($order, 'FILM-1', $this->radiografer());

        $detailId = $order->refresh()->detail->first()->id;
        $isi = $this->bacaan($order);
        $dokter = $this->dokter();

        Livewire::actingAs($dokter)
            ->test(LayarEkspertise::class, ['order' => $order])
            ->set("bacaan.{$detailId}.temuan", $isi['temuan'])
            ->set("bacaan.{$detailId}.kesan", $isi['kesan'])
            ->call('simpan');

        Livewire::actingAs($dokter)
            ->test(LayarEkspertise::class, ['order' => $order->refresh()])
            ->set("bacaan.{$detailId}.kesan", 'Normal.')
            ->set('alasanKoreksi', '')
            ->call('simpan')
            ->assertHasErrors('alasan');

        $this->assertSame($isi['kesan'], EkspertiseRadiologi::first()->kesan);
    }

    public function test_dokter_memesan_radiologi_dari_layar_soap(): void
    {
        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]);

        Livewire::actingAs($this->dokter($kunjungan))
            ->test(FormSoap::class, ['kunjungan' => $kunjungan])
            ->set('pemeriksaanRadiologiDipilih', [$this->usg->id])
            ->set('indikasiRadiologi', 'Nyeri perut kanan atas')
            ->call('pesanRadiologi')
            ->assertHasNoErrors();

        $this->assertSame(1, $kunjungan->refresh()->orderRadiologi()->count());
    }

    public function test_pesan_radiologi_tanpa_indikasi_menampilkan_pesan_di_layar(): void
    {
        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]);

        Livewire::actingAs($this->dokter($kunjungan))
            ->test(FormSoap::class, ['kunjungan' => $kunjungan])
            ->set('pemeriksaanRadiologiDipilih', [$this->usg->id])
            ->set('indikasiRadiologi', '')
            ->call('pesanRadiologi')
            ->assertHasErrors('indikasi_klinis');

        $this->assertSame(0, $kunjungan->refresh()->orderRadiologi()->count());
    }

    public function test_radiografer_tidak_bisa_membuka_layar_kasir(): void
    {
        $this->actingAs($this->radiografer())
            ->get('/kasir/tagihan')
            ->assertForbidden();
    }

    public function test_radiografer_bisa_membuka_antrean_radiologi(): void
    {
        $this->actingAs($this->radiografer())
            ->get('/radiologi/antrean')
            ->assertOk();
    }
}
