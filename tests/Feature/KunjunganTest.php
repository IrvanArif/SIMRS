<?php

namespace Tests\Feature;

use App\Enums\StatusKunjungan;
use App\Models\Dokter;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\Penjamin;
use App\Models\Poli;
use App\Services\PendaftaranKunjungan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class KunjunganTest extends TestCase
{
    use RefreshDatabase;

    private Poli $poli;
    private Dokter $dokter;
    private Penjamin $umum;
    private Penjamin $bpjs;
    private Pasien $pasien;

    protected function setUp(): void
    {
        parent::setUp();

        $this->poli = Poli::factory()->create(['kode' => 'UMU']);
        $this->dokter = Dokter::factory()->create(['poli_id' => $this->poli->id]);
        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $this->bpjs = Penjamin::factory()->create(['kode' => 'BPJS', 'jenis' => 'penjamin']);
        $this->pasien = Pasien::factory()->create();
    }

    private function daftarkan(array $ganti = []): Kunjungan
    {
        return app(PendaftaranKunjungan::class)->daftarkan(array_merge([
            'pasien_id' => $this->pasien->id,
            'poli_id' => $this->poli->id,
            'dokter_id' => $this->dokter->id,
            'penjamin_id' => $this->umum->id,
            'tanggal' => '2026-08-18',
        ], $ganti));
    }

    public function test_kunjungan_baru_bernomor_dan_berstatus_terdaftar(): void
    {
        $kunjungan = $this->daftarkan();

        $this->assertSame('KJ-20260818-0001', $kunjungan->no_kunjungan);
        $this->assertSame(StatusKunjungan::Terdaftar, $kunjungan->status);
    }

    public function test_kunjungan_pertama_pasien_ditandai_baru_dan_berikutnya_lama(): void
    {
        $pertama = $this->daftarkan();
        $this->assertSame('baru', $pertama->jenis_kunjungan);

        $pertama->update(['status' => StatusKunjungan::Selesai]);

        $this->assertSame('lama', $this->daftarkan(['tanggal' => '2026-08-19'])->jenis_kunjungan);
    }

    public function test_pasien_tidak_bisa_punya_dua_kunjungan_aktif_di_poli_yang_sama(): void
    {
        $this->daftarkan();

        $this->expectException(ValidationException::class);

        $this->daftarkan();
    }

    public function test_pasien_bisa_mendaftar_di_poli_lain_pada_hari_yang_sama(): void
    {
        $this->daftarkan();

        $poliGigi = Poli::factory()->create(['kode' => 'GIG']);
        $dokterGigi = Dokter::factory()->create(['poli_id' => $poliGigi->id]);

        $kedua = $this->daftarkan(['poli_id' => $poliGigi->id, 'dokter_id' => $dokterGigi->id]);

        $this->assertSame(StatusKunjungan::Terdaftar, $kedua->status);
    }

    public function test_pasien_bisa_mendaftar_lagi_setelah_kunjungan_sebelumnya_selesai(): void
    {
        $pertama = $this->daftarkan();
        $pertama->update(['status' => StatusKunjungan::Selesai]);

        $this->assertNotNull($this->daftarkan()->id);
    }

    public function test_kunjungan_bpjs_tanpa_nomor_kartu_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        $this->daftarkan(['penjamin_id' => $this->bpjs->id]);
    }

    public function test_kunjungan_bpjs_dengan_nomor_kartu_diterima(): void
    {
        $kunjungan = $this->daftarkan([
            'penjamin_id' => $this->bpjs->id,
            'no_kartu_penjamin' => '0001234567890',
        ]);

        $this->assertSame('0001234567890', $kunjungan->no_kartu_penjamin);
    }

    public function test_kunjungan_umum_tidak_wajib_nomor_kartu(): void
    {
        $this->assertNull($this->daftarkan()->no_kartu_penjamin);
    }

    public function test_dokter_harus_bertugas_di_poli_yang_dipilih(): void
    {
        $poliLain = Poli::factory()->create(['kode' => 'ANK']);

        $this->expectException(ValidationException::class);

        $this->daftarkan(['poli_id' => $poliLain->id]);
    }

    public function test_kunjungan_bisa_dibatalkan_selama_masih_terdaftar(): void
    {
        $kunjungan = $this->daftarkan();

        app(PendaftaranKunjungan::class)->batalkan($kunjungan);

        $this->assertSame(StatusKunjungan::Batal, $kunjungan->refresh()->status);
    }

    public function test_kunjungan_yang_sudah_diperiksa_tidak_bisa_dibatalkan(): void
    {
        $kunjungan = $this->daftarkan();
        $kunjungan->update(['status' => StatusKunjungan::DiperiksaPerawat]);

        $this->expectException(RuntimeException::class);

        app(PendaftaranKunjungan::class)->batalkan($kunjungan);
    }
}
