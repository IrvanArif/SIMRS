<?php

namespace Tests\Feature;

use App\Enums\StatusKunjungan;
use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\User;
use App\Services\PenulisanResep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class ResepTest extends TestCase
{
    use RefreshDatabase;

    private function item(array $ganti = []): array
    {
        return [array_merge([
            'obat_id' => Obat::factory()->create()->id,
            'jumlah' => 10,
            'aturan_pakai' => '3x1 sesudah makan',
        ], $ganti)];
    }

    public function test_resep_bernomor_dan_terhubung_ke_kunjungan(): void
    {
        $kunjungan = Kunjungan::factory()->create();

        $resep = app(PenulisanResep::class)->tulis($kunjungan, $this->item(), User::factory()->create());

        $this->assertStringStartsWith('RS-', $resep->no_resep);
        $this->assertSame($kunjungan->id, $resep->kunjungan_id);
        $this->assertSame(1, $resep->detail()->count());
    }

    public function test_resep_wajib_memuat_minimal_satu_obat(): void
    {
        $this->expectException(ValidationException::class);

        app(PenulisanResep::class)->tulis(Kunjungan::factory()->create(), [], User::factory()->create());
    }

    public function test_aturan_pakai_wajib_diisi(): void
    {
        $this->expectException(ValidationException::class);

        app(PenulisanResep::class)->tulis(
            Kunjungan::factory()->create(),
            $this->item(['aturan_pakai' => '']),
            User::factory()->create()
        );
    }

    public function test_jumlah_obat_minimal_satu(): void
    {
        $this->expectException(ValidationException::class);

        app(PenulisanResep::class)->tulis(
            Kunjungan::factory()->create(),
            $this->item(['jumlah' => 0]),
            User::factory()->create()
        );
    }

    public function test_obat_yang_sama_tidak_boleh_muncul_dua_baris(): void
    {
        $obat = Obat::factory()->create();

        $this->expectException(ValidationException::class);

        app(PenulisanResep::class)->tulis(Kunjungan::factory()->create(), [
            ['obat_id' => $obat->id, 'jumlah' => 5, 'aturan_pakai' => '3x1'],
            ['obat_id' => $obat->id, 'jumlah' => 3, 'aturan_pakai' => '2x1'],
        ], User::factory()->create());
    }

    public function test_penulisan_ulang_mengganti_seluruh_rincian(): void
    {
        $kunjungan = Kunjungan::factory()->create();
        $dokter = User::factory()->create();
        $layanan = app(PenulisanResep::class);

        $pertama = $layanan->tulis($kunjungan, $this->item(), $dokter);
        $nomorAwal = $pertama->no_resep;

        $resep = $layanan->tulis($kunjungan, [
            ['obat_id' => Obat::factory()->create()->id, 'jumlah' => 6, 'aturan_pakai' => '2x1'],
            ['obat_id' => Obat::factory()->create()->id, 'jumlah' => 4, 'aturan_pakai' => '1x1'],
        ], $dokter);

        $this->assertSame(1, $kunjungan->refresh()->resep()->count());
        $this->assertSame(2, $resep->detail()->count());
        $this->assertSame($nomorAwal, $resep->no_resep);
    }

    public function test_resep_tidak_bisa_ditulis_pada_kunjungan_yang_sudah_selesai(): void
    {
        $kunjungan = Kunjungan::factory()->create(['status' => StatusKunjungan::Selesai]);

        $this->expectException(RuntimeException::class);

        app(PenulisanResep::class)->tulis($kunjungan, $this->item(), User::factory()->create());
    }
}
