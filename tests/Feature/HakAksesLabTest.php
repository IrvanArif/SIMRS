<?php

namespace Tests\Feature;

use App\Enums\JenisLayanan;
use App\Enums\Peran;
use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\OrderLab;
use App\Models\Pemeriksaan;
use App\Models\PemeriksaanLab;
use App\Models\Penjamin;
use App\Models\Tagihan;
use App\Models\Tarif;
use App\Models\User;
use App\Services\PemesananLab;
use App\Services\PenulisanResep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HakAksesLabTest extends TestCase
{
    use RefreshDatabase;

    private PemeriksaanLab $darahRutin;
    private Penjamin $umum;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Peran::semua() as $peran) {
            Role::findOrCreate($peran);
        }

        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $this->darahRutin = PemeriksaanLab::factory()->create(['nama' => 'Darah Rutin']);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Lab, 'layanan_id' => $this->darahRutin->id,
            'penjamin_id' => $this->umum->id, 'harga' => 75000, 'berlaku_mulai' => '2026-01-01',
        ]);
    }

    private function penggunaBerperan(string $peran, array $atribut = []): User
    {
        $user = User::factory()->create($atribut);
        $user->assignRole($peran);

        return $user;
    }

    private function order(): OrderLab
    {
        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]);

        return app(PemesananLab::class)
            ->pesan($kunjungan, [$this->darahRutin->id], User::factory()->create());
    }

    public function test_analis_boleh_mengerjakan_dan_memvalidasi_order(): void
    {
        $order = $this->order();
        $analis = $this->penggunaBerperan(Peran::Analis->value);

        $this->assertTrue(Gate::forUser($analis)->allows('kerjakan', $order));
        $this->assertTrue(Gate::forUser($analis)->allows('validasi', $order));
    }

    public function test_dokter_tidak_bisa_mengentri_hasil_lab(): void
    {
        $dokter = $this->penggunaBerperan(Peran::Dokter->value);

        $this->assertFalse(Gate::forUser($dokter)->allows('kerjakan', $this->order()));
    }

    public function test_hanya_dokter_yang_boleh_memesan_lab(): void
    {
        $this->assertTrue(
            Gate::forUser($this->penggunaBerperan(Peran::Dokter->value))->allows('pesan', OrderLab::class)
        );
        $this->assertFalse(
            Gate::forUser($this->penggunaBerperan(Peran::Analis->value))->allows('pesan', OrderLab::class)
        );
    }

    public function test_analis_tidak_bisa_mengubah_rekam_medis(): void
    {
        $pemeriksaan = Pemeriksaan::factory()->create();
        $analis = $this->penggunaBerperan(Peran::Analis->value);

        $this->assertFalse(Gate::forUser($analis)->allows('ubah', $pemeriksaan));
    }

    public function test_analis_tidak_bisa_memeriksa_kunjungan(): void
    {
        $kunjungan = Kunjungan::factory()->create();
        $analis = $this->penggunaBerperan(Peran::Analis->value);

        $this->assertFalse(Gate::forUser($analis)->allows('periksa', $kunjungan));
    }

    public function test_analis_tidak_bisa_menyiapkan_resep(): void
    {
        $resep = app(PenulisanResep::class)->tulis(
            Kunjungan::factory()->create(),
            [['obat_id' => Obat::factory()->create()->id, 'jumlah' => 5, 'aturan_pakai' => '2x1']],
            User::factory()->create()
        );

        $this->assertFalse(
            Gate::forUser($this->penggunaBerperan(Peran::Analis->value))->allows('siapkan', $resep)
        );
    }

    public function test_analis_tidak_bisa_memproses_pembayaran(): void
    {
        $this->assertFalse(
            Gate::forUser($this->penggunaBerperan(Peran::Analis->value))
                ->allows('proses', Tagihan::factory()->create())
        );
    }

    public function test_hasil_belum_divalidasi_tidak_boleh_dibaca_dokter(): void
    {
        $order = $this->order();
        $dokter = $this->penggunaBerperan(Peran::Dokter->value, ['dokter_id' => $order->kunjungan->dokter_id]);

        $this->assertFalse(Gate::forUser($dokter)->allows('baca', $order));
    }

    public function test_order_yang_sudah_selesai_tidak_bisa_dikerjakan_lagi(): void
    {
        $order = $this->order();
        app(PemesananLab::class)->batalkan($order, User::factory()->create(), 'Salah pesan');

        $analis = $this->penggunaBerperan(Peran::Analis->value);

        $this->assertFalse(Gate::forUser($analis)->allows('kerjakan', $order->refresh()));
    }
}
