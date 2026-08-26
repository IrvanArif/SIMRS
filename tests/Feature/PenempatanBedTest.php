<?php

namespace Tests\Feature;

use App\Enums\JenisLayanan;
use App\Enums\StatusRawatInap;
use App\Models\Bed;
use App\Models\KelasKamar;
use App\Models\Kunjungan;
use App\Models\OkupansiBed;
use App\Models\Penjamin;
use App\Models\RawatInap;
use App\Models\Ruang;
use App\Models\Tarif;
use App\Models\User;
use App\Services\PenempatanBed;
use App\Services\PerintahRawatInap;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class PenempatanBedTest extends TestCase
{
    use RefreshDatabase;

    private Penjamin $umum;

    private KelasKamar $kelas2;

    private KelasKamar $vip;

    private Ruang $melati;

    protected function setUp(): void
    {
        parent::setUp();

        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $this->melati = Ruang::factory()->create(['nama' => 'Melati']);
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

    private function bed(string $nomor = '01', ?KelasKamar $kelas = null, bool $aktif = true): Bed
    {
        return Bed::factory()->create([
            'ruang_id' => $this->melati->id,
            'kelas_kamar_id' => ($kelas ?? $this->kelas2)->id,
            'nomor' => $nomor,
            'aktif' => $aktif,
        ]);
    }

    private function rawatInap(): RawatInap
    {
        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]);

        return app(PerintahRawatInap::class)
            ->terbitkan($kunjungan, User::factory()->create(), 'Dehidrasi berat', $this->kelas2);
    }

    public function test_penempatan_mencatat_okupansi_dan_mengunci_bednya(): void
    {
        $rawatInap = $this->rawatInap();
        $bed = $this->bed();

        $okupansi = app(PenempatanBed::class)->tempatkan($rawatInap, $bed, User::factory()->create());

        $this->assertSame($bed->id, $okupansi->bed_id);
        $this->assertNull($okupansi->selesai);
        $this->assertSame($rawatInap->id, $bed->refresh()->rawat_inap_id);
        $this->assertTrue($bed->refresh()->terisi());
    }

    public function test_penempatan_menyalin_tarif_kelas_saat_itu(): void
    {
        $okupansi = app(PenempatanBed::class)
            ->tempatkan($this->rawatInap(), $this->bed(), User::factory()->create());

        $this->assertSame(300000, (int) $okupansi->tarif_harian);

        Tarif::query()->update(['harga' => 999000]);

        $this->assertSame(300000, (int) $okupansi->refresh()->tarif_harian);
    }

    public function test_penempatan_mengisi_waktu_masuk_masa_rawat(): void
    {
        $rawatInap = $this->rawatInap();

        $this->assertNull($rawatInap->waktu_masuk);

        app(PenempatanBed::class)->tempatkan($rawatInap, $this->bed(), User::factory()->create());

        $this->assertNotNull($rawatInap->refresh()->waktu_masuk);
    }

    public function test_bed_terisi_tidak_bisa_ditempati_pasien_lain(): void
    {
        $bed = $this->bed();
        app(PenempatanBed::class)->tempatkan($this->rawatInap(), $bed, User::factory()->create());

        $this->expectException(RuntimeException::class);

        app(PenempatanBed::class)->tempatkan($this->rawatInap(), $bed->refresh(), User::factory()->create());
    }

    public function test_pesan_penolakan_menyebut_nomor_bed(): void
    {
        $bed = $this->bed('07');
        app(PenempatanBed::class)->tempatkan($this->rawatInap(), $bed, User::factory()->create());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('07');

        app(PenempatanBed::class)->tempatkan($this->rawatInap(), $bed->refresh(), User::factory()->create());
    }

    public function test_batasan_unik_basis_data_menolak_okupansi_ganda(): void
    {
        $rawatInap = $this->rawatInap();
        app(PenempatanBed::class)->tempatkan($rawatInap, $this->bed('01'), User::factory()->create());

        $lain = $this->bed('02');

        // Menembus service: penjagaan di service melindungi dari balapan, tapi
        // batasan uniklah yang menolak jalur tulis yang belum terbayang.
        $this->expectException(QueryException::class);

        Bed::whereKey($lain->id)->update(['rawat_inap_id' => $rawatInap->id]);
    }

    public function test_pasien_yang_sudah_di_bed_tidak_bisa_ditempatkan_lagi(): void
    {
        $rawatInap = $this->rawatInap();
        app(PenempatanBed::class)->tempatkan($rawatInap, $this->bed('01'), User::factory()->create());

        $this->expectException(RuntimeException::class);

        app(PenempatanBed::class)->tempatkan($rawatInap->refresh(), $this->bed('02'), User::factory()->create());
    }

    public function test_bed_nonaktif_tidak_bisa_ditempati(): void
    {
        $this->expectException(RuntimeException::class);

        app(PenempatanBed::class)->tempatkan(
            $this->rawatInap(), $this->bed('09', aktif: false), User::factory()->create()
        );
    }

    public function test_masa_rawat_yang_sudah_batal_tidak_bisa_ditempatkan(): void
    {
        $rawatInap = $this->rawatInap();
        app(PerintahRawatInap::class)->batalkan($rawatInap, User::factory()->create(), 'Pasien menolak');

        $this->expectException(RuntimeException::class);

        app(PenempatanBed::class)->tempatkan($rawatInap->refresh(), $this->bed(), User::factory()->create());
    }

    public function test_pindah_bed_menutup_penggal_lama_dan_membuka_penggal_baru(): void
    {
        $rawatInap = $this->rawatInap();
        $layanan = app(PenempatanBed::class);
        $lama = $this->bed('01');
        $baru = $this->bed('02', $this->vip);

        $layanan->tempatkan($rawatInap, $lama, User::factory()->create());
        $layanan->pindahkan($rawatInap->refresh(), $baru, User::factory()->create(), 'Naik kelas atas permintaan keluarga');

        $penggal = OkupansiBed::where('rawat_inap_id', $rawatInap->id)->orderBy('id')->get();

        $this->assertCount(2, $penggal);
        $this->assertNotNull($penggal[0]->selesai);
        $this->assertNull($penggal[1]->selesai);
        $this->assertSame($baru->id, $penggal[1]->bed_id);
        $this->assertSame(750000, (int) $penggal[1]->tarif_harian);
    }

    public function test_pindah_bed_melepaskan_bed_lama_sehingga_bisa_dipakai_pasien_lain(): void
    {
        $rawatInap = $this->rawatInap();
        $layanan = app(PenempatanBed::class);
        $lama = $this->bed('01');
        $baru = $this->bed('02');

        $layanan->tempatkan($rawatInap, $lama, User::factory()->create());
        $layanan->pindahkan($rawatInap->refresh(), $baru, User::factory()->create(), 'Pindah ruang');

        $this->assertNull($lama->refresh()->rawat_inap_id);
        $this->assertSame($rawatInap->id, $baru->refresh()->rawat_inap_id);

        // Bed lama benar-benar bisa dipakai pasien berikutnya.
        $layanan->tempatkan($this->rawatInap(), $lama->refresh(), User::factory()->create());

        $this->assertTrue($lama->refresh()->terisi());
    }

    public function test_pindah_ke_bed_yang_sama_ditolak(): void
    {
        $rawatInap = $this->rawatInap();
        $bed = $this->bed();
        $layanan = app(PenempatanBed::class);

        $layanan->tempatkan($rawatInap, $bed, User::factory()->create());

        $this->expectException(RuntimeException::class);

        $layanan->pindahkan($rawatInap->refresh(), $bed->refresh(), User::factory()->create(), 'Tidak berpindah');
    }

    public function test_pindah_bed_wajib_beralasan(): void
    {
        $rawatInap = $this->rawatInap();
        $layanan = app(PenempatanBed::class);

        $layanan->tempatkan($rawatInap, $this->bed('01'), User::factory()->create());

        $this->expectException(ValidationException::class);

        $layanan->pindahkan($rawatInap->refresh(), $this->bed('02'), User::factory()->create(), '   ');
    }

    public function test_alasan_pindah_tercatat_di_audit_log(): void
    {
        $rawatInap = $this->rawatInap();
        $layanan = app(PenempatanBed::class);

        $layanan->tempatkan($rawatInap, $this->bed('01'), User::factory()->create());
        $layanan->pindahkan($rawatInap->refresh(), $this->bed('02'), User::factory()->create(), 'Ruangan direnovasi');

        $this->assertDatabaseHas('audit_logs', ['alasan' => 'Ruangan direnovasi']);
    }

    public function test_pindah_bed_tidak_bisa_dilakukan_sebelum_pasien_menempati_bed(): void
    {
        $this->expectException(RuntimeException::class);

        app(PenempatanBed::class)->pindahkan(
            $this->rawatInap(), $this->bed(), User::factory()->create(), 'Belum masuk'
        );
    }
}
