<?php

namespace Tests\Feature;

use App\Enums\JenisLayanan;
use App\Enums\StatusKunjungan;
use App\Enums\StatusOrderLab;
use App\Models\Kunjungan;
use App\Models\PemeriksaanLab;
use App\Models\Penjamin;
use App\Models\Tarif;
use App\Models\User;
use App\Services\PemesananLab;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class PemesananLabTest extends TestCase
{
    use RefreshDatabase;

    private Penjamin $umum;
    private PemeriksaanLab $darahRutin;
    private Kunjungan $kunjungan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $this->darahRutin = PemeriksaanLab::factory()->create([
            'nama' => 'Darah Rutin', 'kategori' => 'hematologi',
        ]);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Lab,
            'layanan_id' => $this->darahRutin->id,
            'penjamin_id' => $this->umum->id,
            'harga' => 75000,
            'berlaku_mulai' => '2026-01-01',
        ]);

        $this->kunjungan = Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]);
    }

    private function dokter(): User
    {
        return User::factory()->create();
    }

    public function test_order_lab_bernomor_dan_berstatus_dipesan(): void
    {
        $order = app(PemesananLab::class)
            ->pesan($this->kunjungan, [$this->darahRutin->id], $this->dokter(), 'Curiga anemia');

        $this->assertStringStartsWith('LB-', $order->no_order);
        $this->assertSame(StatusOrderLab::Dipesan, $order->status);
        $this->assertSame('Curiga anemia', $order->catatan_klinis);
        $this->assertSame(1, $order->detail()->count());
    }

    public function test_order_wajib_memuat_minimal_satu_pemeriksaan(): void
    {
        $this->expectException(ValidationException::class);

        app(PemesananLab::class)->pesan($this->kunjungan, [], $this->dokter());
    }

    public function test_pemeriksaan_yang_sama_tidak_boleh_dipesan_dua_kali_dalam_satu_order(): void
    {
        $this->expectException(ValidationException::class);

        app(PemesananLab::class)->pesan(
            $this->kunjungan,
            [$this->darahRutin->id, $this->darahRutin->id],
            $this->dokter()
        );
    }

    public function test_tarif_disalin_saat_order_dibuat(): void
    {
        $order = app(PemesananLab::class)
            ->pesan($this->kunjungan, [$this->darahRutin->id], $this->dokter());

        $this->assertSame(75000, (int) $order->detail->first()->tarif_satuan);
    }

    public function test_perubahan_master_tarif_tidak_mengubah_order_yang_sudah_dibuat(): void
    {
        $order = app(PemesananLab::class)
            ->pesan($this->kunjungan, [$this->darahRutin->id], $this->dokter());

        Tarif::query()->update(['harga' => 120000]);

        $this->assertSame(75000, (int) $order->detail->first()->refresh()->tarif_satuan);
    }

    public function test_order_tidak_bisa_dibuat_pada_kunjungan_yang_sudah_selesai(): void
    {
        $selesai = Kunjungan::factory()->create([
            'penjamin_id' => $this->umum->id,
            'status' => StatusKunjungan::Selesai,
        ]);

        $this->expectException(RuntimeException::class);

        app(PemesananLab::class)->pesan($selesai, [$this->darahRutin->id], $this->dokter());
    }

    public function test_pembatalan_sebelum_sampel_diambil_menandai_order_batal(): void
    {
        $order = app(PemesananLab::class)
            ->pesan($this->kunjungan, [$this->darahRutin->id], $this->dokter());

        $dibatalkan = app(PemesananLab::class)
            ->batalkan($order, $this->dokter(), 'Salah pesan');

        $this->assertSame(StatusOrderLab::Batal, $dibatalkan->status);
    }

    public function test_pembatalan_wajib_menyertakan_alasan(): void
    {
        $order = app(PemesananLab::class)
            ->pesan($this->kunjungan, [$this->darahRutin->id], $this->dokter());

        $this->expectException(ValidationException::class);

        app(PemesananLab::class)->batalkan($order, $this->dokter(), '   ');
    }

    public function test_alasan_pembatalan_tercatat_di_audit_log(): void
    {
        $order = app(PemesananLab::class)
            ->pesan($this->kunjungan, [$this->darahRutin->id], $this->dokter());

        app(PemesananLab::class)->batalkan($order, $this->dokter(), 'Pasien menolak diambil darah');

        $this->assertDatabaseHas('audit_logs', ['alasan' => 'Pasien menolak diambil darah']);
    }

    public function test_order_yang_sudah_batal_tidak_bisa_dibatalkan_lagi(): void
    {
        $order = app(PemesananLab::class)
            ->pesan($this->kunjungan, [$this->darahRutin->id], $this->dokter());

        app(PemesananLab::class)->batalkan($order, $this->dokter(), 'Salah pesan');

        $this->expectException(RuntimeException::class);

        app(PemesananLab::class)->batalkan($order->refresh(), $this->dokter(), 'Sekali lagi');
    }

    public function test_beberapa_pemeriksaan_bisa_dipesan_dalam_satu_order(): void
    {
        $gds = PemeriksaanLab::factory()->create(['nama' => 'Gula Darah Sewaktu']);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Lab, 'layanan_id' => $gds->id,
            'penjamin_id' => $this->umum->id, 'harga' => 35000, 'berlaku_mulai' => '2026-01-01',
        ]);

        $order = app(PemesananLab::class)
            ->pesan($this->kunjungan, [$this->darahRutin->id, $gds->id], $this->dokter());

        $this->assertSame(2, $order->detail()->count());
        $this->assertSame(110000, $order->totalTarif());
    }
}
