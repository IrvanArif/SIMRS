<?php

namespace Tests\Feature;

use App\Enums\CaraPulang;
use App\Enums\JenisDiagnosa;
use App\Enums\JenisLayanan;
use App\Models\Bed;
use App\Models\Icd10;
use App\Models\KelasKamar;
use App\Models\Kunjungan;
use App\Models\Penjamin;
use App\Models\RawatInap;
use App\Models\Ruang;
use App\Models\Tarif;
use App\Models\User;
use App\Services\PemeriksaanKlinis;
use App\Services\PemulanganPasien;
use App\Services\PenempatanBed;
use App\Services\PenghitungBiayaKamar;
use App\Services\PerintahRawatInap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BiayaKamarTest extends TestCase
{
    use RefreshDatabase;

    private Penjamin $umum;

    private KelasKamar $kelas2;

    private KelasKamar $vip;

    private Ruang $ruang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $this->ruang = Ruang::factory()->create(['nama' => 'Melati']);
        $this->kelas2 = KelasKamar::factory()->create(['nama' => 'Kelas 2']);
        $this->vip = KelasKamar::factory()->create(['nama' => 'VIP']);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Kamar, 'layanan_id' => $this->kelas2->id,
            'penjamin_id' => $this->umum->id, 'harga' => 300000, 'berlaku_mulai' => '2026-01-01',
        ]);
        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Kamar, 'layanan_id' => $this->vip->id,
            'penjamin_id' => $this->umum->id, 'harga' => 750000, 'berlaku_mulai' => '2026-01-01',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function bed(string $nomor, ?KelasKamar $kelas = null): Bed
    {
        return Bed::factory()->create([
            'ruang_id' => $this->ruang->id,
            'kelas_kamar_id' => ($kelas ?? $this->kelas2)->id,
            'nomor' => $nomor,
        ]);
    }

    private function masuk(string $tanggal, Bed $bed): RawatInap
    {
        Carbon::setTestNow($tanggal.' 08:00:00');

        $kunjungan = Kunjungan::factory()->create([
            'penjamin_id' => $this->umum->id, 'tanggal' => $tanggal,
        ]);
        $dokter = User::factory()->create(['dokter_id' => $kunjungan->dokter_id]);

        app(PemeriksaanKlinis::class)->catatSoap($kunjungan, [
            'subjective' => 'Muntah', 'objective' => 'Turgor menurun',
            'assessment' => 'Dehidrasi', 'plan' => 'Rawat inap',
        ], $dokter);
        app(PemeriksaanKlinis::class)
            ->tambahDiagnosa($kunjungan, Icd10::factory()->create()->id, JenisDiagnosa::Primer);

        $rawatInap = app(PerintahRawatInap::class)
            ->terbitkan($kunjungan->refresh(), $dokter, 'Dehidrasi', $this->kelas2);

        app(PenempatanBed::class)->tempatkan($rawatInap, $bed, User::factory()->create());

        return $rawatInap->refresh();
    }

    private function pulang(RawatInap $rawatInap, string $tanggal): RawatInap
    {
        Carbon::setTestNow($tanggal.' 10:00:00');

        return app(PemulanganPasien::class)->pulangkan(
            $rawatInap,
            User::factory()->create(['dokter_id' => $rawatInap->kunjungan->dokter_id]),
            Icd10::factory()->create()->id,
            CaraPulang::Sembuh,
            null
        );
    }

    public function test_lama_rawat_sehari_saat_masuk_dan_pulang_di_tanggal_sama(): void
    {
        $rawatInap = $this->masuk('2026-03-01', $this->bed('01'));
        $this->pulang($rawatInap, '2026-03-01');

        // Kamarnya tetap tidak bisa dijual ke orang lain hari itu.
        $this->assertSame(1, app(PenghitungBiayaKamar::class)->lamaRawat($rawatInap->refresh()));
        $this->assertSame(300000, app(PenghitungBiayaKamar::class)->total($rawatInap->refresh()));
    }

    public function test_lama_rawat_lima_hari_dihitung_dari_selisih_tanggal(): void
    {
        $rawatInap = $this->masuk('2026-03-01', $this->bed('01'));
        $this->pulang($rawatInap, '2026-03-06');

        $this->assertSame(5, app(PenghitungBiayaKamar::class)->lamaRawat($rawatInap->refresh()));
        $this->assertSame(5 * 300000, app(PenghitungBiayaKamar::class)->total($rawatInap->refresh()));
    }

    public function test_pindah_kelas_menghasilkan_dua_baris_tarif_berbeda(): void
    {
        $rawatInap = $this->masuk('2026-03-01', $this->bed('01'));

        Carbon::setTestNow('2026-03-04 09:00:00');
        app(PenempatanBed::class)->pindahkan(
            $rawatInap->refresh(), $this->bed('02', $this->vip), User::factory()->create(), 'Naik kelas'
        );

        $this->pulang($rawatInap->refresh(), '2026-03-06');

        $penggal = app(PenghitungBiayaKamar::class)->penggal($rawatInap->refresh());

        $this->assertCount(2, $penggal);
        $this->assertSame(3, $penggal[0]['hari']);
        $this->assertSame(3 * 300000, $penggal[0]['subtotal']);
        $this->assertSame(2, $penggal[1]['hari']);
        $this->assertSame(2 * 750000, $penggal[1]['subtotal']);
    }

    public function test_hari_peralihan_menjadi_milik_penggal_yang_baru(): void
    {
        $rawatInap = $this->masuk('2026-03-01', $this->bed('01'));

        Carbon::setTestNow('2026-03-04 09:00:00');
        app(PenempatanBed::class)->pindahkan(
            $rawatInap->refresh(), $this->bed('02', $this->vip), User::factory()->create(), 'Naik kelas'
        );

        $okupansi = $rawatInap->refresh()->okupansi;

        // Kamar barulah yang ditempati pada malam 4 Maret.
        $this->assertSame('2026-03-04', $okupansi[0]->selesai->toDateString());
        $this->assertSame('2026-03-04', $okupansi[1]->mulai->toDateString());
    }

    public function test_jumlah_hari_seluruh_penggal_sama_dengan_lama_rawat(): void
    {
        $rawatInap = $this->masuk('2026-03-01', $this->bed('01'));

        Carbon::setTestNow('2026-03-04 09:00:00');
        app(PenempatanBed::class)->pindahkan(
            $rawatInap->refresh(), $this->bed('02', $this->vip), User::factory()->create(), 'Naik kelas'
        );

        $this->pulang($rawatInap->refresh(), '2026-03-06');

        $penghitung = app(PenghitungBiayaKamar::class);
        $rawatInap->refresh();

        // Inilah penjaganya: tanpa selang setengah terbuka, satu hari hilang
        // tanpa jejak dan pasien ditagih kurang tanpa ada yang tahu.
        $this->assertSame(
            $penghitung->lamaRawat($rawatInap),
            collect($penghitung->penggal($rawatInap))->sum('hari')
        );
    }

    public function test_tiap_penggal_minimal_satu_hari(): void
    {
        $rawatInap = $this->masuk('2026-03-01', $this->bed('01'));

        // Pindah di hari yang sama: dua kamar sama-sama terpakai hari itu.
        app(PenempatanBed::class)->pindahkan(
            $rawatInap->refresh(), $this->bed('02', $this->vip), User::factory()->create(), 'Salah tempat'
        );

        $this->pulang($rawatInap->refresh(), '2026-03-03');

        $penggal = app(PenghitungBiayaKamar::class)->penggal($rawatInap->refresh());

        $this->assertSame(1, $penggal[0]['hari']);
        $this->assertSame(2, $penggal[1]['hari']);
    }

    public function test_perubahan_master_tarif_tidak_mengubah_masa_rawat_berjalan(): void
    {
        $rawatInap = $this->masuk('2026-03-01', $this->bed('01'));

        Tarif::query()->update(['harga' => 9999000]);

        $this->pulang($rawatInap->refresh(), '2026-03-03');

        $this->assertSame(2 * 300000, app(PenghitungBiayaKamar::class)->total($rawatInap->refresh()));
    }

    public function test_biaya_berjalan_bisa_dibaca_sebelum_pasien_pulang(): void
    {
        $rawatInap = $this->masuk('2026-03-01', $this->bed('01'));

        Carbon::setTestNow('2026-03-04 09:00:00');

        // Keluarga pasien lazim menanyakan biaya sementara; angkanya harus bisa
        // dibaca tanpa memulangkan pasien lebih dulu.
        $this->assertSame(3, app(PenghitungBiayaKamar::class)->lamaRawat($rawatInap->refresh()));
        $this->assertSame(3 * 300000, app(PenghitungBiayaKamar::class)->total($rawatInap->refresh()));
    }
}
