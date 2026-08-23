<?php

namespace Tests\Feature;

use App\Enums\JenisDiagnosa;
use App\Enums\MetodePembayaran;
use App\Enums\Peran;
use App\Enums\StatusResep;
use App\Enums\StatusTagihan;
use App\Models\BatchObat;
use App\Models\Dokter;
use App\Models\HargaObat;
use App\Models\Icd10;
use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\Pasien;
use App\Models\Penjamin;
use App\Models\Poli;
use App\Models\TarifTindakan;
use App\Models\Tindakan;
use App\Models\User;
use App\Services\PemeriksaanKlinis;
use App\Services\PendaftaranKunjungan;
use App\Services\PenulisanResep;
use App\Services\PenyerahanObat;
use App\Services\PenyiapanResep;
use App\Services\ProsesPembayaran;
use App\Services\TindakanPelayanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AlurFarmasiTest extends TestCase
{
    use RefreshDatabase;

    private Poli $poli;
    private Dokter $dokter;
    private Tindakan $konsultasi;
    private Obat $obat;
    private BatchObat $batch;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Peran::semua() as $peran) {
            Role::findOrCreate($peran);
        }

        $this->poli = Poli::factory()->create(['kode' => 'UMU', 'nama' => 'Poli Umum']);
        $this->dokter = Dokter::factory()->create(['poli_id' => $this->poli->id]);
        $this->konsultasi = Tindakan::factory()->create(['nama' => 'Konsultasi Dokter Umum']);
        $this->obat = Obat::factory()->create(['nama' => 'Amoksisilin 500 mg']);

        $this->batch = BatchObat::factory()->create([
            'obat_id' => $this->obat->id,
            'no_batch' => 'B2026001',
            'tanggal_kedaluwarsa' => '2029-01-31',
            'jumlah_awal' => 100,
            'jumlah_tersisa' => 100,
            'harga_beli' => 1200,
        ]);
    }

    private function penjaminLengkap(string $kode, string $jenis, int $tarif, int $harga): Penjamin
    {
        $penjamin = Penjamin::factory()->create(['kode' => $kode, 'jenis' => $jenis]);

        TarifTindakan::factory()->create([
            'tindakan_id' => $this->konsultasi->id,
            'penjamin_id' => $penjamin->id,
            'tarif' => $tarif,
            'berlaku_mulai' => '2026-01-01',
        ]);

        HargaObat::factory()->create([
            'obat_id' => $this->obat->id,
            'penjamin_id' => $penjamin->id,
            'harga' => $harga,
            'berlaku_mulai' => '2026-01-01',
        ]);

        return $penjamin;
    }

    private function penggunaBerperan(string $peran, array $atribut = []): User
    {
        $user = User::factory()->create($atribut);
        $user->assignRole($peran);

        return $user;
    }

    /** Menjalankan alur poli sampai kunjungan selesai dan resep tertulis. */
    private function sampaiResepTertulis(Penjamin $penjamin, string $nik, ?string $noKartu): Kunjungan
    {
        $pasien = Pasien::factory()->create(['nik' => $nik]);
        $admisi = $this->penggunaBerperan(Peran::Admisi->value);

        $kunjungan = app(PendaftaranKunjungan::class)->daftarkan([
            'pasien_id' => $pasien->id,
            'poli_id' => $this->poli->id,
            'dokter_id' => $this->dokter->id,
            'penjamin_id' => $penjamin->id,
            'no_kartu_penjamin' => $noKartu,
            'tanggal' => now()->toDateString(),
        ], $admisi);

        $perawat = $this->penggunaBerperan(Peran::Perawat->value);
        $dokterUser = $this->penggunaBerperan(Peran::Dokter->value, ['dokter_id' => $this->dokter->id]);
        $klinis = app(PemeriksaanKlinis::class);

        $klinis->catatVital($kunjungan, [
            'sistolik' => 120, 'diastolik' => 80, 'nadi' => 78, 'suhu' => 36.7,
            'respirasi' => 18, 'berat_badan' => 62.5, 'tinggi_badan' => 165,
            'keluhan_awal' => 'Nyeri tenggorokan',
        ], $perawat);

        $klinis->catatSoap($kunjungan, [
            'subjective' => 'Nyeri tenggorokan tiga hari',
            'objective' => 'Tonsil hiperemis',
            'assessment' => 'Tonsilitis akut',
            'plan' => 'Antibiotik',
        ], $dokterUser);

        $klinis->tambahDiagnosa($kunjungan, Icd10::factory()->create()->id, JenisDiagnosa::Primer);
        app(TindakanPelayanan::class)->tambah($kunjungan, $this->konsultasi->id, 1, $dokterUser);

        app(PenulisanResep::class)->tulis($kunjungan, [[
            'obat_id' => $this->obat->id,
            'jumlah' => 10,
            'aturan_pakai' => '3x1 sesudah makan',
        ]], $dokterUser);

        $klinis->selesaikan($kunjungan, $dokterUser);

        return $kunjungan->refresh();
    }

    public function test_alur_lengkap_pasien_umum_dari_resep_sampai_obat_diserahkan(): void
    {
        $umum = $this->penjaminLengkap('UMUM', 'tunai', 50000, 2000);
        $kunjungan = $this->sampaiResepTertulis($umum, '3202011203900001', null);

        // Sebelum apotek bekerja, tagihan hanya berisi tindakan.
        $this->assertSame(50000, (int) $kunjungan->tagihan->total);

        $apoteker = $this->penggunaBerperan(Peran::Apoteker->value);
        app(PenyiapanResep::class)->siapkan($kunjungan->resep, $apoteker);

        $kunjungan->refresh();
        $tagihan = $kunjungan->tagihan->refresh();

        $this->assertSame(70000, (int) $tagihan->total);
        $this->assertSame(70000, (int) $tagihan->ditagihkan_ke_pasien);
        $this->assertSame(90, (int) $this->batch->refresh()->jumlah_tersisa);

        $kasir = $this->penggunaBerperan(Peran::Kasir->value);
        $pembayaran = app(ProsesPembayaran::class)
            ->bayar($tagihan, MetodePembayaran::Tunai, 70000, $kasir);

        $diserahkan = app(PenyerahanObat::class)->serahkan($kunjungan->resep->refresh(), $apoteker);

        $this->assertSame(StatusTagihan::Lunas, $tagihan->refresh()->status);
        $this->assertSame(70000, (int) $pembayaran->nominal);
        $this->assertSame(StatusResep::Diserahkan, $diserahkan->status);
        $this->assertSame(2, $tagihan->detail()->count());
    }

    public function test_alur_lengkap_pasien_bpjs_menerima_obat_tanpa_ke_kasir(): void
    {
        $this->penjaminLengkap('UMUM', 'tunai', 50000, 2000);
        $bpjs = $this->penjaminLengkap('BPJS', 'penjamin', 35000, 1400);

        $kunjungan = $this->sampaiResepTertulis($bpjs, '3202011203900002', '0001234567890');

        $apoteker = $this->penggunaBerperan(Peran::Apoteker->value);
        app(PenyiapanResep::class)->siapkan($kunjungan->resep, $apoteker);

        $diserahkan = app(PenyerahanObat::class)->serahkan($kunjungan->resep->refresh(), $apoteker);
        $tagihan = $kunjungan->tagihan->refresh();

        $this->assertSame(StatusResep::Diserahkan, $diserahkan->status);
        $this->assertSame(49000, (int) $tagihan->total);
        $this->assertSame(49000, (int) $tagihan->ditanggung_penjamin);
        $this->assertSame(0, (int) $tagihan->ditagihkan_ke_pasien);
        $this->assertSame(StatusTagihan::DitanggungPenjamin, $tagihan->status);
        $this->assertSame(0, $tagihan->pembayaran()->count());
    }

    public function test_batch_yang_diterima_pasien_bisa_ditelusuri(): void
    {
        $umum = $this->penjaminLengkap('UMUM', 'tunai', 50000, 2000);
        $kunjungan = $this->sampaiResepTertulis($umum, '3202011203900003', null);

        $apoteker = $this->penggunaBerperan(Peran::Apoteker->value);
        $resep = app(PenyiapanResep::class)->siapkan($kunjungan->resep, $apoteker);

        $pengambilan = $resep->detail->first()->pengambilan->first();

        $this->assertSame('B2026001', $pengambilan->batch->no_batch);
        $this->assertSame(10, (int) $pengambilan->jumlah);
    }
}
