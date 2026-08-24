<?php

namespace Tests\Feature;

use App\Enums\JenisDiagnosa;
use App\Enums\JenisLayanan;
use App\Enums\MetodePembayaran;
use App\Enums\PenandaHasil;
use App\Enums\Peran;
use App\Enums\StatusKunjungan;
use App\Enums\StatusOrderLab;
use App\Enums\StatusTagihan;
use App\Models\Dokter;
use App\Models\HasilLab;
use App\Models\Icd10;
use App\Models\Kunjungan;
use App\Models\OrderLabDetail;
use App\Models\ParameterLab;
use App\Models\Pasien;
use App\Models\PemeriksaanLab;
use App\Models\Penjamin;
use App\Models\Poli;
use App\Models\RujukanLab;
use App\Models\Tarif;
use App\Models\Tindakan;
use App\Models\User;
use App\Services\PemeriksaanKlinis;
use App\Services\PemeriksaanLaboratorium;
use App\Services\PemesananLab;
use App\Services\PendaftaranKunjungan;
use App\Services\ProsesPembayaran;
use App\Services\TindakanPelayanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AlurLabTest extends TestCase
{
    use RefreshDatabase;

    private Poli $poli;
    private Dokter $dokter;
    private Tindakan $konsultasi;
    private PemeriksaanLab $darahRutin;
    private ParameterLab $hemoglobin;
    private Penjamin $umum;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Peran::semua() as $peran) {
            Role::findOrCreate($peran);
        }

        $this->poli = Poli::factory()->create(['kode' => 'UMU', 'nama' => 'Poli Umum']);
        $this->dokter = Dokter::factory()->create(['poli_id' => $this->poli->id]);
        $this->konsultasi = Tindakan::factory()->create(['nama' => 'Konsultasi Dokter Umum']);
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
            'jenis_layanan' => JenisLayanan::Tindakan, 'layanan_id' => $this->konsultasi->id,
            'penjamin_id' => $this->umum->id, 'harga' => 50000, 'berlaku_mulai' => '2026-01-01',
        ]);
        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Lab, 'layanan_id' => $this->darahRutin->id,
            'penjamin_id' => $this->umum->id, 'harga' => 75000, 'berlaku_mulai' => '2026-01-01',
        ]);
    }

    private function penggunaBerperan(string $peran, array $atribut = []): User
    {
        $user = User::factory()->create($atribut);
        $user->assignRole($peran);

        return $user;
    }

    private function kunjunganBaru(string $nik, string $jenisKelamin = 'L'): Kunjungan
    {
        $pasien = Pasien::factory()->create(['nik' => $nik, 'jenis_kelamin' => $jenisKelamin]);

        return app(PendaftaranKunjungan::class)->daftarkan([
            'pasien_id' => $pasien->id,
            'poli_id' => $this->poli->id,
            'dokter_id' => $this->dokter->id,
            'penjamin_id' => $this->umum->id,
            'tanggal' => now()->toDateString(),
        ], $this->penggunaBerperan(Peran::Admisi->value));
    }

    public function test_alur_lengkap_dari_dokter_memesan_sampai_kunjungan_ditutup(): void
    {
        $kunjungan = $this->kunjunganBaru('3202011203900001', 'L');

        $perawat = $this->penggunaBerperan(Peran::Perawat->value);
        $dokterUser = $this->penggunaBerperan(Peran::Dokter->value, ['dokter_id' => $this->dokter->id]);
        $analis = $this->penggunaBerperan(Peran::Analis->value);
        $klinis = app(PemeriksaanKlinis::class);

        $klinis->catatVital($kunjungan, [
            'sistolik' => 110, 'diastolik' => 70, 'nadi' => 82, 'suhu' => 36.5,
            'respirasi' => 18, 'berat_badan' => 58.0, 'tinggi_badan' => 168,
            'keluhan_awal' => 'Lemas dan pucat',
        ], $perawat);

        $klinis->catatSoap($kunjungan, [
            'subjective' => 'Lemas dua minggu', 'objective' => 'Konjungtiva pucat',
            'assessment' => 'Suspek anemia', 'plan' => 'Cek darah rutin',
        ], $dokterUser);

        app(TindakanPelayanan::class)->tambah($kunjungan, $this->konsultasi->id, 1, $dokterUser);

        // Dokter memesan lab, lalu mencoba menutup kunjungan sebelum hasilnya keluar.
        $order = app(PemesananLab::class)
            ->pesan($kunjungan, [$this->darahRutin->id], $dokterUser, 'Suspek anemia');

        $klinis->tambahDiagnosa($kunjungan, Icd10::factory()->create()->id, JenisDiagnosa::Primer);

        try {
            $klinis->selesaikan($kunjungan->refresh(), $dokterUser);
            $this->fail('Kunjungan seharusnya tertahan sampai hasil lab divalidasi.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString($order->no_order, $e->getMessage());
        }

        // Laboratorium mengerjakan.
        $lab = app(PemeriksaanLaboratorium::class);
        $lab->ambilSampel($order, $analis);
        $lab->entriHasil($order->refresh(), [$this->hemoglobin->id => 10.5], $analis);

        // Hasil belum terbaca dokter sebelum divalidasi.
        $this->assertFalse($order->refresh()->terbacaDokter());

        $lab->validasi($order->refresh(), $analis);
        $this->assertTrue($order->refresh()->terbacaDokter());

        // Hemoglobin 10,5 pada laki-laki (rujukan 13–17) tergolong rendah.
        $hasil = HasilLab::where('parameter_lab_id', $this->hemoglobin->id)->first();
        $this->assertSame(PenandaHasil::Rendah, $hasil->penanda);

        // Barulah kunjungan bisa ditutup, dan tagihannya memuat lab.
        $selesai = $klinis->selesaikan($kunjungan->refresh(), $dokterUser);
        $tagihan = $selesai->refresh()->tagihan;

        $this->assertSame(StatusKunjungan::Selesai, $selesai->status);
        $this->assertSame(125000, (int) $tagihan->total);
        $this->assertSame(2, $tagihan->detail()->count());
        $this->assertSame(
            75000,
            (int) $tagihan->detail()->where('sumber_tipe', OrderLabDetail::class)->sum('subtotal')
        );

        // Kasir menyelesaikan seluruhnya dalam satu pembayaran.
        $kasir = $this->penggunaBerperan(Peran::Kasir->value);
        app(ProsesPembayaran::class)->bayar($tagihan, MetodePembayaran::Tunai, 125000, $kasir);

        $this->assertSame(StatusTagihan::Lunas, $tagihan->refresh()->status);
        $this->assertSame(StatusOrderLab::Divalidasi, $order->refresh()->status);
    }

    public function test_nilai_abnormal_tertandai_benar_untuk_pasien_pria_dan_wanita(): void
    {
        $dokterUser = $this->penggunaBerperan(Peran::Dokter->value, ['dokter_id' => $this->dokter->id]);
        $analis = $this->penggunaBerperan(Peran::Analis->value);
        $lab = app(PemeriksaanLaboratorium::class);

        $penanda = [];

        foreach ([['3202011203900002', 'L'], ['3202011203900003', 'P']] as [$nik, $jk]) {
            $kunjungan = $this->kunjunganBaru($nik, $jk);

            $order = app(PemesananLab::class)
                ->pesan($kunjungan, [$this->darahRutin->id], $dokterUser);

            $lab->ambilSampel($order, $analis);
            // Nilai yang sama persis untuk kedua pasien.
            $lab->entriHasil($order->refresh(), [$this->hemoglobin->id => 16.0], $analis);

            $penanda[$jk] = HasilLab::whereHas(
                'orderDetail', fn ($q) => $q->where('order_lab_id', $order->id)
            )->first()->penanda;
        }

        $this->assertSame(PenandaHasil::Normal, $penanda['L']);
        $this->assertSame(PenandaHasil::Tinggi, $penanda['P']);
    }

    public function test_batch_hasil_bisa_ditelusuri_dari_kunjungan_sampai_pelakunya(): void
    {
        $kunjungan = $this->kunjunganBaru('3202011203900004', 'P');
        $dokterUser = $this->penggunaBerperan(Peran::Dokter->value, ['dokter_id' => $this->dokter->id]);
        $analis = $this->penggunaBerperan(Peran::Analis->value);

        $order = app(PemesananLab::class)
            ->pesan($kunjungan, [$this->darahRutin->id], $dokterUser);

        $lab = app(PemeriksaanLaboratorium::class);
        $lab->ambilSampel($order, $analis);
        $lab->entriHasil($order->refresh(), [$this->hemoglobin->id => 13.0], $analis);
        $lab->validasi($order->refresh(), $analis);

        $order->refresh();

        // Seluruh tahap beserta pelakunya tercatat pada satu order.
        $this->assertSame($dokterUser->id, $order->dokter_id);
        $this->assertSame($analis->id, $order->diambil_oleh);
        $this->assertSame($analis->id, $order->dientri_oleh);
        $this->assertSame($analis->id, $order->divalidasi_oleh);
        $this->assertNotNull($order->waktu_sampel);
        $this->assertNotNull($order->waktu_hasil);
        $this->assertNotNull($order->waktu_validasi);
    }
}
