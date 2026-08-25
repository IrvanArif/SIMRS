<?php

namespace Tests\Feature;

use App\Enums\JenisLayanan;
use App\Enums\Peran;
use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\OrderLab;
use App\Models\OrderRadiologi;
use App\Models\Pemeriksaan;
use App\Models\PemeriksaanLab;
use App\Models\PemeriksaanRadiologi;
use App\Models\Penjamin;
use App\Models\Tagihan;
use App\Models\Tarif;
use App\Models\User;
use App\Services\PelaksanaanRadiologi;
use App\Services\PemesananLab;
use App\Services\PemesananRadiologi;
use App\Services\PenulisanResep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HakAksesRadiologiTest extends TestCase
{
    use RefreshDatabase;

    private Penjamin $umum;

    private PemeriksaanRadiologi $toraks;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Peran::semua() as $peran) {
            Role::findOrCreate($peran);
        }

        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $this->toraks = PemeriksaanRadiologi::factory()->create(['nama' => 'Rontgen Toraks PA']);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Radiologi, 'layanan_id' => $this->toraks->id,
            'penjamin_id' => $this->umum->id, 'harga' => 150000, 'berlaku_mulai' => '2026-01-01',
        ]);
    }

    private function penggunaBerperan(string $peran, array $atribut = []): User
    {
        $user = User::factory()->create($atribut);
        $user->assignRole($peran);

        return $user;
    }

    private function order(): OrderRadiologi
    {
        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]);

        return app(PemesananRadiologi::class)
            ->pesan($kunjungan, [$this->toraks->id], User::factory()->create(), 'Batuk kronis');
    }

    public function test_radiografer_boleh_mengerjakan_pencitraan(): void
    {
        $radiografer = $this->penggunaBerperan(Peran::Radiografer->value);

        $this->assertTrue(Gate::forUser($radiografer)->allows('kerjakan', $this->order()));
    }

    public function test_radiografer_tidak_bisa_menulis_ekspertise(): void
    {
        // Menyimpulkan temuan pencitraan adalah tindakan medis, bukan tugas
        // petugas yang mengoperasikan alatnya (aturan 54).
        $radiografer = $this->penggunaBerperan(Peran::Radiografer->value);

        $this->assertFalse(Gate::forUser($radiografer)->allows('ekspertise', $this->order()));
    }

    public function test_dokter_boleh_menulis_ekspertise(): void
    {
        $dokter = $this->penggunaBerperan(Peran::Dokter->value);

        $this->assertTrue(Gate::forUser($dokter)->allows('ekspertise', $this->order()));
    }

    public function test_hanya_dokter_yang_boleh_memesan_radiologi(): void
    {
        $this->assertTrue(
            Gate::forUser($this->penggunaBerperan(Peran::Dokter->value))->allows('pesan', OrderRadiologi::class)
        );
        $this->assertFalse(
            Gate::forUser($this->penggunaBerperan(Peran::Radiografer->value))->allows('pesan', OrderRadiologi::class)
        );
    }

    public function test_radiografer_tidak_bisa_mengubah_rekam_medis(): void
    {
        $radiografer = $this->penggunaBerperan(Peran::Radiografer->value);

        $this->assertFalse(Gate::forUser($radiografer)->allows('ubah', Pemeriksaan::factory()->create()));
    }

    public function test_radiografer_tidak_bisa_menyiapkan_resep(): void
    {
        $resep = app(PenulisanResep::class)->tulis(
            Kunjungan::factory()->create(),
            [['obat_id' => Obat::factory()->create()->id, 'jumlah' => 5, 'aturan_pakai' => '2x1']],
            User::factory()->create()
        );

        $this->assertFalse(
            Gate::forUser($this->penggunaBerperan(Peran::Radiografer->value))->allows('siapkan', $resep)
        );
    }

    public function test_radiografer_tidak_bisa_mengerjakan_order_lab(): void
    {
        $pemeriksaan = PemeriksaanLab::factory()->create();
        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Lab, 'layanan_id' => $pemeriksaan->id,
            'penjamin_id' => $this->umum->id, 'harga' => 75000, 'berlaku_mulai' => '2026-01-01',
        ]);

        $orderLab = app(PemesananLab::class)->pesan(
            Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]),
            [$pemeriksaan->id],
            User::factory()->create()
        );

        $this->assertFalse(
            Gate::forUser($this->penggunaBerperan(Peran::Radiografer->value))->allows('kerjakan', $orderLab)
        );
    }

    public function test_radiografer_tidak_bisa_memproses_pembayaran(): void
    {
        $this->assertFalse(
            Gate::forUser($this->penggunaBerperan(Peran::Radiografer->value))
                ->allows('proses', Tagihan::factory()->create())
        );
    }

    public function test_hasil_belum_diekspertise_tidak_boleh_dibaca_dokter(): void
    {
        $order = $this->order();
        $dokter = $this->penggunaBerperan(Peran::Dokter->value, ['dokter_id' => $order->kunjungan->dokter_id]);

        $this->assertFalse(Gate::forUser($dokter)->allows('baca', $order));

        app(PelaksanaanRadiologi::class)->kerjakan($order, 'FILM-1', User::factory()->create());

        // Citra sudah diambil, tapi belum ada yang membacanya.
        $this->assertFalse(Gate::forUser($dokter)->allows('baca', $order->refresh()));
    }

    public function test_order_yang_sudah_batal_tidak_bisa_dikerjakan_lagi(): void
    {
        $order = $this->order();
        app(PemesananRadiologi::class)->batalkan($order, User::factory()->create(), 'Salah pesan');

        $this->assertFalse(
            Gate::forUser($this->penggunaBerperan(Peran::Radiografer->value))->allows('kerjakan', $order->refresh())
        );
    }
}
