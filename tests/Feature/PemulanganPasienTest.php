<?php

namespace Tests\Feature;

use App\Enums\CaraPulang;
use App\Enums\JenisDiagnosa;
use App\Enums\JenisLayanan;
use App\Enums\StatusKunjungan;
use App\Enums\StatusRawatInap;
use App\Models\Bed;
use App\Models\Icd10;
use App\Models\KelasKamar;
use App\Models\Kunjungan;
use App\Models\PemeriksaanLab;
use App\Models\PemeriksaanRadiologi;
use App\Models\Penjamin;
use App\Models\RawatInap;
use App\Models\Ruang;
use App\Models\Tarif;
use App\Models\User;
use App\Services\PelaksanaanRadiologi;
use App\Services\PemeriksaanKlinis;
use App\Services\PemesananLab;
use App\Services\PemesananRadiologi;
use App\Services\PemulanganPasien;
use App\Services\PenempatanBed;
use App\Services\PerintahRawatInap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class PemulanganPasienTest extends TestCase
{
    use RefreshDatabase;

    private Penjamin $umum;

    private KelasKamar $kelas;

    private Ruang $ruang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $this->ruang = Ruang::factory()->create(['nama' => 'Melati']);
        $this->kelas = KelasKamar::factory()->create(['nama' => 'Kelas 2']);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Kamar, 'layanan_id' => $this->kelas->id,
            'penjamin_id' => $this->umum->id, 'harga' => 300000, 'berlaku_mulai' => '2026-01-01',
        ]);
    }

    private function bed(string $nomor = '01'): Bed
    {
        return Bed::factory()->create([
            'ruang_id' => $this->ruang->id, 'kelas_kamar_id' => $this->kelas->id, 'nomor' => $nomor,
        ]);
    }

    /** Masa rawat yang sudah lengkap SOAP, diagnosa, dan menempati bed. */
    private function pasienDirawat(?Bed $bed = null): RawatInap
    {
        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]);
        $dokter = User::factory()->create(['dokter_id' => $kunjungan->dokter_id]);

        app(PemeriksaanKlinis::class)->catatSoap($kunjungan, [
            'subjective' => 'Muntah berulang', 'objective' => 'Turgor menurun',
            'assessment' => 'Dehidrasi sedang', 'plan' => 'Rawat inap, rehidrasi',
        ], $dokter);
        app(PemeriksaanKlinis::class)
            ->tambahDiagnosa($kunjungan, Icd10::factory()->create()->id, JenisDiagnosa::Primer);

        $rawatInap = app(PerintahRawatInap::class)
            ->terbitkan($kunjungan->refresh(), $dokter, 'Dehidrasi sedang', $this->kelas);

        app(PenempatanBed::class)->tempatkan($rawatInap, $bed ?? $this->bed(), User::factory()->create());

        return $rawatInap->refresh();
    }

    private function pulangkan(RawatInap $rawatInap, ?int $icd10Id = null, ?CaraPulang $cara = null): RawatInap
    {
        return app(PemulanganPasien::class)->pulangkan(
            $rawatInap,
            User::factory()->create(['dokter_id' => $rawatInap->kunjungan->dokter_id]),
            $icd10Id ?? Icd10::factory()->create()->id,
            $cara ?? CaraPulang::Sembuh,
            'Kondisi membaik, cairan tercukupi.'
        );
    }

    public function test_pemulangan_mencatat_waktu_cara_pulang_dan_pemulangnya(): void
    {
        $hasil = $this->pulangkan($this->pasienDirawat());

        $this->assertSame(StatusRawatInap::Pulang, $hasil->status);
        $this->assertSame(CaraPulang::Sembuh, $hasil->cara_pulang);
        $this->assertNotNull($hasil->waktu_pulang);
        $this->assertNotNull($hasil->dipulangkan_oleh);
        $this->assertNotNull($hasil->diagnosa_akhir_id);
    }

    public function test_pemulangan_wajib_menyertakan_diagnosa_akhir(): void
    {
        $rawatInap = $this->pasienDirawat();

        $this->expectException(ValidationException::class);

        app(PemulanganPasien::class)->pulangkan(
            $rawatInap, User::factory()->create(), 0, CaraPulang::Sembuh, null
        );
    }

    public function test_pasien_tidak_bisa_pulang_saat_order_lab_belum_selesai(): void
    {
        $rawatInap = $this->pasienDirawat();
        $pemeriksaan = PemeriksaanLab::factory()->create();

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Lab, 'layanan_id' => $pemeriksaan->id,
            'penjamin_id' => $this->umum->id, 'harga' => 75000, 'berlaku_mulai' => '2026-01-01',
        ]);

        $order = app(PemesananLab::class)
            ->pesan($rawatInap->kunjungan, [$pemeriksaan->id], User::factory()->create());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($order->no_order);

        $this->pulangkan($rawatInap);
    }

    public function test_pasien_tidak_bisa_pulang_saat_ekspertise_radiologi_belum_ditulis(): void
    {
        $rawatInap = $this->pasienDirawat();
        $pemeriksaan = PemeriksaanRadiologi::factory()->create();

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Radiologi, 'layanan_id' => $pemeriksaan->id,
            'penjamin_id' => $this->umum->id, 'harga' => 150000, 'berlaku_mulai' => '2026-01-01',
        ]);

        $order = app(PemesananRadiologi::class)
            ->pesan($rawatInap->kunjungan, [$pemeriksaan->id], User::factory()->create(), 'Curiga pneumonia');
        app(PelaksanaanRadiologi::class)->kerjakan($order, 'FILM-1', User::factory()->create());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($order->no_order);

        $this->pulangkan($rawatInap);
    }

    public function test_pemulangan_melepaskan_bed(): void
    {
        $bed = $this->bed('05');
        $rawatInap = $this->pasienDirawat($bed);

        $this->assertTrue($bed->refresh()->terisi());

        $this->pulangkan($rawatInap);

        $this->assertFalse($bed->refresh()->terisi());
        $this->assertNull($bed->refresh()->rawat_inap_id);
    }

    public function test_bed_yang_dilepas_bisa_ditempati_pasien_berikutnya(): void
    {
        $bed = $this->bed('05');
        $this->pulangkan($this->pasienDirawat($bed));

        $berikutnya = $this->pasienDirawat($bed->refresh());

        $this->assertSame($berikutnya->id, $bed->refresh()->rawat_inap_id);
    }

    public function test_pemulangan_menutup_penggal_okupansi_terakhir(): void
    {
        $rawatInap = $this->pasienDirawat();

        $this->pulangkan($rawatInap);

        $this->assertSame(0, $rawatInap->refresh()->okupansi()->berjalan()->count());
        $this->assertNotNull($rawatInap->refresh()->okupansi()->first()->selesai);
    }

    public function test_pemulangan_menyelesaikan_kunjungannya_dan_menyusun_tagihan(): void
    {
        $rawatInap = $this->pasienDirawat();

        $this->pulangkan($rawatInap);

        $kunjungan = $rawatInap->refresh()->kunjungan->refresh();

        $this->assertSame(StatusKunjungan::Selesai, $kunjungan->status);
        $this->assertNotNull($kunjungan->tagihan);
    }

    public function test_kunjungan_rawat_inap_tidak_bisa_ditutup_lewat_alur_poli(): void
    {
        $rawatInap = $this->pasienDirawat();
        $dokter = User::factory()->create(['dokter_id' => $rawatInap->kunjungan->dokter_id]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('pemulangan');

        app(PemeriksaanKlinis::class)->selesaikan($rawatInap->kunjungan->refresh(), $dokter);
    }

    public function test_masa_rawat_yang_sudah_pulang_tidak_bisa_dipulangkan_lagi(): void
    {
        $rawatInap = $this->pasienDirawat();
        $this->pulangkan($rawatInap);

        $this->expectException(RuntimeException::class);

        $this->pulangkan($rawatInap->refresh());
    }

    public function test_pemulangan_pasien_meninggal_tetap_tercatat_lengkap(): void
    {
        $hasil = $this->pulangkan($this->pasienDirawat(), cara: CaraPulang::Meninggal);

        // Cara pulang dibedakan karena artinya berbeda secara klinis maupun
        // pelaporan; meninggal bukan kesembuhan.
        $this->assertSame(CaraPulang::Meninggal, $hasil->cara_pulang);
        $this->assertSame(StatusRawatInap::Pulang, $hasil->status);
    }
}
