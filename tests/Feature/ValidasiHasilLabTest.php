<?php

namespace Tests\Feature;

use App\Enums\JenisDiagnosa;
use App\Enums\JenisLayanan;
use App\Enums\PenandaHasil;
use App\Enums\StatusKunjungan;
use App\Enums\StatusOrderLab;
use App\Models\HasilLab;
use App\Models\Icd10;
use App\Models\Kunjungan;
use App\Models\OrderLab;
use App\Models\ParameterLab;
use App\Models\PemeriksaanLab;
use App\Models\Penjamin;
use App\Models\RujukanLab;
use App\Models\Tarif;
use App\Models\User;
use App\Services\PemeriksaanKlinis;
use App\Services\PemeriksaanLaboratorium;
use App\Services\PemesananLab;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class ValidasiHasilLabTest extends TestCase
{
    use RefreshDatabase;

    private PemeriksaanLab $darahRutin;
    private ParameterLab $hemoglobin;
    private Penjamin $umum;

    protected function setUp(): void
    {
        parent::setUp();

        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $this->darahRutin = PemeriksaanLab::factory()->create(['nama' => 'Darah Rutin']);

        $this->hemoglobin = ParameterLab::factory()->create([
            'pemeriksaan_lab_id' => $this->darahRutin->id,
            'kode' => 'HB', 'nama' => 'Hemoglobin', 'satuan' => 'g/dL',
        ]);

        RujukanLab::factory()->create([
            'parameter_lab_id' => $this->hemoglobin->id,
            'jenis_kelamin' => 'semua', 'nilai_min' => 12.0, 'nilai_maks' => 17.0,
        ]);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Lab, 'layanan_id' => $this->darahRutin->id,
            'penjamin_id' => $this->umum->id, 'harga' => 75000, 'berlaku_mulai' => '2026-01-01',
        ]);
    }

    /** @return array{0: Kunjungan, 1: OrderLab, 2: User} */
    private function orderBerhasil(): array
    {
        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]);
        $analis = User::factory()->create();

        $order = app(PemesananLab::class)
            ->pesan($kunjungan, [$this->darahRutin->id], User::factory()->create());

        $lab = app(PemeriksaanLaboratorium::class);
        $lab->ambilSampel($order, $analis);
        $lab->entriHasil($order->refresh(), [$this->hemoglobin->id => 14.0], $analis);

        return [$kunjungan, $order->refresh(), $analis];
    }

    private function isiSoapDanDiagnosa(Kunjungan $kunjungan): User
    {
        $dokter = User::factory()->create(['dokter_id' => $kunjungan->dokter_id]);
        $klinis = app(PemeriksaanKlinis::class);

        $klinis->catatSoap($kunjungan, [
            'subjective' => 'Lemas', 'objective' => 'Konjungtiva pucat',
            'assessment' => 'Anemia', 'plan' => 'Terapi besi',
        ], $dokter);
        $klinis->tambahDiagnosa($kunjungan, Icd10::factory()->create()->id, JenisDiagnosa::Primer);

        return $dokter;
    }

    public function test_validasi_mencatat_waktu_dan_pelakunya(): void
    {
        [$kunjungan, $order, $analis] = $this->orderBerhasil();

        $divalidasi = app(PemeriksaanLaboratorium::class)->validasi($order, $analis);

        $this->assertSame(StatusOrderLab::Divalidasi, $divalidasi->status);
        $this->assertNotNull($divalidasi->waktu_validasi);
        $this->assertSame($analis->id, $divalidasi->divalidasi_oleh);
    }

    public function test_hasil_belum_divalidasi_tidak_terbaca_dokter(): void
    {
        [$kunjungan, $order] = $this->orderBerhasil();

        $this->assertFalse($order->terbacaDokter());

        app(PemeriksaanLaboratorium::class)->validasi($order, User::factory()->create());

        $this->assertTrue($order->refresh()->terbacaDokter());
    }

    public function test_validasi_oleh_petugas_yang_sama_diperbolehkan_dan_kedua_pelakunya_tercatat(): void
    {
        [$kunjungan, $order, $analis] = $this->orderBerhasil();

        $divalidasi = app(PemeriksaanLaboratorium::class)->validasi($order, $analis);

        $this->assertSame($analis->id, $divalidasi->dientri_oleh);
        $this->assertSame($analis->id, $divalidasi->divalidasi_oleh);
    }

    public function test_order_yang_belum_ada_hasilnya_tidak_bisa_divalidasi(): void
    {
        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]);
        $order = app(PemesananLab::class)
            ->pesan($kunjungan, [$this->darahRutin->id], User::factory()->create());

        $this->expectException(RuntimeException::class);

        app(PemeriksaanLaboratorium::class)->validasi($order, User::factory()->create());
    }

    public function test_hasil_tervalidasi_tidak_bisa_dientri_ulang_lewat_jalur_biasa(): void
    {
        [$kunjungan, $order, $analis] = $this->orderBerhasil();
        app(PemeriksaanLaboratorium::class)->validasi($order, $analis);

        $this->expectException(RuntimeException::class);

        app(PemeriksaanLaboratorium::class)
            ->entriHasil($order->refresh(), [$this->hemoglobin->id => 9.0], $analis);
    }

    public function test_koreksi_hasil_tervalidasi_wajib_beralasan(): void
    {
        [$kunjungan, $order, $analis] = $this->orderBerhasil();
        app(PemeriksaanLaboratorium::class)->validasi($order, $analis);

        $this->expectException(ValidationException::class);

        app(PemeriksaanLaboratorium::class)
            ->koreksi($order->refresh(), [$this->hemoglobin->id => 9.0], $analis, '   ');
    }

    public function test_koreksi_mengubah_nilai_dan_tercatat_di_audit_log(): void
    {
        [$kunjungan, $order, $analis] = $this->orderBerhasil();
        app(PemeriksaanLaboratorium::class)->validasi($order, $analis);

        app(PemeriksaanLaboratorium::class)->koreksi(
            $order->refresh(), [$this->hemoglobin->id => 9.0], $analis, 'Salah baca alat'
        );

        $baris = HasilLab::where('parameter_lab_id', $this->hemoglobin->id)->first();

        $this->assertSame(9.0, (float) $baris->nilai);
        $this->assertSame(PenandaHasil::Rendah, $baris->penanda);
        $this->assertDatabaseHas('audit_logs', ['alasan' => 'Salah baca alat']);
    }

    public function test_koreksi_hanya_berlaku_untuk_hasil_yang_sudah_divalidasi(): void
    {
        [$kunjungan, $order, $analis] = $this->orderBerhasil();

        $this->expectException(RuntimeException::class);

        app(PemeriksaanLaboratorium::class)
            ->koreksi($order, [$this->hemoglobin->id => 9.0], $analis, 'Salah baca');
    }

    public function test_kunjungan_tidak_bisa_diselesaikan_saat_hasil_lab_belum_divalidasi(): void
    {
        [$kunjungan, $order, $analis] = $this->orderBerhasil();
        $dokter = $this->isiSoapDanDiagnosa($kunjungan);

        try {
            app(PemeriksaanKlinis::class)->selesaikan($kunjungan->refresh(), $dokter);
            $this->fail('Kunjungan seharusnya ditolak karena hasil lab belum divalidasi.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString($order->no_order, $e->getMessage());
        }
    }

    public function test_kunjungan_bisa_diselesaikan_setelah_seluruh_order_divalidasi(): void
    {
        [$kunjungan, $order, $analis] = $this->orderBerhasil();
        app(PemeriksaanLaboratorium::class)->validasi($order, $analis);

        $dokter = $this->isiSoapDanDiagnosa($kunjungan);
        $selesai = app(PemeriksaanKlinis::class)->selesaikan($kunjungan->refresh(), $dokter);

        $this->assertSame(StatusKunjungan::Selesai, $selesai->status);
    }

    public function test_order_yang_dibatalkan_tidak_menahan_penyelesaian_kunjungan(): void
    {
        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]);
        $order = app(PemesananLab::class)
            ->pesan($kunjungan, [$this->darahRutin->id], User::factory()->create());

        app(PemesananLab::class)->batalkan($order, User::factory()->create(), 'Salah pesan');

        $dokter = $this->isiSoapDanDiagnosa($kunjungan);

        $this->assertSame(
            StatusKunjungan::Selesai,
            app(PemeriksaanKlinis::class)->selesaikan($kunjungan->refresh(), $dokter)->status
        );
    }
}
