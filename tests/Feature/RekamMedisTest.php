<?php

namespace Tests\Feature;

use App\Enums\JenisDiagnosa;
use App\Enums\StatusKunjungan;
use App\Models\AuditLog;
use App\Models\Dokter;
use App\Models\Icd10;
use App\Models\Kunjungan;
use App\Models\User;
use App\Services\PemeriksaanKlinis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class RekamMedisTest extends TestCase
{
    use RefreshDatabase;

    private Kunjungan $kunjungan;
    private User $dokter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kunjungan = Kunjungan::factory()->create();
        $this->dokter = User::factory()->create(['dokter_id' => $this->kunjungan->dokter_id]);
    }

    private function layanan(): PemeriksaanKlinis
    {
        return app(PemeriksaanKlinis::class);
    }

    private function soap(array $ganti = []): array
    {
        return array_merge([
            'subjective' => 'Batuk berdahak tiga hari, tidak demam',
            'objective' => 'Faring hiperemis, paru vesikuler',
            'assessment' => 'ISPA',
            'plan' => 'Antibiotik dan obat batuk, kontrol bila memberat',
        ], $ganti);
    }

    private function lengkapiSoapDanDiagnosa(): void
    {
        $this->layanan()->catatSoap($this->kunjungan, $this->soap(), $this->dokter);
        $this->layanan()->tambahDiagnosa(
            $this->kunjungan,
            Icd10::factory()->create()->id,
            JenisDiagnosa::Primer
        );
    }

    public function test_dokter_mencatat_soap_dan_status_kunjungan_berubah(): void
    {
        $pemeriksaan = $this->layanan()->catatSoap($this->kunjungan, $this->soap(), $this->dokter);

        $this->assertSame('ISPA', $pemeriksaan->assessment);
        $this->assertSame($this->dokter->id, $pemeriksaan->dicatat_dokter_id);
        $this->assertSame(StatusKunjungan::DiperiksaDokter, $this->kunjungan->refresh()->status);
    }

    public function test_soap_yang_tidak_lengkap_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        $this->layanan()->catatSoap($this->kunjungan, $this->soap(['plan' => '']), $this->dokter);
    }

    public function test_kunjungan_hanya_boleh_punya_satu_diagnosa_primer(): void
    {
        $this->layanan()->tambahDiagnosa($this->kunjungan, Icd10::factory()->create()->id, JenisDiagnosa::Primer);

        $this->expectException(ValidationException::class);

        $this->layanan()->tambahDiagnosa($this->kunjungan, Icd10::factory()->create()->id, JenisDiagnosa::Primer);
    }

    public function test_diagnosa_sekunder_boleh_lebih_dari_satu(): void
    {
        $this->layanan()->tambahDiagnosa($this->kunjungan, Icd10::factory()->create()->id, JenisDiagnosa::Primer);
        $this->layanan()->tambahDiagnosa($this->kunjungan, Icd10::factory()->create()->id, JenisDiagnosa::Sekunder);
        $this->layanan()->tambahDiagnosa($this->kunjungan, Icd10::factory()->create()->id, JenisDiagnosa::Sekunder);

        $this->assertSame(3, $this->kunjungan->diagnosa()->count());
    }

    public function test_kode_diagnosa_yang_sama_tidak_bisa_ditambahkan_dua_kali(): void
    {
        $icd = Icd10::factory()->create();
        $this->layanan()->tambahDiagnosa($this->kunjungan, $icd->id, JenisDiagnosa::Primer);

        $this->expectException(ValidationException::class);

        $this->layanan()->tambahDiagnosa($this->kunjungan, $icd->id, JenisDiagnosa::Sekunder);
    }

    public function test_kunjungan_tanpa_soap_tidak_bisa_diselesaikan(): void
    {
        $this->layanan()->tambahDiagnosa($this->kunjungan, Icd10::factory()->create()->id, JenisDiagnosa::Primer);

        $this->expectException(RuntimeException::class);

        $this->layanan()->selesaikan($this->kunjungan, $this->dokter);
    }

    public function test_kunjungan_tanpa_diagnosa_primer_tidak_bisa_diselesaikan(): void
    {
        $this->layanan()->catatSoap($this->kunjungan, $this->soap(), $this->dokter);

        $this->expectException(RuntimeException::class);

        $this->layanan()->selesaikan($this->kunjungan, $this->dokter);
    }

    public function test_kunjungan_dengan_soap_dan_diagnosa_lengkap_bisa_diselesaikan(): void
    {
        $this->lengkapiSoapDanDiagnosa();

        $selesai = $this->layanan()->selesaikan($this->kunjungan, $this->dokter);

        $this->assertSame(StatusKunjungan::Selesai, $selesai->status);
        $this->assertNotNull($selesai->waktu_selesai);
    }

    public function test_data_klinis_terkunci_setelah_kunjungan_selesai(): void
    {
        $this->lengkapiSoapDanDiagnosa();
        $this->layanan()->selesaikan($this->kunjungan, $this->dokter);

        $this->expectException(RuntimeException::class);

        $this->layanan()->catatSoap($this->kunjungan, $this->soap(['plan' => 'Diubah diam-diam']), $this->dokter);
    }

    public function test_koreksi_setelah_selesai_wajib_menyertakan_alasan_dan_tercatat_di_audit(): void
    {
        $this->lengkapiSoapDanDiagnosa();
        $this->layanan()->selesaikan($this->kunjungan, $this->dokter);

        $this->layanan()->koreksi(
            $this->kunjungan,
            $this->soap(['assessment' => 'ISPA dengan faringitis akut']),
            $this->dokter,
            'Assessment kurang spesifik saat pemeriksaan'
        );

        $catatan = AuditLog::where('aksi', 'update')->latest('id')->first();

        $this->assertSame('Assessment kurang spesifik saat pemeriksaan', $catatan->alasan);
        $this->assertSame('ISPA dengan faringitis akut', $this->kunjungan->refresh()->pemeriksaan->assessment);
    }

    public function test_koreksi_tanpa_alasan_ditolak(): void
    {
        $this->lengkapiSoapDanDiagnosa();
        $this->layanan()->selesaikan($this->kunjungan, $this->dokter);

        $this->expectException(ValidationException::class);

        $this->layanan()->koreksi($this->kunjungan, $this->soap(), $this->dokter, '   ');
    }

    public function test_koreksi_oleh_dokter_lain_ditolak(): void
    {
        $this->lengkapiSoapDanDiagnosa();
        $this->layanan()->selesaikan($this->kunjungan, $this->dokter);

        $dokterLain = User::factory()->create(['dokter_id' => Dokter::factory()->create()->id]);

        $this->expectException(RuntimeException::class);

        $this->layanan()->koreksi($this->kunjungan, $this->soap(), $dokterLain, 'Iseng mengubah');
    }
}
