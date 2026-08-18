<?php

namespace Tests\Feature;

use App\Enums\JenisDiagnosa;
use App\Enums\MetodePembayaran;
use App\Enums\Peran;
use App\Enums\StatusKunjungan;
use App\Enums\StatusTagihan;
use App\Models\BatchObat;
use App\Models\Dokter;
use App\Models\HargaObat;
use App\Models\Icd10;
use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\Penjamin;
use App\Models\Poli;
use App\Models\TarifTindakan;
use App\Models\Tindakan;
use App\Models\User;
use App\Services\PemeriksaanKlinis;
use App\Services\PendaftaranKunjungan;
use App\Services\PendaftaranPasien;
use App\Services\PenulisanResep;
use App\Services\PenyiapanResep;
use App\Services\ProsesPembayaran;
use App\Services\TindakanPelayanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AlurRawatJalanTest extends TestCase
{
    use RefreshDatabase;

    private Poli $poli;
    private Dokter $dokter;
    private Tindakan $konsultasi;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Peran::semua() as $peran) {
            Role::findOrCreate($peran);
        }

        $this->poli = Poli::factory()->create(['kode' => 'UMU', 'nama' => 'Poli Umum']);
        $this->dokter = Dokter::factory()->create(['poli_id' => $this->poli->id]);
        $this->konsultasi = Tindakan::factory()->create(['nama' => 'Konsultasi Dokter Umum']);
    }

    private function penjaminDenganTarif(string $kode, string $jenis, int $tarif): Penjamin
    {
        $penjamin = Penjamin::factory()->create(['kode' => $kode, 'jenis' => $jenis]);

        TarifTindakan::factory()->create([
            'tindakan_id' => $this->konsultasi->id,
            'penjamin_id' => $penjamin->id,
            'tarif' => $tarif,
            'berlaku_mulai' => '2026-01-01',
        ]);

        return $penjamin;
    }

    private function jalankanAlur(Penjamin $penjamin, string $nik, ?string $noKartu): Kunjungan
    {
        $pasien = app(PendaftaranPasien::class)->daftarkan([
            'nik' => $nik,
            'nama' => 'Pasien Alur '.$penjamin->kode,
            'tanggal_lahir' => '1990-03-12',
            'jenis_kelamin' => 'P',
            'alamat' => 'Jl. Melati No. 12',
        ]);

        $admisi = User::factory()->create();
        $admisi->assignRole(Peran::Admisi->value);

        $kunjungan = app(PendaftaranKunjungan::class)->daftarkan([
            'pasien_id' => $pasien->id,
            'poli_id' => $this->poli->id,
            'dokter_id' => $this->dokter->id,
            'penjamin_id' => $penjamin->id,
            'no_kartu_penjamin' => $noKartu,
            'tanggal' => now()->toDateString(),
        ], $admisi);

        $perawat = User::factory()->create();
        $perawat->assignRole(Peran::Perawat->value);

        $klinis = app(PemeriksaanKlinis::class);
        $klinis->catatVital($kunjungan, [
            'sistolik' => 120, 'diastolik' => 80, 'nadi' => 78, 'suhu' => 36.7,
            'respirasi' => 18, 'berat_badan' => 62.5, 'tinggi_badan' => 165,
            'keluhan_awal' => 'Batuk tiga hari',
        ], $perawat);

        $dokterUser = User::factory()->create(['dokter_id' => $this->dokter->id]);
        $dokterUser->assignRole(Peran::Dokter->value);

        $klinis->catatSoap($kunjungan, [
            'subjective' => 'Batuk berdahak tiga hari',
            'objective' => 'Faring hiperemis',
            'assessment' => 'ISPA',
            'plan' => 'Antibiotik dan obat batuk',
        ], $dokterUser);

        $klinis->tambahDiagnosa($kunjungan, Icd10::factory()->create()->id, JenisDiagnosa::Primer);

        app(TindakanPelayanan::class)->tambah($kunjungan, $this->konsultasi->id, 1, $dokterUser);

        $obat = Obat::factory()->create(['nama' => 'Amoksisilin 500 mg']);

        HargaObat::factory()->create([
            'obat_id' => $obat->id,
            'penjamin_id' => $penjamin->id,
            'harga' => 2000,
            'berlaku_mulai' => '2026-01-01',
        ]);

        BatchObat::factory()->create([
            'obat_id' => $obat->id,
            'tanggal_kedaluwarsa' => '2029-01-31',
            'jumlah_awal' => 100,
            'jumlah_tersisa' => 100,
            'harga_beli' => 1200,
        ]);

        $resep = app(PenulisanResep::class)->tulis($kunjungan, [[
            'obat_id' => $obat->id,
            'jumlah' => 10,
            'aturan_pakai' => '3x1 sesudah makan',
        ]], $dokterUser);

        $klinis->selesaikan($kunjungan, $dokterUser);

        // Sejak Fase 2, kasir terkunci sampai apotek menyiapkan resepnya
        // (aturan 29). Alur rawat jalan kini memang melewati apotek.
        $apoteker = User::factory()->create();
        $apoteker->assignRole(Peran::Apoteker->value);
        app(PenyiapanResep::class)->siapkan($resep, $apoteker);

        return $kunjungan->refresh();
    }

    public function test_alur_lengkap_pasien_umum_sampai_kuitansi(): void
    {
        $umum = $this->penjaminDenganTarif('UMUM', 'tunai', 50000);
        $kunjungan = $this->jalankanAlur($umum, '3202011203900001', null);

        $kasir = User::factory()->create();
        $kasir->assignRole(Peran::Kasir->value);

        $pembayaran = app(ProsesPembayaran::class)
            ->bayar($kunjungan->tagihan, MetodePembayaran::Tunai, 100000, $kasir);

        $this->assertSame(StatusKunjungan::Selesai, $kunjungan->status);
        // 50.000 konsultasi + 10 x 2.000 obat
        $this->assertSame(70000, (int) $kunjungan->tagihan->total);
        $this->assertSame(30000, (int) $pembayaran->kembalian);
        $this->assertSame(StatusTagihan::Lunas, $kunjungan->tagihan->refresh()->status);
        $this->assertNotNull($kunjungan->resep);
        $this->assertSame(1, $kunjungan->diagnosa()->count());
    }

    public function test_alur_lengkap_pasien_bpjs_tidak_ditagihkan_ke_pasien(): void
    {
        $this->penjaminDenganTarif('UMUM', 'tunai', 50000);
        $bpjs = $this->penjaminDenganTarif('BPJS', 'penjamin', 35000);

        $kunjungan = $this->jalankanAlur($bpjs, '3202011203900002', '0001234567890');

        $this->assertSame(55000, (int) $kunjungan->tagihan->total);
        $this->assertSame(55000, (int) $kunjungan->tagihan->ditanggung_penjamin);
        $this->assertSame(0, (int) $kunjungan->tagihan->ditagihkan_ke_pasien);
        $this->assertSame(StatusTagihan::DitanggungPenjamin, $kunjungan->tagihan->status);
    }

    public function test_seluruh_perubahan_klinis_pada_alur_terekam_di_audit_log(): void
    {
        $umum = $this->penjaminDenganTarif('UMUM', 'tunai', 50000);
        $kunjungan = $this->jalankanAlur($umum, '3202011203900003', null);

        $this->assertDatabaseHas('audit_logs', ['model_tipe' => \App\Models\Pasien::class, 'aksi' => 'create']);
        $this->assertDatabaseHas('audit_logs', ['model_tipe' => \App\Models\Kunjungan::class, 'model_id' => $kunjungan->id]);
        $this->assertDatabaseHas('audit_logs', ['model_tipe' => \App\Models\Pemeriksaan::class]);
        $this->assertDatabaseHas('audit_logs', ['model_tipe' => \App\Models\Tagihan::class]);
    }
}
