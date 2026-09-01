<?php

namespace Tests\Feature;

use App\Enums\CaraPulang;
use App\Enums\JenisDiagnosa;
use App\Enums\JenisLayanan;
use App\Enums\JenisPelayanan;
use App\Enums\Peran;
use App\Enums\StatusBerkasKlaim;
use App\Models\Bed;
use App\Models\Dokter;
use App\Models\Icd10;
use App\Models\Icd9;
use App\Models\KelasKamar;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\Penjamin;
use App\Models\Poli;
use App\Models\Ruang;
use App\Models\Tarif;
use App\Models\Tindakan;
use App\Models\User;
use App\Services\EksporKlaim;
use App\Services\IndikatorRawatInap;
use App\Services\LaporanKunjungan;
use App\Services\LaporanMorbiditas;
use App\Services\LaporanPendapatan;
use App\Services\PemeriksaanKlinis;
use App\Services\PemulanganPasien;
use App\Services\PendaftaranKunjungan;
use App\Services\PenempatanBed;
use App\Services\PenerbitanSep;
use App\Services\PenyusunBerkasKlaim;
use App\Services\PerintahRawatInap;
use App\Services\RentangTanggal;
use App\Services\TindakanPelayanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AlurKlaimTest extends TestCase
{
    use RefreshDatabase;

    private Penjamin $bpjs;

    private Poli $poli;

    private Dokter $dokter;

    private Tindakan $infus;

    private KelasKamar $kelas;

    private Ruang $ruang;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Peran::semua() as $peran) {
            Role::findOrCreate($peran);
        }

        $this->bpjs = Penjamin::factory()->create([
            'kode' => 'BPJS', 'nama' => 'BPJS Kesehatan', 'jenis' => 'penjamin',
        ]);
        $this->poli = Poli::factory()->create(['kode' => 'PDL', 'nama' => 'Poli Penyakit Dalam']);
        $this->dokter = Dokter::factory()->create(['poli_id' => $this->poli->id]);
        $this->ruang = Ruang::factory()->create(['nama' => 'Anggrek']);
        $this->kelas = KelasKamar::factory()->create(['nama' => 'Kelas 2']);

        $icd9 = Icd9::factory()->create(['kode' => '38.93', 'nama' => 'Pemasangan infus']);
        $this->infus = Tindakan::factory()->create(['nama' => 'Pemasangan Infus', 'icd9_id' => $icd9->id]);

        foreach ([
            [JenisLayanan::Tindakan, $this->infus->id, 52000],
            [JenisLayanan::Kamar, $this->kelas->id, 210000],
        ] as [$jenis, $layananId, $harga]) {
            Tarif::factory()->create([
                'jenis_layanan' => $jenis, 'layanan_id' => $layananId,
                'penjamin_id' => $this->bpjs->id, 'harga' => $harga, 'berlaku_mulai' => '2026-01-01',
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

    private function icd(string $kode): Icd10
    {
        return Icd10::firstOrCreate(['kode' => $kode], ['nama_id' => 'Diagnosa '.$kode]);
    }

    public function test_alur_lengkap_dari_sep_terbit_sampai_klaim_diajukan(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');

        $admisi = $this->penggunaBerperan(Peran::Admisi->value);
        $dokterUser = $this->penggunaBerperan(Peran::Dokter->value, ['dokter_id' => $this->dokter->id]);
        $rekamMedis = $this->penggunaBerperan(Peran::RekamMedis->value);

        $kunjungan = app(PendaftaranKunjungan::class)->daftarkan([
            'pasien_id' => Pasien::factory()->create(['nama' => 'Budi Santoso'])->id,
            'poli_id' => $this->poli->id,
            'dokter_id' => $this->dokter->id,
            'penjamin_id' => $this->bpjs->id,
            'no_kartu_penjamin' => '0001234567890',
            'tanggal' => '2026-06-01',
        ], $admisi);

        // SEP terbit di awal: tanpa itu, pelayanan pasien BPJS tidak terjamin.
        $sep = app(PenerbitanSep::class)->terbitkan($kunjungan, $admisi, 'Demam tifoid');

        $this->assertSame(JenisPelayanan::RawatJalan, $sep->jenis_pelayanan);

        $klinis = app(PemeriksaanKlinis::class);
        app(TindakanPelayanan::class)->tambah($kunjungan, $this->infus->id, 1, $dokterUser);
        $klinis->catatSoap($kunjungan, [
            'subjective' => 'Demam lima hari', 'objective' => 'Suhu 38,9',
            'assessment' => 'Demam tifoid', 'plan' => 'Terapi',
        ], $dokterUser);
        $klinis->tambahDiagnosa($kunjungan, $this->icd('A01.0')->id, JenisDiagnosa::Primer);
        $klinis->tambahDiagnosa($kunjungan, $this->icd('E86')->id, JenisDiagnosa::Sekunder);
        $klinis->selesaikan($kunjungan->refresh(), $dokterUser);

        $berkas = app(PenyusunBerkasKlaim::class)->susun($kunjungan->refresh(), $rekamMedis);

        $this->assertSame(StatusBerkasKlaim::Draf, $berkas->status);
        $this->assertSame($sep->id, $berkas->sep_id);
        $this->assertSame('A01.0', $berkas->diagnosa()->where('jenis', 'primer')->first()->kode);
        $this->assertSame(['38.93'], $berkas->prosedur()->pluck('kode')->all());
        $this->assertSame(52000, (int) $berkas->total_biaya);

        app(PenyusunBerkasKlaim::class)->ajukan($berkas, $rekamMedis);

        $this->assertSame(StatusBerkasKlaim::Diajukan, $berkas->refresh()->status);

        $csv = app(EksporKlaim::class)->csv(Collection::make([$berkas->refresh()]));

        $this->assertStringContainsString($sep->no_sep, $csv);
        $this->assertStringContainsString('Budi Santoso', $csv);

        app(PenyusunBerkasKlaim::class)->tandaiHasil(
            $berkas->refresh(), StatusBerkasKlaim::Disetujui, $rekamMedis, null
        );

        $this->assertSame(StatusBerkasKlaim::Disetujui, $berkas->refresh()->status);
    }

    public function test_klaim_rawat_inap_memuat_kelas_lama_rawat_dan_seluruh_biayanya(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');

        $admisi = $this->penggunaBerperan(Peran::Admisi->value);
        $dokterUser = $this->penggunaBerperan(Peran::Dokter->value, ['dokter_id' => $this->dokter->id]);
        $rekamMedis = $this->penggunaBerperan(Peran::RekamMedis->value);
        $klinis = app(PemeriksaanKlinis::class);

        $kunjungan = app(PendaftaranKunjungan::class)->daftarkan([
            'pasien_id' => Pasien::factory()->create(['nama' => 'Siti Aminah'])->id,
            'poli_id' => $this->poli->id,
            'dokter_id' => $this->dokter->id,
            'penjamin_id' => $this->bpjs->id,
            'no_kartu_penjamin' => '0009876543210',
            'tanggal' => '2026-06-01',
        ], $admisi);

        app(TindakanPelayanan::class)->tambah($kunjungan, $this->infus->id, 2, $dokterUser);
        $klinis->catatSoap($kunjungan, [
            'subjective' => 'Muntah', 'objective' => 'Turgor menurun',
            'assessment' => 'Dehidrasi', 'plan' => 'Rawat inap',
        ], $dokterUser);
        $klinis->tambahDiagnosa($kunjungan, $this->icd('E86')->id, JenisDiagnosa::Primer);

        $rawatInap = app(PerintahRawatInap::class)
            ->terbitkan($kunjungan->refresh(), $dokterUser, 'Dehidrasi sedang', $this->kelas);

        app(PenempatanBed::class)->tempatkan($rawatInap, Bed::factory()->create([
            'ruang_id' => $this->ruang->id, 'kelas_kamar_id' => $this->kelas->id, 'nomor' => '01',
        ]), $admisi);

        app(PenerbitanSep::class)->terbitkan($kunjungan->refresh(), $admisi, 'Dehidrasi');

        Carbon::setTestNow('2026-06-05 10:00:00');
        app(PemulanganPasien::class)->pulangkan(
            $rawatInap->refresh(), $dokterUser, $this->icd('E86')->id, CaraPulang::Sembuh, null
        );

        $berkas = app(PenyusunBerkasKlaim::class)->susun($kunjungan->refresh(), $rekamMedis);

        $this->assertSame(JenisPelayanan::RawatInap, $berkas->jenis_pelayanan);
        $this->assertSame('Kelas 2', $berkas->kelas_rawat);
        $this->assertSame(4, $berkas->lama_rawat);
        $this->assertSame('2026-06-05', $berkas->tanggal_pulang->toDateString());
        // 4 hari × 210.000 kamar + 2 × 52.000 infus
        $this->assertSame(4 * 210000 + 2 * 52000, (int) $berkas->total_biaya);
        $this->assertSame(2, (int) $berkas->prosedur()->first()->jumlah);
    }

    public function test_laporan_membaca_data_yang_sama_dengan_yang_dihasilkan_alurnya(): void
    {
        $this->test_klaim_rawat_inap_memuat_kelas_lama_rawat_dan_seluruh_biayanya();

        Carbon::setTestNow('2026-07-01 09:00:00');
        $juni = RentangTanggal::dari('2026-06-01', '2026-06-30');

        $indikator = app(IndikatorRawatInap::class)->hitung($juni);
        $morbiditas = app(LaporanMorbiditas::class)->sepuluhBesar($juni);
        $pendapatan = app(LaporanPendapatan::class)->perPenjamin($juni);
        $kunjungan = app(LaporanKunjungan::class)->perPoli($juni);

        // Angka yang muncul di laporan harus angka yang sama dengan yang
        // dihasilkan alurnya, bukan hitungan terpisah yang bisa berselisih.
        $this->assertSame(4, $indikator['hari_rawat']);
        $this->assertSame(1, $indikator['pasien_keluar']);
        $this->assertSame(['E86'], $morbiditas->pluck('kode')->all());
        $this->assertSame(4 * 210000 + 2 * 52000, $pendapatan->firstWhere('penjamin', 'BPJS Kesehatan')['ditanggung_penjamin']);
        $this->assertSame(0, $pendapatan->sum('lunas'));
        $this->assertSame(1, $kunjungan->firstWhere('poli', 'Poli Penyakit Dalam')['rawat_inap']);
    }
}
