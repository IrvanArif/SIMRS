<?php

namespace Tests\Feature;

use App\Enums\JenisDiagnosa;
use App\Enums\JenisLayanan;
use App\Enums\StatusBerkasKlaim;
use App\Models\BerkasKlaim;
use App\Models\Icd10;
use App\Models\Icd9;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\Penjamin;
use App\Models\Tarif;
use App\Models\Tindakan;
use App\Models\User;
use App\Services\EksporKlaim;
use App\Services\PemeriksaanKlinis;
use App\Services\PenerbitanSep;
use App\Services\PenyusunBerkasKlaim;
use App\Services\TindakanPelayanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EksporKlaimTest extends TestCase
{
    use RefreshDatabase;

    private Penjamin $bpjs;

    private Tindakan $infus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bpjs = Penjamin::factory()->create([
            'kode' => 'BPJS', 'nama' => 'BPJS Kesehatan', 'jenis' => 'penjamin',
        ]);

        $icd9 = Icd9::factory()->create(['kode' => '38.93', 'nama' => 'Pemasangan infus']);
        $this->infus = Tindakan::factory()->create(['nama' => 'Pemasangan Infus', 'icd9_id' => $icd9->id]);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Tindakan, 'layanan_id' => $this->infus->id,
            'penjamin_id' => $this->bpjs->id, 'harga' => 75000, 'berlaku_mulai' => '2026-01-01',
        ]);
    }

    /** Kode ICD-10 dipakai berulang antar berkas, jadi dicari dulu sebelum dibuat. */
    private function icd(string $kode): Icd10
    {
        return Icd10::firstOrCreate(['kode' => $kode], ['nama_id' => 'Diagnosa '.$kode]);
    }

    private function berkas(string $namaPasien = 'Budi Santoso', bool $duaSekunder = false): BerkasKlaim
    {
        $pasien = Pasien::factory()->create(['nama' => $namaPasien]);
        $kunjungan = Kunjungan::factory()->create([
            'pasien_id' => $pasien->id,
            'penjamin_id' => $this->bpjs->id,
            'no_kartu_penjamin' => '0001234567890',
        ]);
        $dokter = User::factory()->create(['dokter_id' => $kunjungan->dokter_id]);

        app(PenerbitanSep::class)->terbitkan($kunjungan, User::factory()->create(), 'Demam tifoid');
        app(TindakanPelayanan::class)->tambah($kunjungan, $this->infus->id, 1, $dokter);

        $klinis = app(PemeriksaanKlinis::class);
        $klinis->catatSoap($kunjungan, [
            'subjective' => 'a', 'objective' => 'b', 'assessment' => 'c', 'plan' => 'd',
        ], $dokter);
        $klinis->tambahDiagnosa($kunjungan, $this->icd('A01.0')->id, JenisDiagnosa::Primer);
        $klinis->tambahDiagnosa($kunjungan, $this->icd('E86')->id, JenisDiagnosa::Sekunder);

        if ($duaSekunder) {
            $klinis->tambahDiagnosa($kunjungan, $this->icd('R50.9')->id, JenisDiagnosa::Sekunder);
        }

        $klinis->selesaikan($kunjungan->refresh(), $dokter);

        return app(PenyusunBerkasKlaim::class)->susun($kunjungan->refresh(), User::factory()->create());
    }

    private function diajukan(string $namaPasien = 'Budi Santoso', bool $duaSekunder = false): BerkasKlaim
    {
        $berkas = $this->berkas($namaPasien, $duaSekunder);

        return app(PenyusunBerkasKlaim::class)->ajukan($berkas, User::factory()->create());
    }

    public function test_baris_ekspor_memuat_identitas_sep_dan_biaya(): void
    {
        $berkas = $this->diajukan();
        $baris = app(EksporKlaim::class)->baris($berkas);

        $this->assertSame($berkas->no_berkas, $baris['no_berkas']);
        $this->assertSame($berkas->sep->no_sep, $baris['no_sep']);
        $this->assertSame('0001234567890', $baris['no_kartu']);
        $this->assertSame('Budi Santoso', $baris['nama_peserta']);
        $this->assertSame('A01.0', $baris['diagnosa_primer']);
        $this->assertSame('38.93', $baris['prosedur']);
        $this->assertSame(75000, $baris['total_biaya']);
        $this->assertSame('Rawat Jalan', $baris['jenis_pelayanan']);
    }

    public function test_baris_ekspor_menggabungkan_diagnosa_sekunder_dengan_pemisah(): void
    {
        $baris = app(EksporKlaim::class)->baris($this->diajukan(duaSekunder: true));

        $this->assertSame('E86;R50.9', $baris['diagnosa_sekunder']);
    }

    public function test_csv_memuat_baris_kepala(): void
    {
        $csv = app(EksporKlaim::class)->csv(BerkasKlaim::terkirim()->get());
        $kepala = strtok($csv, "\n");

        $this->assertStringContainsString('no_berkas', $kepala);
        $this->assertStringContainsString('total_biaya', $kepala);
    }

    public function test_csv_menuliskan_setiap_berkas_satu_baris(): void
    {
        $this->diajukan('Budi Santoso');
        $this->diajukan('Siti Aminah');

        $csv = app(EksporKlaim::class)->csv(BerkasKlaim::terkirim()->get());

        // Satu baris kepala + dua baris isi, ditambah baris kosong penutup.
        $this->assertCount(3, array_filter(explode("\n", trim($csv))));
        $this->assertStringContainsString('Budi Santoso', $csv);
        $this->assertStringContainsString('Siti Aminah', $csv);
    }

    public function test_nilai_bertanda_kutip_tidak_merusak_csv(): void
    {
        // Nama yang memuat koma atau tanda kutip menggeser seluruh kolom di
        // berkas yang dikirim ke pihak lain bila tidak dikutip dengan benar.
        $this->diajukan('Budi "Ucok" Santoso, S.Pd');

        $csv = app(EksporKlaim::class)->csv(BerkasKlaim::terkirim()->get());
        $baris = array_filter(explode("\n", trim($csv)));
        $terurai = str_getcsv(end($baris));
        $kepala = str_getcsv(reset($baris));

        $this->assertSame('Budi "Ucok" Santoso, S.Pd', $terurai[array_search('nama_peserta', $kepala, true)]);
        $this->assertCount(count($kepala), $terurai);
    }

    public function test_berkas_draf_tidak_ikut_diekspor(): void
    {
        $this->berkas('Masih Draf');
        $this->diajukan('Sudah Diajukan');

        $csv = app(EksporKlaim::class)->csv(BerkasKlaim::terkirim()->get());

        $this->assertStringNotContainsString('Masih Draf', $csv);
        $this->assertStringContainsString('Sudah Diajukan', $csv);
    }

    public function test_berkas_tanpa_prosedur_tetap_terekspor_dengan_kolom_kosong(): void
    {
        $pasien = Pasien::factory()->create(['nama' => 'Tanpa Prosedur']);
        $kunjungan = Kunjungan::factory()->create([
            'pasien_id' => $pasien->id, 'penjamin_id' => $this->bpjs->id,
            'no_kartu_penjamin' => '0009999999999',
        ]);
        $dokter = User::factory()->create(['dokter_id' => $kunjungan->dokter_id]);
        $tanpaIcd9 = Tindakan::factory()->create(['nama' => 'Konsultasi', 'icd9_id' => null]);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Tindakan, 'layanan_id' => $tanpaIcd9->id,
            'penjamin_id' => $this->bpjs->id, 'harga' => 50000, 'berlaku_mulai' => '2026-01-01',
        ]);

        app(PenerbitanSep::class)->terbitkan($kunjungan, User::factory()->create(), 'Faringitis');
        app(TindakanPelayanan::class)->tambah($kunjungan, $tanpaIcd9->id, 1, $dokter);

        $klinis = app(PemeriksaanKlinis::class);
        $klinis->catatSoap($kunjungan, ['subjective' => 'a', 'objective' => 'b', 'assessment' => 'c', 'plan' => 'd'], $dokter);
        $klinis->tambahDiagnosa($kunjungan, $this->icd('J02.9')->id, JenisDiagnosa::Primer);
        $klinis->selesaikan($kunjungan->refresh(), $dokter);

        $berkas = app(PenyusunBerkasKlaim::class)->susun($kunjungan->refresh(), User::factory()->create());
        app(PenyusunBerkasKlaim::class)->ajukan($berkas, User::factory()->create());

        $baris = app(EksporKlaim::class)->baris($berkas->refresh());

        $this->assertSame('', $baris['prosedur']);
        $this->assertSame('J02.9', $baris['diagnosa_primer']);
    }

    public function test_kumpulan_kosong_menghasilkan_csv_berisi_kepala_saja(): void
    {
        $csv = app(EksporKlaim::class)->csv(BerkasKlaim::terkirim()->get());

        $this->assertCount(1, array_filter(explode("\n", trim($csv))));
    }
}
