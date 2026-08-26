<?php

namespace Tests\Feature;

use App\Enums\JenisDiagnosa;
use App\Enums\JenisLayanan;
use App\Enums\MetodePembayaran;
use App\Enums\Peran;
use App\Enums\StatusKunjungan;
use App\Enums\StatusOrderRadiologi;
use App\Enums\StatusTagihan;
use App\Models\Dokter;
use App\Models\EkspertiseRadiologi;
use App\Models\Icd10;
use App\Models\Kunjungan;
use App\Models\OrderRadiologiDetail;
use App\Models\Pasien;
use App\Models\PemeriksaanRadiologi;
use App\Models\Penjamin;
use App\Models\Poli;
use App\Models\Tarif;
use App\Models\Tindakan;
use App\Models\TindakanKunjungan;
use App\Models\User;
use App\Services\PelaksanaanRadiologi;
use App\Services\PemeriksaanKlinis;
use App\Services\PemesananRadiologi;
use App\Services\PendaftaranKunjungan;
use App\Services\PenulisanEkspertise;
use App\Services\ProsesPembayaran;
use App\Services\TindakanPelayanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AlurRadiologiTest extends TestCase
{
    use RefreshDatabase;

    private Poli $poli;

    private Dokter $dokter;

    private Tindakan $konsultasi;

    private PemeriksaanRadiologi $toraks;

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

        $this->toraks = PemeriksaanRadiologi::factory()->create([
            'nama' => 'Rontgen Toraks PA', 'modalitas' => 'rontgen',
        ]);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Tindakan, 'layanan_id' => $this->konsultasi->id,
            'penjamin_id' => $this->umum->id, 'harga' => 50000, 'berlaku_mulai' => '2026-01-01',
        ]);
        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Radiologi, 'layanan_id' => $this->toraks->id,
            'penjamin_id' => $this->umum->id, 'harga' => 150000, 'berlaku_mulai' => '2026-01-01',
        ]);
    }

    private function penggunaBerperan(string $peran, array $atribut = []): User
    {
        $user = User::factory()->create($atribut);
        $user->assignRole($peran);

        return $user;
    }

    private function kunjunganBaru(string $nik): Kunjungan
    {
        $pasien = Pasien::factory()->create(['nik' => $nik]);

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
        $kunjungan = $this->kunjunganBaru('3202011203900011');

        $perawat = $this->penggunaBerperan(Peran::Perawat->value);
        $dokterUser = $this->penggunaBerperan(Peran::Dokter->value, ['dokter_id' => $this->dokter->id]);
        $radiografer = $this->penggunaBerperan(Peran::Radiografer->value);
        $dokterRadiologi = $this->penggunaBerperan(Peran::Dokter->value);
        $kasir = $this->penggunaBerperan(Peran::Kasir->value);
        $klinis = app(PemeriksaanKlinis::class);

        $klinis->catatVital($kunjungan, [
            'sistolik' => 118, 'diastolik' => 76, 'nadi' => 88, 'suhu' => 37.4,
            'respirasi' => 22, 'berat_badan' => 61.0, 'tinggi_badan' => 170,
            'keluhan_awal' => 'Batuk tiga minggu',
        ], $perawat);

        $klinis->catatSoap($kunjungan, [
            'subjective' => 'Batuk berdahak tiga minggu', 'objective' => 'Ronki basah kasar lapang paru kanan',
            'assessment' => 'Suspek bronkitis', 'plan' => 'Rontgen toraks',
        ], $dokterUser);

        app(TindakanPelayanan::class)->tambah($kunjungan, $this->konsultasi->id, 1, $dokterUser);
        $klinis->tambahDiagnosa($kunjungan, Icd10::factory()->create()->id, JenisDiagnosa::Primer);

        $order = app(PemesananRadiologi::class)
            ->pesan($kunjungan, [$this->toraks->id], $dokterUser, 'Batuk kronis tiga minggu');

        // Kunjungan tertahan sampai ekspertise ditulis (aturan 50).
        try {
            $klinis->selesaikan($kunjungan->refresh(), $dokterUser);
            $this->fail('Kunjungan seharusnya tertahan sampai ekspertise ditulis.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString($order->no_order, $e->getMessage());
        }

        app(PelaksanaanRadiologi::class)->kerjakan($order, 'FILM-2026-0042', $radiografer);

        // Citra sudah diambil, tapi belum ada yang membacanya.
        $this->assertFalse($order->refresh()->terbacaDokter());

        app(PenulisanEkspertise::class)->tulis($order->refresh(), [
            $order->detail->first()->id => [
                'temuan' => 'Corakan bronkovaskular meningkat, tidak tampak infiltrat maupun efusi.',
                'kesan' => 'Bronkitis kronis.',
                'saran' => null,
            ],
        ], $dokterRadiologi);

        $this->assertSame(StatusOrderRadiologi::Selesai, $order->refresh()->status);
        $this->assertTrue($order->refresh()->terbacaDokter());

        $klinis->selesaikan($kunjungan->refresh(), $dokterUser);

        $this->assertSame(StatusKunjungan::Selesai, $kunjungan->refresh()->status);

        $tagihan = $kunjungan->refresh()->tagihan;
        $ringkasan = $tagihan->detail()
            ->selectRaw('sumber_tipe, SUM(subtotal) AS total')
            ->groupBy('sumber_tipe')
            ->pluck('total', 'sumber_tipe');

        $this->assertSame(200000, (int) $tagihan->total);
        $this->assertSame(50000, (int) $ringkasan[TindakanKunjungan::class]);
        $this->assertSame(150000, (int) $ringkasan[OrderRadiologiDetail::class]);

        app(ProsesPembayaran::class)->bayar(
            $tagihan, MetodePembayaran::Tunai, (int) $tagihan->ditagihkan_ke_pasien, $kasir
        );

        $this->assertSame(StatusTagihan::Lunas, $tagihan->refresh()->status);
    }

    public function test_pekerjaan_radiografer_dan_dokter_tercatat_terpisah(): void
    {
        $kunjungan = $this->kunjunganBaru('3202011203900012');

        $dokterUser = $this->penggunaBerperan(Peran::Dokter->value, ['dokter_id' => $this->dokter->id]);
        $radiografer = $this->penggunaBerperan(Peran::Radiografer->value);
        $dokterRadiologi = $this->penggunaBerperan(Peran::Dokter->value);

        $order = app(PemesananRadiologi::class)
            ->pesan($kunjungan, [$this->toraks->id], $dokterUser, 'Batuk kronis');

        app(PelaksanaanRadiologi::class)->kerjakan($order, 'FILM-2026-0043', $radiografer);
        app(PenulisanEkspertise::class)->tulis($order->refresh(), [
            $order->detail->first()->id => [
                'temuan' => 'Tidak tampak kelainan pada kedua lapang paru.',
                'kesan' => 'Toraks dalam batas normal.',
                'saran' => null,
            ],
        ], $dokterRadiologi);

        $order->refresh();

        // Siapa yang memotret dan siapa yang menyimpulkan harus bisa dibedakan
        // saat hasilnya dipersoalkan di kemudian hari (aturan 54).
        $this->assertSame($radiografer->id, $order->dikerjakan_oleh);
        $this->assertSame($dokterRadiologi->id, $order->ditulis_oleh);
        $this->assertNotSame($order->dikerjakan_oleh, $order->ditulis_oleh);
        $this->assertSame($dokterUser->id, $order->dokter_id);

        $this->assertSame(
            'Toraks dalam batas normal.',
            EkspertiseRadiologi::first()->kesan
        );
    }
}
