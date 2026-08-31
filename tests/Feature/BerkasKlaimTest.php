<?php

namespace Tests\Feature;

use App\Enums\CaraPulang;
use App\Enums\JenisDiagnosa;
use App\Enums\JenisLayanan;
use App\Enums\JenisPelayanan;
use App\Enums\StatusBerkasKlaim;
use App\Models\BerkasKlaim;
use App\Models\Bed;
use App\Models\Icd10;
use App\Models\Icd9;
use App\Models\KelasKamar;
use App\Models\Kunjungan;
use App\Models\Penjamin;
use App\Models\Ruang;
use App\Models\Tarif;
use App\Models\Tindakan;
use App\Models\User;
use App\Services\PemeriksaanKlinis;
use App\Services\PemulanganPasien;
use App\Services\PenempatanBed;
use App\Services\PenerbitanSep;
use App\Services\PenyusunBerkasKlaim;
use App\Services\PerintahRawatInap;
use App\Services\TindakanPelayanan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class BerkasKlaimTest extends TestCase
{
    use RefreshDatabase;

    private Penjamin $bpjs;

    private Tindakan $infus;

    private Tindakan $konsultasi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bpjs = Penjamin::factory()->create([
            'kode' => 'BPJS', 'nama' => 'BPJS Kesehatan', 'jenis' => 'penjamin',
        ]);

        $icd9 = Icd9::factory()->create(['kode' => '38.93', 'nama' => 'Pemasangan infus']);
        $this->infus = Tindakan::factory()->create(['nama' => 'Pemasangan Infus', 'icd9_id' => $icd9->id]);
        // Sengaja tanpa pemetaan ICD-9: konsultasi bukan prosedur.
        $this->konsultasi = Tindakan::factory()->create(['nama' => 'Konsultasi Dokter Umum', 'icd9_id' => null]);

        foreach ([[$this->infus->id, 75000], [$this->konsultasi->id, 50000]] as [$layananId, $harga]) {
            Tarif::factory()->create([
                'jenis_layanan' => JenisLayanan::Tindakan, 'layanan_id' => $layananId,
                'penjamin_id' => $this->bpjs->id, 'harga' => $harga, 'berlaku_mulai' => '2026-01-01',
            ]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function kunjunganBpjs(): Kunjungan
    {
        return Kunjungan::factory()->create([
            'penjamin_id' => $this->bpjs->id,
            'no_kartu_penjamin' => '0001234567890',
        ]);
    }

    /** Kunjungan rawat jalan yang lengkap: SEP, diagnosa, tindakan, tagihan. */
    private function kunjunganSiapKlaim(bool $denganSep = true, bool $denganDiagnosa = true): Kunjungan
    {
        $kunjungan = $this->kunjunganBpjs();
        $dokter = User::factory()->create(['dokter_id' => $kunjungan->dokter_id]);

        if ($denganSep) {
            app(PenerbitanSep::class)->terbitkan($kunjungan, User::factory()->create(), 'Demam tifoid');
        }

        app(TindakanPelayanan::class)->tambah($kunjungan, $this->infus->id, 1, $dokter);
        app(TindakanPelayanan::class)->tambah($kunjungan, $this->konsultasi->id, 1, $dokter);

        $klinis = app(PemeriksaanKlinis::class);
        $klinis->catatSoap($kunjungan, [
            'subjective' => 'Demam lima hari', 'objective' => 'Suhu 38,9',
            'assessment' => 'Suspek demam tifoid', 'plan' => 'Terapi dan rehidrasi',
        ], $dokter);

        if ($denganDiagnosa) {
            $klinis->tambahDiagnosa($kunjungan, Icd10::factory()->create(['kode' => 'A01.0'])->id, JenisDiagnosa::Primer);
            $klinis->tambahDiagnosa($kunjungan, Icd10::factory()->create(['kode' => 'E86'])->id, JenisDiagnosa::Sekunder);
            $klinis->selesaikan($kunjungan->refresh(), $dokter);
        }

        return $kunjungan->refresh();
    }

    private function susun(?Kunjungan $kunjungan = null): BerkasKlaim
    {
        return app(PenyusunBerkasKlaim::class)
            ->susun($kunjungan ?? $this->kunjunganSiapKlaim(), User::factory()->create());
    }

    public function test_berkas_klaim_bernomor_dan_berstatus_draf(): void
    {
        $berkas = $this->susun();

        $this->assertStringStartsWith('KL-', $berkas->no_berkas);
        $this->assertSame(StatusBerkasKlaim::Draf, $berkas->status);
        $this->assertSame(JenisPelayanan::RawatJalan, $berkas->jenis_pelayanan);
    }

    public function test_klaim_tidak_bisa_disusun_dari_kunjungan_yang_belum_selesai(): void
    {
        $kunjungan = $this->kunjunganSiapKlaim(denganDiagnosa: false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('belum selesai');

        $this->susun($kunjungan);
    }

    public function test_klaim_menolak_kunjungan_tanpa_sep_dan_menyebutkan_kekurangannya(): void
    {
        $kunjungan = $this->kunjunganSiapKlaim(denganSep: false);

        try {
            $this->susun($kunjungan);
            $this->fail('Berkas tanpa SEP seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('SEP', implode(' ', $e->errors()['berkas']));
        }
    }

    public function test_pesan_kekurangan_memuat_seluruh_kekurangan_sekaligus(): void
    {
        // Penolakan yang menyebut satu kekurangan, lalu kekurangan berikutnya
        // setelah diperbaiki, memaksa petugas bolak-balik. Seluruhnya dikumpulkan
        // dulu, baru dilaporkan bersama (aturan 85).
        $kunjungan = $this->kunjunganBpjs();
        $dokter = User::factory()->create(['dokter_id' => $kunjungan->dokter_id]);

        app(TindakanPelayanan::class)->tambah($kunjungan, $this->infus->id, 1, $dokter);
        app(PemeriksaanKlinis::class)->catatSoap($kunjungan, [
            'subjective' => 'a', 'objective' => 'b', 'assessment' => 'c', 'plan' => 'd',
        ], $dokter);
        app(PemeriksaanKlinis::class)
            ->tambahDiagnosa($kunjungan, Icd10::factory()->create()->id, JenisDiagnosa::Sekunder);
        $kunjungan->update(['status' => \App\Enums\StatusKunjungan::Selesai]);

        try {
            $this->susun($kunjungan->refresh());
            $this->fail('Berkas tanpa SEP, tagihan, dan diagnosa primer seharusnya ditolak.');
        } catch (ValidationException $e) {
            $pesan = implode(' ', $e->errors()['berkas']);

            $this->assertStringContainsString('SEP', $pesan);
            $this->assertStringContainsString('tagihan', $pesan);
            $this->assertStringContainsString('primer', $pesan);
        }
    }

    public function test_klaim_menyalin_diagnosa_primer_dan_sekunder(): void
    {
        $berkas = $this->susun();

        $this->assertSame('A01.0', $berkas->diagnosa()->where('jenis', 'primer')->first()->kode);
        $this->assertSame('E86', $berkas->diagnosa()->where('jenis', 'sekunder')->first()->kode);
        $this->assertSame(2, $berkas->diagnosa()->count());
    }

    public function test_klaim_memuat_prosedur_icd9_dari_pemetaan_tindakan(): void
    {
        $berkas = $this->susun();

        $this->assertSame(['38.93'], $berkas->prosedur()->pluck('kode')->all());
        $this->assertSame('Pemasangan infus', $berkas->prosedur()->first()->nama);
    }

    public function test_tindakan_tanpa_pemetaan_icd9_tidak_menggagalkan_klaim(): void
    {
        // Konsultasi tidak punya padanan prosedur, dan itu tidak boleh membuat
        // berkasnya ditolak (aturan 88).
        $berkas = $this->susun();

        $this->assertSame(StatusBerkasKlaim::Draf, $berkas->status);
        $this->assertSame(1, $berkas->prosedur()->count());
    }

    public function test_tindakan_tanpa_pemetaan_dicatat_sebagai_peringatan(): void
    {
        $berkas = $this->susun();

        $this->assertStringContainsString('Konsultasi Dokter Umum', (string) $berkas->peringatan);
    }

    public function test_klaim_menyalin_total_biaya_dari_tagihan(): void
    {
        $berkas = $this->susun();

        // 75.000 infus + 50.000 konsultasi
        $this->assertSame(125000, (int) $berkas->total_biaya);
    }

    public function test_klaim_menyalin_identitas_peserta_dan_sep(): void
    {
        $kunjungan = $this->kunjunganSiapKlaim();
        $berkas = $this->susun($kunjungan);

        $this->assertSame('0001234567890', $berkas->no_kartu);
        $this->assertSame($kunjungan->sepBerlaku()->id, $berkas->sep_id);
        $this->assertSame($kunjungan->pasien->nama, $berkas->nama_peserta);
    }

    public function test_satu_kunjungan_hanya_boleh_punya_satu_berkas_berlaku(): void
    {
        $kunjungan = $this->kunjunganSiapKlaim();
        $this->susun($kunjungan);

        $this->expectException(RuntimeException::class);

        $this->susun($kunjungan->refresh());
    }

    public function test_batasan_unik_menolak_berkas_kedua_yang_berlaku(): void
    {
        $pertama = $this->susun();

        $this->expectException(QueryException::class);

        BerkasKlaim::create([
            'no_berkas' => 'KL-20260101-9999',
            'kunjungan_id' => $pertama->kunjungan_id,
            'sep_id' => $pertama->sep_id,
            'no_kartu' => '0001234567890',
            'nama_peserta' => 'Menembus service',
            'jenis_pelayanan' => JenisPelayanan::RawatJalan,
            'tanggal_masuk' => now()->toDateString(),
            'total_biaya' => 1,
            'status' => StatusBerkasKlaim::Draf,
        ]);
    }

    public function test_berkas_yang_sudah_diajukan_tidak_bisa_disunting(): void
    {
        $berkas = $this->susun();
        app(PenyusunBerkasKlaim::class)->ajukan($berkas, User::factory()->create());

        $this->assertFalse($berkas->refresh()->status->bisaDisunting());
        $this->assertNotNull($berkas->refresh()->diajukan_pada);
    }

    public function test_berkas_yang_sudah_diajukan_tidak_bisa_diajukan_lagi(): void
    {
        $berkas = $this->susun();
        app(PenyusunBerkasKlaim::class)->ajukan($berkas, User::factory()->create());

        $this->expectException(RuntimeException::class);

        app(PenyusunBerkasKlaim::class)->ajukan($berkas->refresh(), User::factory()->create());
    }

    public function test_pembatalan_wajib_beralasan_dan_tercatat_di_audit_log(): void
    {
        $berkas = $this->susun();

        try {
            app(PenyusunBerkasKlaim::class)->batalkan($berkas, User::factory()->create(), '   ');
            $this->fail('Pembatalan tanpa alasan seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('alasan', $e->errors());
        }

        app(PenyusunBerkasKlaim::class)->batalkan($berkas, User::factory()->create(), 'Salah kode diagnosa');

        $this->assertDatabaseHas('audit_logs', ['alasan' => 'Salah kode diagnosa']);
    }

    public function test_pembatalan_membuka_jalan_untuk_penyusunan_ulang(): void
    {
        $kunjungan = $this->kunjunganSiapKlaim();
        $lama = $this->susun($kunjungan);

        app(PenyusunBerkasKlaim::class)->ajukan($lama, User::factory()->create());
        app(PenyusunBerkasKlaim::class)->batalkan($lama->refresh(), User::factory()->create(), 'Dikembalikan verifikator');

        $baru = $this->susun($kunjungan->refresh());

        $this->assertNotSame($lama->no_berkas, $baru->no_berkas);
        $this->assertSame(2, BerkasKlaim::count());
    }

    public function test_hasil_verifikasi_disetujui_tercatat(): void
    {
        $berkas = $this->susun();
        app(PenyusunBerkasKlaim::class)->ajukan($berkas, User::factory()->create());

        $hasil = app(PenyusunBerkasKlaim::class)->tandaiHasil(
            $berkas->refresh(), StatusBerkasKlaim::Disetujui, User::factory()->create(), null
        );

        $this->assertSame(StatusBerkasKlaim::Disetujui, $hasil->status);
        $this->assertNotNull($hasil->diverifikasi_pada);
    }

    public function test_hasil_verifikasi_ditolak_wajib_bercatatan(): void
    {
        $berkas = $this->susun();
        app(PenyusunBerkasKlaim::class)->ajukan($berkas, User::factory()->create());

        // Penolakan tanpa alasan tidak bisa ditindaklanjuti siapa pun.
        $this->expectException(ValidationException::class);

        app(PenyusunBerkasKlaim::class)->tandaiHasil(
            $berkas->refresh(), StatusBerkasKlaim::Ditolak, User::factory()->create(), '   '
        );
    }

    public function test_hasil_hanya_bisa_ditandai_pada_berkas_yang_sudah_diajukan(): void
    {
        $berkas = $this->susun();

        $this->expectException(RuntimeException::class);

        app(PenyusunBerkasKlaim::class)->tandaiHasil(
            $berkas, StatusBerkasKlaim::Disetujui, User::factory()->create(), null
        );
    }

    public function test_klaim_rawat_inap_menyalin_kelas_tanggal_dan_lama_rawat(): void
    {
        Carbon::setTestNow('2026-05-01 08:00:00');

        $kunjungan = $this->kunjunganBpjs();
        $dokter = User::factory()->create(['dokter_id' => $kunjungan->dokter_id]);
        $kelas = KelasKamar::factory()->create(['nama' => 'Kelas 2']);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Kamar, 'layanan_id' => $kelas->id,
            'penjamin_id' => $this->bpjs->id, 'harga' => 210000, 'berlaku_mulai' => '2026-01-01',
        ]);

        app(PemeriksaanKlinis::class)->catatSoap($kunjungan, [
            'subjective' => 'Demam', 'objective' => 'Suhu 39',
            'assessment' => 'Tifoid', 'plan' => 'Rawat inap',
        ], $dokter);
        app(PemeriksaanKlinis::class)
            ->tambahDiagnosa($kunjungan, Icd10::factory()->create(['kode' => 'A01.0'])->id, JenisDiagnosa::Primer);

        $rawatInap = app(PerintahRawatInap::class)
            ->terbitkan($kunjungan->refresh(), $dokter, 'Demam tifoid', $kelas);

        app(PenempatanBed::class)->tempatkan($rawatInap, Bed::factory()->create([
            'ruang_id' => Ruang::factory()->create()->id, 'kelas_kamar_id' => $kelas->id,
        ]), User::factory()->create());

        app(PenerbitanSep::class)->terbitkan($kunjungan->refresh(), User::factory()->create(), 'Demam tifoid');

        Carbon::setTestNow('2026-05-05 10:00:00');
        app(PemulanganPasien::class)->pulangkan(
            $rawatInap->refresh(), $dokter, Icd10::factory()->create()->id, CaraPulang::Sembuh, null
        );

        $berkas = $this->susun($kunjungan->refresh());

        $this->assertSame(JenisPelayanan::RawatInap, $berkas->jenis_pelayanan);
        $this->assertSame('Kelas 2', $berkas->kelas_rawat);
        $this->assertSame('2026-05-01', $berkas->tanggal_masuk->toDateString());
        $this->assertSame('2026-05-05', $berkas->tanggal_pulang->toDateString());
        $this->assertSame(4, $berkas->lama_rawat);
    }
}
