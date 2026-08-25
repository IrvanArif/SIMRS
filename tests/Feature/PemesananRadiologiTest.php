<?php

namespace Tests\Feature;

use App\Enums\JenisLayanan;
use App\Enums\StatusKunjungan;
use App\Enums\StatusOrderRadiologi;
use App\Models\Kunjungan;
use App\Models\PemeriksaanRadiologi;
use App\Models\Penjamin;
use App\Models\Tarif;
use App\Models\User;
use App\Services\PemesananRadiologi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class PemesananRadiologiTest extends TestCase
{
    use RefreshDatabase;

    private Penjamin $umum;

    private PemeriksaanRadiologi $toraks;

    private Kunjungan $kunjungan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $this->toraks = PemeriksaanRadiologi::factory()->create([
            'nama' => 'Rontgen Toraks PA', 'modalitas' => 'rontgen',
        ]);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Radiologi,
            'layanan_id' => $this->toraks->id,
            'penjamin_id' => $this->umum->id,
            'harga' => 150000,
            'berlaku_mulai' => '2026-01-01',
        ]);

        $this->kunjungan = Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]);
    }

    private function dokter(): User
    {
        return User::factory()->create();
    }

    public function test_order_bernomor_dan_berstatus_dipesan(): void
    {
        $order = app(PemesananRadiologi::class)
            ->pesan($this->kunjungan, [$this->toraks->id], $this->dokter(), 'Batuk kronis');

        $this->assertStringStartsWith('RD-', $order->no_order);
        $this->assertSame(StatusOrderRadiologi::Dipesan, $order->status);
        $this->assertSame('Batuk kronis', $order->indikasi_klinis);
        $this->assertSame(1, $order->detail()->count());
    }

    public function test_order_wajib_memuat_minimal_satu_pemeriksaan(): void
    {
        $this->expectException(ValidationException::class);

        app(PemesananRadiologi::class)->pesan($this->kunjungan, [], $this->dokter(), 'Batuk kronis');
    }

    public function test_order_wajib_menyertakan_indikasi_klinis(): void
    {
        // Pencitraan tanpa indikasi berarti pasien menerima radiasi tanpa alasan
        // yang tercatat.
        $this->expectException(ValidationException::class);

        app(PemesananRadiologi::class)->pesan($this->kunjungan, [$this->toraks->id], $this->dokter(), '   ');
    }

    public function test_pemeriksaan_yang_sama_tidak_boleh_dipesan_dua_kali(): void
    {
        $this->expectException(ValidationException::class);

        app(PemesananRadiologi::class)->pesan(
            $this->kunjungan, [$this->toraks->id, $this->toraks->id], $this->dokter(), 'Batuk kronis'
        );
    }

    public function test_tarif_disalin_saat_order_dibuat(): void
    {
        $order = app(PemesananRadiologi::class)
            ->pesan($this->kunjungan, [$this->toraks->id], $this->dokter(), 'Batuk kronis');

        $this->assertSame(150000, (int) $order->detail->first()->tarif_satuan);
    }

    public function test_perubahan_master_tarif_tidak_mengubah_order_yang_sudah_dibuat(): void
    {
        $order = app(PemesananRadiologi::class)
            ->pesan($this->kunjungan, [$this->toraks->id], $this->dokter(), 'Batuk kronis');

        Tarif::query()->update(['harga' => 250000]);

        $this->assertSame(150000, (int) $order->detail->first()->refresh()->tarif_satuan);
    }

    public function test_order_tidak_bisa_dibuat_pada_kunjungan_yang_sudah_selesai(): void
    {
        $selesai = Kunjungan::factory()->create([
            'penjamin_id' => $this->umum->id, 'status' => StatusKunjungan::Selesai,
        ]);

        $this->expectException(RuntimeException::class);

        app(PemesananRadiologi::class)->pesan($selesai, [$this->toraks->id], $this->dokter(), 'Batuk kronis');
    }

    public function test_pembatalan_wajib_menyertakan_alasan(): void
    {
        $order = app(PemesananRadiologi::class)
            ->pesan($this->kunjungan, [$this->toraks->id], $this->dokter(), 'Batuk kronis');

        $this->expectException(ValidationException::class);

        app(PemesananRadiologi::class)->batalkan($order, $this->dokter(), '   ');
    }

    public function test_alasan_pembatalan_tercatat_di_audit_log(): void
    {
        $order = app(PemesananRadiologi::class)
            ->pesan($this->kunjungan, [$this->toraks->id], $this->dokter(), 'Batuk kronis');

        app(PemesananRadiologi::class)->batalkan($order, $this->dokter(), 'Pasien hamil, ditunda');

        $this->assertSame(StatusOrderRadiologi::Batal, $order->refresh()->status);
        $this->assertDatabaseHas('audit_logs', ['alasan' => 'Pasien hamil, ditunda']);
    }

    public function test_order_yang_sudah_batal_tidak_bisa_dibatalkan_lagi(): void
    {
        $order = app(PemesananRadiologi::class)
            ->pesan($this->kunjungan, [$this->toraks->id], $this->dokter(), 'Batuk kronis');

        app(PemesananRadiologi::class)->batalkan($order, $this->dokter(), 'Salah pesan');

        $this->expectException(RuntimeException::class);

        app(PemesananRadiologi::class)->batalkan($order->refresh(), $this->dokter(), 'Sekali lagi');
    }
}
