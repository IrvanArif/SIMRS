<?php

namespace Tests\Feature;

use App\Enums\JenisLayanan;
use App\Enums\PenandaHasil;
use App\Enums\Peran;
use App\Enums\StatusOrderLab;
use App\Livewire\Lab\AntreanOrder;
use App\Livewire\Lab\LayarEntriHasil;
use App\Livewire\Lab\LayarSampel;
use App\Livewire\Lab\LayarValidasi;
use App\Livewire\Poli\FormSoap;
use App\Models\HasilLab;
use App\Models\Kunjungan;
use App\Models\OrderLab;
use App\Models\ParameterLab;
use App\Models\Pasien;
use App\Models\PemeriksaanLab;
use App\Models\Penjamin;
use App\Models\RujukanLab;
use App\Models\Tarif;
use App\Models\User;
use App\Services\PemeriksaanLaboratorium;
use App\Services\PemesananLab;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LayarLabTest extends TestCase
{
    use RefreshDatabase;

    private PemeriksaanLab $darahRutin;
    private ParameterLab $hemoglobin;
    private Penjamin $umum;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Peran::semua() as $peran) {
            Role::findOrCreate($peran);
        }

        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $this->darahRutin = PemeriksaanLab::factory()->create([
            'nama' => 'Darah Rutin', 'kategori' => 'hematologi',
        ]);

        $this->hemoglobin = ParameterLab::factory()->create([
            'pemeriksaan_lab_id' => $this->darahRutin->id,
            'kode' => 'HB', 'nama' => 'Hemoglobin', 'satuan' => 'g/dL',
        ]);

        RujukanLab::factory()->create([
            'parameter_lab_id' => $this->hemoglobin->id,
            'jenis_kelamin' => 'P', 'nilai_min' => 12.0, 'nilai_maks' => 15.0,
        ]);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Lab, 'layanan_id' => $this->darahRutin->id,
            'penjamin_id' => $this->umum->id, 'harga' => 75000, 'berlaku_mulai' => '2026-01-01',
        ]);
    }

    private function analis(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Peran::Analis->value);

        return $user;
    }

    private function order(): OrderLab
    {
        $pasien = Pasien::factory()->create(['jenis_kelamin' => 'P', 'nama' => 'Siti Aminah']);
        $kunjungan = Kunjungan::factory()->create([
            'pasien_id' => $pasien->id, 'penjamin_id' => $this->umum->id,
        ]);

        return app(PemesananLab::class)
            ->pesan($kunjungan, [$this->darahRutin->id], User::factory()->create());
    }

    public function test_antrean_menampilkan_order_yang_belum_dikerjakan(): void
    {
        $order = $this->order();

        Livewire::actingAs($this->analis())
            ->test(AntreanOrder::class)
            ->assertSee($order->no_order)
            ->assertSee('Siti Aminah');
    }

    public function test_order_yang_sudah_divalidasi_tidak_muncul_di_antrean_dipesan(): void
    {
        $order = $this->order();
        $order->update(['status' => StatusOrderLab::Divalidasi]);

        Livewire::actingAs($this->analis())
            ->test(AntreanOrder::class)
            ->assertDontSee($order->no_order);
    }

    public function test_analis_mengambil_sampel_lewat_layar(): void
    {
        $order = $this->order();

        Livewire::actingAs($this->analis())
            ->test(LayarSampel::class, ['order' => $order])
            ->call('ambil')
            ->assertHasNoErrors();

        $this->assertSame(StatusOrderLab::SampelDiambil, $order->refresh()->status);
    }

    public function test_analis_mengentri_hasil_lewat_layar_dan_penandanya_muncul(): void
    {
        $order = $this->order();
        $analis = $this->analis();
        app(PemeriksaanLaboratorium::class)->ambilSampel($order, $analis);

        Livewire::actingAs($analis)
            ->test(LayarEntriHasil::class, ['order' => $order->refresh()])
            ->set("nilai.{$this->hemoglobin->id}", '16')
            ->call('simpan')
            ->assertHasNoErrors();

        $baris = HasilLab::where('parameter_lab_id', $this->hemoglobin->id)->first();

        // Pasiennya perempuan, rujukan 12–15, jadi 16 tergolong tinggi.
        $this->assertSame(PenandaHasil::Tinggi, $baris->penanda);
        $this->assertSame(StatusOrderLab::HasilDientri, $order->refresh()->status);
    }

    public function test_nilai_bukan_angka_menampilkan_pesan_di_layar_bukan_error(): void
    {
        $order = $this->order();
        $analis = $this->analis();
        app(PemeriksaanLaboratorium::class)->ambilSampel($order, $analis);

        Livewire::actingAs($analis)
            ->test(LayarEntriHasil::class, ['order' => $order->refresh()])
            ->set("nilai.{$this->hemoglobin->id}", 'enam belas')
            ->call('simpan')
            ->assertHasErrors("nilai.{$this->hemoglobin->id}");

        $this->assertSame(StatusOrderLab::SampelDiambil, $order->refresh()->status);
    }

    public function test_layar_entri_menampilkan_rentang_rujukan_pasien(): void
    {
        $order = $this->order();
        $analis = $this->analis();
        app(PemeriksaanLaboratorium::class)->ambilSampel($order, $analis);

        Livewire::actingAs($analis)
            ->test(LayarEntriHasil::class, ['order' => $order->refresh()])
            ->assertSee('Hemoglobin')
            ->assertSee('g/dL')
            ->assertSee('12');
    }

    public function test_analis_memvalidasi_lewat_layar(): void
    {
        $order = $this->order();
        $analis = $this->analis();
        $lab = app(PemeriksaanLaboratorium::class);

        $lab->ambilSampel($order, $analis);
        $lab->entriHasil($order->refresh(), [$this->hemoglobin->id => 14.0], $analis);

        Livewire::actingAs($analis)
            ->test(LayarValidasi::class, ['order' => $order->refresh()])
            ->call('validasi')
            ->assertHasNoErrors();

        $this->assertSame(StatusOrderLab::Divalidasi, $order->refresh()->status);
    }

    public function test_koreksi_tanpa_alasan_menampilkan_pesan_di_layar(): void
    {
        $order = $this->order();
        $analis = $this->analis();
        $lab = app(PemeriksaanLaboratorium::class);

        $lab->ambilSampel($order, $analis);
        $lab->entriHasil($order->refresh(), [$this->hemoglobin->id => 14.0], $analis);
        $lab->validasi($order->refresh(), $analis);

        Livewire::actingAs($analis)
            ->test(LayarValidasi::class, ['order' => $order->refresh()])
            ->set("nilai.{$this->hemoglobin->id}", '9')
            ->set('alasanKoreksi', '')
            ->call('koreksi')
            ->assertHasErrors('alasan');
    }

    public function test_dokter_memesan_lab_dari_layar_soap(): void
    {
        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]);
        $dokter = User::factory()->create(['dokter_id' => $kunjungan->dokter_id]);
        $dokter->assignRole(Peran::Dokter->value);

        Livewire::actingAs($dokter)
            ->test(FormSoap::class, ['kunjungan' => $kunjungan])
            ->set('pemeriksaanLabDipilih', [$this->darahRutin->id])
            ->call('pesanLab')
            ->assertHasNoErrors();

        $this->assertSame(1, $kunjungan->refresh()->orderLab()->count());
    }

    public function test_dokter_tidak_bisa_membuka_layar_analis(): void
    {
        $dokter = User::factory()->create();
        $dokter->assignRole(Peran::Dokter->value);

        $this->actingAs($dokter)->get(route('lab.antrean'))->assertForbidden();
    }

    public function test_analis_tidak_bisa_membuka_layar_kasir(): void
    {
        $this->actingAs($this->analis())->get(route('kasir.tagihan'))->assertForbidden();
    }
}
