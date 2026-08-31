<?php

namespace Tests\Feature;

use App\Enums\CaraPulang;
use App\Enums\JenisDiagnosa;
use App\Enums\JenisLayanan;
use App\Enums\MetodePembayaran;
use App\Enums\Peran;
use App\Enums\StatusKunjungan;
use App\Enums\StatusRawatInap;
use App\Enums\StatusTagihan;
use App\Models\Bed;
use App\Models\Dokter;
use App\Models\Icd10;
use App\Models\KelasKamar;
use App\Models\OkupansiBed;
use App\Models\Pasien;
use App\Models\Penjamin;
use App\Models\Poli;
use App\Models\RawatInap;
use App\Models\Ruang;
use App\Models\Tarif;
use App\Models\Tindakan;
use App\Models\TindakanKunjungan;
use App\Models\User;
use App\Services\CatatanHarian;
use App\Services\PemeriksaanKlinis;
use App\Services\PemulanganPasien;
use App\Services\PendaftaranKunjungan;
use App\Services\PenempatanBed;
use App\Services\PerintahRawatInap;
use App\Services\ProsesPembayaran;
use App\Services\TindakanPelayanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AlurRawatInapTest extends TestCase
{
    use RefreshDatabase;

    private Poli $poli;

    private Dokter $dokter;

    private Tindakan $visite;

    private Penjamin $umum;

    private Ruang $ruang;

    private KelasKamar $kelas2;

    private KelasKamar $vip;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Peran::semua() as $peran) {
            Role::findOrCreate($peran);
        }

        $this->poli = Poli::factory()->create(['kode' => 'PDL', 'nama' => 'Poli Penyakit Dalam']);
        $this->dokter = Dokter::factory()->create(['poli_id' => $this->poli->id]);
        $this->visite = Tindakan::factory()->create(['nama' => 'Visite Dokter']);
        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $this->ruang = Ruang::factory()->create(['nama' => 'Anggrek']);
        $this->kelas2 = KelasKamar::factory()->create(['nama' => 'Kelas 2']);
        $this->vip = KelasKamar::factory()->create(['nama' => 'VIP']);

        foreach ([
            [JenisLayanan::Tindakan, $this->visite->id, 80000],
            [JenisLayanan::Kamar, $this->kelas2->id, 300000],
            [JenisLayanan::Kamar, $this->vip->id, 750000],
        ] as [$jenis, $layananId, $harga]) {
            Tarif::factory()->create([
                'jenis_layanan' => $jenis, 'layanan_id' => $layananId,
                'penjamin_id' => $this->umum->id, 'harga' => $harga, 'berlaku_mulai' => '2026-01-01',
            ]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function penggunaBerperan(string $peran, array $atribut = []): User
    {
        $user = User::factory()->create($atribut);
        $user->assignRole($peran);

        return $user;
    }

    private function bed(string $nomor, ?KelasKamar $kelas = null): Bed
    {
        return Bed::factory()->create([
            'ruang_id' => $this->ruang->id,
            'kelas_kamar_id' => ($kelas ?? $this->kelas2)->id,
            'nomor' => $nomor,
        ]);
    }

    public function test_alur_lengkap_dari_perintah_rawat_sampai_tagihan_lunas(): void
    {
        Carbon::setTestNow('2026-04-01 08:00:00');

        $admisi = $this->penggunaBerperan(Peran::Admisi->value);
        $perawat = $this->penggunaBerperan(Peran::Perawat->value);
        $dokterUser = $this->penggunaBerperan(Peran::Dokter->value, ['dokter_id' => $this->dokter->id]);
        $kasir = $this->penggunaBerperan(Peran::Kasir->value);
        $klinis = app(PemeriksaanKlinis::class);

        $kunjungan = app(PendaftaranKunjungan::class)->daftarkan([
            'pasien_id' => Pasien::factory()->create(['nama' => 'Budi Santoso'])->id,
            'poli_id' => $this->poli->id,
            'dokter_id' => $this->dokter->id,
            'penjamin_id' => $this->umum->id,
            'tanggal' => '2026-04-01',
        ], $admisi);

        $klinis->catatVital($kunjungan, [
            'sistolik' => 100, 'diastolik' => 65, 'nadi' => 104, 'suhu' => 38.9,
            'respirasi' => 22, 'berat_badan' => 55.0, 'tinggi_badan' => 165,
            'keluhan_awal' => 'Demam lima hari, muntah',
        ], $perawat);

        $klinis->catatSoap($kunjungan, [
            'subjective' => 'Demam lima hari disertai muntah',
            'objective' => 'Suhu 38,9; turgor menurun',
            'assessment' => 'Suspek demam tifoid dengan dehidrasi',
            'plan' => 'Rawat inap, rehidrasi',
        ], $dokterUser);
        $klinis->tambahDiagnosa($kunjungan, Icd10::factory()->create()->id, JenisDiagnosa::Primer);

        $rawatInap = app(PerintahRawatInap::class)
            ->terbitkan($kunjungan->refresh(), $dokterUser, 'Dehidrasi sedang', $this->kelas2);

        $this->assertSame(StatusKunjungan::DalamPerawatan, $kunjungan->refresh()->status);

        app(PenempatanBed::class)->tempatkan($rawatInap, $this->bed('01'), $admisi);

        // Tiga hari perawatan: visite dan catatan perkembangan tiap hari.
        foreach (['2026-04-01', '2026-04-02', '2026-04-03'] as $hari) {
            Carbon::setTestNow($hari.' 09:00:00');

            app(TindakanPelayanan::class)->tambah($kunjungan, $this->visite->id, 1, $dokterUser);
            app(CatatanHarian::class)->tulis($rawatInap->refresh(), [
                'subjective' => 'Keluhan berkurang', 'objective' => 'Suhu turun',
                'assessment' => 'Perbaikan', 'plan' => 'Lanjutkan terapi',
            ], $perawat);
        }

        $this->assertSame(3, $rawatInap->refresh()->catatan()->count());

        Carbon::setTestNow('2026-04-04 10:00:00');

        app(PemulanganPasien::class)->pulangkan(
            $rawatInap->refresh(), $dokterUser, Icd10::factory()->create()->id,
            CaraPulang::Sembuh, 'Kondisi membaik, boleh rawat jalan.'
        );

        $rawatInap->refresh();

        $this->assertSame(StatusRawatInap::Pulang, $rawatInap->status);
        $this->assertSame(CaraPulang::Sembuh, $rawatInap->cara_pulang);
        $this->assertSame(StatusKunjungan::Selesai, $kunjungan->refresh()->status);

        $tagihan = $kunjungan->refresh()->tagihan;
        $ringkasan = $tagihan->detail()
            ->selectRaw('sumber_tipe, SUM(subtotal) AS total')
            ->groupBy('sumber_tipe')
            ->pluck('total', 'sumber_tipe');

        // 3 hari kamar Kelas 2 (900.000) + 3 visite (240.000)
        $this->assertSame(900000, (int) $ringkasan[OkupansiBed::class]);
        $this->assertSame(240000, (int) $ringkasan[TindakanKunjungan::class]);
        $this->assertSame(1_140_000, (int) $tagihan->total);

        app(ProsesPembayaran::class)->bayar(
            $tagihan, MetodePembayaran::Tunai, (int) $tagihan->ditagihkan_ke_pasien, $kasir
        );

        $this->assertSame(StatusTagihan::Lunas, $tagihan->refresh()->status);
    }

    public function test_pasien_pindah_kelas_ditagih_dua_tarif_berbeda(): void
    {
        Carbon::setTestNow('2026-04-01 08:00:00');

        $rawatInap = $this->pasienDirawat('2026-04-01', $this->bed('01'));

        Carbon::setTestNow('2026-04-03 09:00:00');
        app(PenempatanBed::class)->pindahkan(
            $rawatInap->refresh(), $this->bed('02', $this->vip),
            $this->penggunaBerperan(Peran::Admisi->value), 'Permintaan keluarga'
        );

        Carbon::setTestNow('2026-04-06 10:00:00');
        app(PemulanganPasien::class)->pulangkan(
            $rawatInap->refresh(),
            $this->penggunaBerperan(Peran::Dokter->value, ['dokter_id' => $this->dokter->id]),
            Icd10::factory()->create()->id, CaraPulang::Membaik, null
        );

        $baris = $rawatInap->refresh()->kunjungan->tagihan->detail()
            ->where('sumber_tipe', OkupansiBed::class)->orderBy('id')->get();

        // 2 hari Kelas 2 + 3 hari VIP = 5 hari, persis lama rawatnya.
        $this->assertCount(2, $baris);
        $this->assertSame(2 * 300000, (int) $baris[0]->subtotal);
        $this->assertSame(3 * 750000, (int) $baris[1]->subtotal);
        $this->assertSame(5, (int) $baris->sum('jumlah'));
    }

    public function test_bed_berputar_dipakai_pasien_berikutnya_setelah_pemulangan(): void
    {
        Carbon::setTestNow('2026-04-01 08:00:00');

        $bed = $this->bed('01');
        $pertama = $this->pasienDirawat('2026-04-01', $bed);

        $this->assertSame($pertama->id, $bed->refresh()->rawat_inap_id);

        Carbon::setTestNow('2026-04-03 10:00:00');
        app(PemulanganPasien::class)->pulangkan(
            $pertama->refresh(),
            $this->penggunaBerperan(Peran::Dokter->value, ['dokter_id' => $this->dokter->id]),
            Icd10::factory()->create()->id, CaraPulang::Sembuh, null
        );

        $this->assertNull($bed->refresh()->rawat_inap_id);

        $kedua = $this->pasienDirawat('2026-04-03', $bed->refresh());

        $this->assertSame($kedua->id, $bed->refresh()->rawat_inap_id);
        $this->assertSame(2, RawatInap::count());
    }

    private function pasienDirawat(string $tanggal, Bed $bed): RawatInap
    {
        $dokterUser = $this->penggunaBerperan(Peran::Dokter->value, ['dokter_id' => $this->dokter->id]);
        $klinis = app(PemeriksaanKlinis::class);

        $kunjungan = app(PendaftaranKunjungan::class)->daftarkan([
            'pasien_id' => Pasien::factory()->create()->id,
            'poli_id' => $this->poli->id,
            'dokter_id' => $this->dokter->id,
            'penjamin_id' => $this->umum->id,
            'tanggal' => $tanggal,
        ], $this->penggunaBerperan(Peran::Admisi->value));

        $klinis->catatSoap($kunjungan, [
            'subjective' => 'Demam', 'objective' => 'Suhu 38,5',
            'assessment' => 'Infeksi', 'plan' => 'Rawat inap',
        ], $dokterUser);
        $klinis->tambahDiagnosa($kunjungan, Icd10::factory()->create()->id, JenisDiagnosa::Primer);

        $rawatInap = app(PerintahRawatInap::class)
            ->terbitkan($kunjungan->refresh(), $dokterUser, 'Perlu observasi', $this->kelas2);

        app(PenempatanBed::class)
            ->tempatkan($rawatInap, $bed, $this->penggunaBerperan(Peran::Admisi->value));

        return $rawatInap->refresh();
    }
}
