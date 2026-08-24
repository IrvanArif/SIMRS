<?php

namespace Tests\Feature;

use App\Enums\JenisLayanan;
use App\Enums\PenandaHasil;
use App\Enums\StatusOrderLab;
use App\Models\HasilLab;
use App\Models\Kunjungan;
use App\Models\OrderLab;
use App\Models\ParameterLab;
use App\Models\Pasien;
use App\Models\PemeriksaanLab;
use App\Models\Penjamin;
use App\Models\RujukanLab;
use App\Models\Tarif;
use App\Models\User;
use App\Services\PemeriksaanLaboratorium;
use App\Services\PemesananLab;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class EntriHasilLabTest extends TestCase
{
    use RefreshDatabase;

    private PemeriksaanLab $darahRutin;
    private ParameterLab $hemoglobin;
    private Penjamin $umum;

    protected function setUp(): void
    {
        parent::setUp();

        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $this->darahRutin = PemeriksaanLab::factory()->create([
            'nama' => 'Darah Rutin', 'kategori' => 'hematologi',
        ]);

        $this->hemoglobin = ParameterLab::factory()->create([
            'pemeriksaan_lab_id' => $this->darahRutin->id,
            'kode' => 'HB', 'nama' => 'Hemoglobin', 'satuan' => 'g/dL',
        ]);

        RujukanLab::factory()->create([
            'parameter_lab_id' => $this->hemoglobin->id,
            'jenis_kelamin' => 'L', 'nilai_min' => 13.0, 'nilai_maks' => 17.0,
        ]);
        RujukanLab::factory()->create([
            'parameter_lab_id' => $this->hemoglobin->id,
            'jenis_kelamin' => 'P', 'nilai_min' => 12.0, 'nilai_maks' => 15.0,
        ]);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Lab, 'layanan_id' => $this->darahRutin->id,
            'penjamin_id' => $this->umum->id, 'harga' => 75000, 'berlaku_mulai' => '2026-01-01',
        ]);
    }

    private function order(string $jenisKelamin = 'L'): OrderLab
    {
        $pasien = Pasien::factory()->create(['jenis_kelamin' => $jenisKelamin]);
        $kunjungan = Kunjungan::factory()->create([
            'pasien_id' => $pasien->id, 'penjamin_id' => $this->umum->id,
        ]);

        return app(PemesananLab::class)
            ->pesan($kunjungan, [$this->darahRutin->id], User::factory()->create());
    }

    private function analis(): User
    {
        return User::factory()->create();
    }

    public function test_pengambilan_sampel_mencatat_waktu_dan_pelakunya(): void
    {
        $order = $this->order();
        $analis = $this->analis();

        $hasil = app(PemeriksaanLaboratorium::class)->ambilSampel($order, $analis);

        $this->assertSame(StatusOrderLab::SampelDiambil, $hasil->status);
        $this->assertNotNull($hasil->waktu_sampel);
        $this->assertSame($analis->id, $hasil->diambil_oleh);
    }

    public function test_hasil_tidak_bisa_dientri_sebelum_sampel_diambil(): void
    {
        $order = $this->order();

        $this->expectException(RuntimeException::class);

        app(PemeriksaanLaboratorium::class)
            ->entriHasil($order, [$this->hemoglobin->id => 14.0], $this->analis());
    }

    public function test_entri_hasil_menyimpan_nilai_dan_penandanya(): void
    {
        $order = $this->order('L');
        $lab = app(PemeriksaanLaboratorium::class);
        $analis = $this->analis();

        $lab->ambilSampel($order, $analis);
        $hasil = $lab->entriHasil($order->refresh(), [$this->hemoglobin->id => 14.0], $analis);

        $this->assertSame(StatusOrderLab::HasilDientri, $hasil->status);
        $this->assertSame($analis->id, $hasil->dientri_oleh);
        $this->assertNotNull($hasil->waktu_hasil);

        $baris = HasilLab::where('parameter_lab_id', $this->hemoglobin->id)->first();

        $this->assertSame(14.0, (float) $baris->nilai);
        $this->assertSame(PenandaHasil::Normal, $baris->penanda);
    }

    public function test_penanda_mengikuti_jenis_kelamin_pasien(): void
    {
        $lab = app(PemeriksaanLaboratorium::class);
        $analis = $this->analis();

        $orderPria = $this->order('L');
        $lab->ambilSampel($orderPria, $analis);
        $lab->entriHasil($orderPria->refresh(), [$this->hemoglobin->id => 16.0], $analis);

        $orderWanita = $this->order('P');
        $lab->ambilSampel($orderWanita, $analis);
        $lab->entriHasil($orderWanita->refresh(), [$this->hemoglobin->id => 16.0], $analis);

        $penandaPria = HasilLab::whereHas(
            'orderDetail', fn ($q) => $q->where('order_lab_id', $orderPria->id)
        )->first();
        $penandaWanita = HasilLab::whereHas(
            'orderDetail', fn ($q) => $q->where('order_lab_id', $orderWanita->id)
        )->first();

        // Nilai identik, penanda berbeda — inilah gunanya rujukan per jenis kelamin.
        $this->assertSame(PenandaHasil::Normal, $penandaPria->penanda);
        $this->assertSame(PenandaHasil::Tinggi, $penandaWanita->penanda);
    }

    public function test_nilai_bukan_angka_ditolak_dengan_menyebut_parameternya(): void
    {
        $order = $this->order();
        $lab = app(PemeriksaanLaboratorium::class);
        $analis = $this->analis();

        $lab->ambilSampel($order, $analis);

        try {
            $lab->entriHasil($order->refresh(), [$this->hemoglobin->id => 'empat belas'], $analis);
            $this->fail('Nilai bukan angka seharusnya ditolak.');
        } catch (ValidationException $e) {
            $pesan = implode(' ', $e->errors()[array_key_first($e->errors())]);
            $this->assertStringContainsString('Hemoglobin', $pesan);
        }
    }

    public function test_nilai_bukan_angka_tidak_menyimpan_apa_pun(): void
    {
        $order = $this->order();
        $lab = app(PemeriksaanLaboratorium::class);
        $analis = $this->analis();

        $lab->ambilSampel($order, $analis);

        try {
            $lab->entriHasil($order->refresh(), [$this->hemoglobin->id => 'x'], $analis);
        } catch (ValidationException) {
            // diabaikan; yang diuji keadaan sesudahnya
        }

        $this->assertSame(0, HasilLab::count());
        $this->assertSame(StatusOrderLab::SampelDiambil, $order->refresh()->status);
    }

    public function test_entri_ulang_memperbarui_nilai_yang_sama_bukan_menambah_baris(): void
    {
        $order = $this->order();
        $lab = app(PemeriksaanLaboratorium::class);
        $analis = $this->analis();

        $lab->ambilSampel($order, $analis);
        $lab->entriHasil($order->refresh(), [$this->hemoglobin->id => 14.0], $analis);
        $lab->entriHasil($order->refresh(), [$this->hemoglobin->id => 11.0], $analis);

        $this->assertSame(1, HasilLab::where('parameter_lab_id', $this->hemoglobin->id)->count());
        $this->assertSame(
            PenandaHasil::Rendah,
            HasilLab::where('parameter_lab_id', $this->hemoglobin->id)->first()->penanda
        );
    }

    public function test_parameter_tanpa_rujukan_tersimpan_tanpa_penanda(): void
    {
        $tanpaRujukan = ParameterLab::factory()->create([
            'pemeriksaan_lab_id' => $this->darahRutin->id,
            'kode' => 'XX', 'nama' => 'Parameter Tanpa Rujukan',
        ]);

        $order = $this->order();
        $lab = app(PemeriksaanLaboratorium::class);
        $analis = $this->analis();

        $lab->ambilSampel($order, $analis);
        $lab->entriHasil($order->refresh(), [$tanpaRujukan->id => 7.5], $analis);

        $baris = HasilLab::where('parameter_lab_id', $tanpaRujukan->id)->first();

        $this->assertSame(7.5, (float) $baris->nilai);
        $this->assertNull($baris->penanda);
    }

    public function test_order_yang_sudah_batal_tidak_bisa_diambil_sampelnya(): void
    {
        $order = $this->order();
        app(PemesananLab::class)->batalkan($order, $this->analis(), 'Salah pesan');

        $this->expectException(RuntimeException::class);

        app(PemeriksaanLaboratorium::class)->ambilSampel($order->refresh(), $this->analis());
    }

    public function test_sampel_tidak_bisa_diambil_dua_kali(): void
    {
        $order = $this->order();
        $lab = app(PemeriksaanLaboratorium::class);

        $lab->ambilSampel($order, $this->analis());

        $this->expectException(RuntimeException::class);

        $lab->ambilSampel($order->refresh(), $this->analis());
    }
}
