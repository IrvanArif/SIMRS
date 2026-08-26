<?php

namespace Tests\Feature;

use App\Enums\StatusKunjungan;
use App\Enums\StatusRawatInap;
use App\Models\KelasKamar;
use App\Models\Kunjungan;
use App\Models\RawatInap;
use App\Models\User;
use App\Services\PerintahRawatInap;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class PerintahRawatInapTest extends TestCase
{
    use RefreshDatabase;

    private KelasKamar $kelas;

    private Kunjungan $kunjungan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kelas = KelasKamar::factory()->create(['nama' => 'Kelas 2']);
        $this->kunjungan = Kunjungan::factory()->create();
    }

    private function dokter(): User
    {
        return User::factory()->create();
    }

    private function terbitkan(?Kunjungan $kunjungan = null, string $indikasi = 'Dehidrasi berat'): RawatInap
    {
        return app(PerintahRawatInap::class)->terbitkan(
            $kunjungan ?? $this->kunjungan, $this->dokter(), $indikasi, $this->kelas
        );
    }

    public function test_perintah_bernomor_dan_berstatus_dirawat(): void
    {
        $rawatInap = $this->terbitkan();

        $this->assertStringStartsWith('RI-', $rawatInap->no_rawat_inap);
        $this->assertSame(StatusRawatInap::Dirawat, $rawatInap->status);
        $this->assertSame('Dehidrasi berat', $rawatInap->indikasi);
    }

    public function test_perintah_rawat_inap_wajib_menyertakan_indikasi(): void
    {
        $this->expectException(ValidationException::class);

        $this->terbitkan(indikasi: '   ');
    }

    public function test_satu_kunjungan_hanya_boleh_punya_satu_masa_rawat(): void
    {
        $this->terbitkan();

        $this->expectException(RuntimeException::class);

        $this->terbitkan();
    }

    public function test_batasan_unik_menolak_masa_rawat_kedua_pada_kunjungan_yang_sama(): void
    {
        $pertama = $this->terbitkan();

        // Menembus service untuk membuktikan basis datanya sendiri menolak —
        // penjagaan di service saja bisa dilewati jalur tulis yang belum terbayang.
        $this->expectException(QueryException::class);

        RawatInap::create([
            'no_rawat_inap' => 'RI-20260826-9999',
            'kunjungan_id' => $pertama->kunjungan_id,
            'dokter_id' => $this->kunjungan->dokter_id,
            'kelas_diminta_id' => $this->kelas->id,
            'indikasi' => 'Menembus service',
            'status' => StatusRawatInap::Dirawat,
        ]);
    }

    public function test_perintah_tidak_bisa_diterbitkan_pada_kunjungan_yang_sudah_selesai(): void
    {
        $selesai = Kunjungan::factory()->create(['status' => StatusKunjungan::Selesai]);

        $this->expectException(RuntimeException::class);

        $this->terbitkan($selesai);
    }

    public function test_kunjungan_berpindah_ke_status_dalam_perawatan(): void
    {
        $this->terbitkan();

        $this->assertSame(StatusKunjungan::DalamPerawatan, $this->kunjungan->refresh()->status);
        // Kunjungannya tetap aktif: tindakan, resep, lab, dan radiologi masih
        // boleh ditambahkan selama pasien dirawat.
        $this->assertTrue($this->kunjungan->refresh()->status->aktif());
    }

    public function test_kelas_yang_diminta_tercatat(): void
    {
        $rawatInap = $this->terbitkan();

        $this->assertSame($this->kelas->id, $rawatInap->kelas_diminta_id);
        $this->assertSame('Kelas 2', $rawatInap->kelasDiminta->nama);
    }

    public function test_kunjungan_mengenali_dirinya_sedang_dirawat_inap(): void
    {
        $this->assertFalse($this->kunjungan->sedangDirawatInap());

        $this->terbitkan();

        $this->assertTrue($this->kunjungan->refresh()->sedangDirawatInap());
    }

    public function test_pembatalan_wajib_menyertakan_alasan(): void
    {
        $rawatInap = $this->terbitkan();

        $this->expectException(ValidationException::class);

        app(PerintahRawatInap::class)->batalkan($rawatInap, $this->dokter(), '   ');
    }

    public function test_alasan_pembatalan_tercatat_di_audit_log(): void
    {
        $rawatInap = $this->terbitkan();

        app(PerintahRawatInap::class)->batalkan($rawatInap, $this->dokter(), 'Pasien menolak dirawat');

        $this->assertSame(StatusRawatInap::Batal, $rawatInap->refresh()->status);
        $this->assertDatabaseHas('audit_logs', ['alasan' => 'Pasien menolak dirawat']);
    }

    public function test_pembatalan_mengembalikan_kunjungan_ke_status_diperiksa_dokter(): void
    {
        $rawatInap = $this->terbitkan();

        app(PerintahRawatInap::class)->batalkan($rawatInap, $this->dokter(), 'Pasien menolak dirawat');

        // Kunjungannya kembali menjadi kunjungan poli biasa, dan bisa ditutup
        // lewat alur rawat jalan seperti sedia kala.
        $this->assertSame(StatusKunjungan::DiperiksaDokter, $this->kunjungan->refresh()->status);
        $this->assertFalse($this->kunjungan->refresh()->sedangDirawatInap());
    }

    public function test_masa_rawat_yang_sudah_batal_tidak_bisa_dibatalkan_lagi(): void
    {
        $rawatInap = $this->terbitkan();
        app(PerintahRawatInap::class)->batalkan($rawatInap, $this->dokter(), 'Salah terbit');

        $this->expectException(RuntimeException::class);

        app(PerintahRawatInap::class)->batalkan($rawatInap->refresh(), $this->dokter(), 'Sekali lagi');
    }
}
