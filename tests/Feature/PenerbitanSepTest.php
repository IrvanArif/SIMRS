<?php

namespace Tests\Feature;

use App\Enums\JenisPelayanan;
use App\Enums\JenisLayanan;
use App\Kontrak\PenerbitSep;
use App\Models\Bed;
use App\Models\KelasKamar;
use App\Models\Kunjungan;
use App\Models\Penjamin;
use App\Models\Ruang;
use App\Models\Sep;
use App\Models\Tarif;
use App\Models\User;
use App\Services\PenempatanBed;
use App\Services\PenerbitanSep;
use App\Services\PerintahRawatInap;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class PenerbitanSepTest extends TestCase
{
    use RefreshDatabase;

    private Penjamin $bpjs;

    private Penjamin $umum;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bpjs = Penjamin::factory()->create(['kode' => 'BPJS', 'nama' => 'BPJS Kesehatan', 'jenis' => 'penjamin']);
        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'nama' => 'Umum (Tunai)', 'jenis' => 'tunai']);
    }

    private function kunjunganBpjs(array $ganti = []): Kunjungan
    {
        return Kunjungan::factory()->create(array_merge([
            'penjamin_id' => $this->bpjs->id,
            'no_kartu_penjamin' => '0001234567890',
        ], $ganti));
    }

    private function terbitkan(?Kunjungan $kunjungan = null, string $diagnosaAwal = 'Demam tifoid'): Sep
    {
        return app(PenerbitanSep::class)->terbitkan(
            $kunjungan ?? $this->kunjunganBpjs(), User::factory()->create(), $diagnosaAwal
        );
    }

    public function test_sep_terbit_dengan_nomor_dan_tanggal(): void
    {
        $sep = $this->terbitkan();

        $this->assertStringStartsWith('SEP-', $sep->no_sep);
        $this->assertSame('0001234567890', $sep->no_kartu);
        $this->assertSame('Demam tifoid', $sep->diagnosa_awal);
        $this->assertNotNull($sep->tanggal);
        $this->assertSame('lokal', $sep->diterbitkan_dengan);
    }

    public function test_sep_tidak_bisa_diterbitkan_untuk_pasien_umum(): void
    {
        $this->expectException(RuntimeException::class);

        $this->terbitkan(Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]));
    }

    public function test_pesan_penolakan_menyebut_nama_penjaminnya(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Umum (Tunai)');

        $this->terbitkan(Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]));
    }

    public function test_sep_wajib_menyertakan_nomor_kartu_peserta(): void
    {
        $this->expectException(ValidationException::class);

        $this->terbitkan($this->kunjunganBpjs(['no_kartu_penjamin' => null]));
    }

    public function test_sep_wajib_menyertakan_diagnosa_awal(): void
    {
        $this->expectException(ValidationException::class);

        $this->terbitkan(diagnosaAwal: '   ');
    }

    public function test_satu_kunjungan_hanya_boleh_punya_satu_sep_berlaku(): void
    {
        $kunjungan = $this->kunjunganBpjs();
        $this->terbitkan($kunjungan);

        $this->expectException(RuntimeException::class);

        $this->terbitkan($kunjungan->refresh());
    }

    public function test_batasan_unik_menolak_sep_kedua_yang_berlaku(): void
    {
        $pertama = $this->terbitkan();

        // Menembus service: penjagaan di service melindungi alur biasa, batasan
        // uniklah yang menolak jalur tulis yang belum terbayang.
        $this->expectException(QueryException::class);

        Sep::create([
            'no_sep' => 'SEP-20260101-9999',
            'kunjungan_id' => $pertama->kunjungan_id,
            'no_kartu' => '0001234567890',
            'jenis_pelayanan' => JenisPelayanan::RawatJalan,
            'diagnosa_awal' => 'Menembus service',
            'tanggal' => now()->toDateString(),
            'status' => 'berlaku',
        ]);
    }

    public function test_sep_yang_dibatalkan_membuka_jalan_untuk_sep_baru(): void
    {
        $kunjungan = $this->kunjunganBpjs();
        $lama = $this->terbitkan($kunjungan);

        app(PenerbitanSep::class)->batalkan($lama, User::factory()->create(), 'Salah diagnosa awal');

        $baru = $this->terbitkan($kunjungan->refresh(), 'Demam berdarah');

        $this->assertNotSame($lama->no_sep, $baru->no_sep);
        $this->assertSame(2, Sep::count());
    }

    public function test_jenis_pelayanan_rawat_jalan_saat_tidak_ada_masa_rawat(): void
    {
        $this->assertSame(JenisPelayanan::RawatJalan, $this->terbitkan()->jenis_pelayanan);
    }

    public function test_jenis_pelayanan_rawat_inap_saat_ada_masa_rawat(): void
    {
        $kunjungan = $this->kunjunganBpjs();
        $kelas = KelasKamar::factory()->create(['nama' => 'Kelas 2']);

        app(PerintahRawatInap::class)
            ->terbitkan($kunjungan, User::factory()->create(), 'Dehidrasi', $kelas);

        $sep = $this->terbitkan($kunjungan->refresh());

        // Jenis pelayanan mengikuti kenyataan, bukan pilihan petugas (aturan 81).
        $this->assertSame(JenisPelayanan::RawatInap, $sep->jenis_pelayanan);
    }

    public function test_kelas_rawat_diambil_dari_bed_yang_ditempati(): void
    {
        $kunjungan = $this->kunjunganBpjs();
        $kelas = KelasKamar::factory()->create(['nama' => 'Kelas 1']);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Kamar, 'layanan_id' => $kelas->id,
            'penjamin_id' => $this->bpjs->id, 'harga' => 315000, 'berlaku_mulai' => '2026-01-01',
        ]);

        $rawatInap = app(PerintahRawatInap::class)
            ->terbitkan($kunjungan, User::factory()->create(), 'Dehidrasi', $kelas);

        app(PenempatanBed::class)->tempatkan($rawatInap, Bed::factory()->create([
            'ruang_id' => Ruang::factory()->create()->id, 'kelas_kamar_id' => $kelas->id,
        ]), User::factory()->create());

        $this->assertSame('Kelas 1', $this->terbitkan($kunjungan->refresh())->kelas_rawat);
    }

    public function test_kelas_rawat_kosong_untuk_rawat_jalan(): void
    {
        $this->assertNull($this->terbitkan()->kelas_rawat);
    }

    public function test_pembatalan_sep_wajib_beralasan(): void
    {
        $sep = $this->terbitkan();

        $this->expectException(ValidationException::class);

        app(PenerbitanSep::class)->batalkan($sep, User::factory()->create(), '   ');
    }

    public function test_alasan_pembatalan_tercatat_di_audit_log(): void
    {
        $sep = $this->terbitkan();

        app(PenerbitanSep::class)->batalkan($sep, User::factory()->create(), 'Peserta ternyata tidak aktif');

        $this->assertSame('batal', $sep->refresh()->status);
        $this->assertDatabaseHas('audit_logs', ['alasan' => 'Peserta ternyata tidak aktif']);
    }

    public function test_sep_yang_sudah_batal_tidak_bisa_dibatalkan_lagi(): void
    {
        $sep = $this->terbitkan();
        app(PenerbitanSep::class)->batalkan($sep, User::factory()->create(), 'Salah terbit');

        $this->expectException(RuntimeException::class);

        app(PenerbitanSep::class)->batalkan($sep->refresh(), User::factory()->create(), 'Sekali lagi');
    }

    public function test_penerbit_sep_bisa_diganti_tanpa_menyentuh_pemanggilnya(): void
    {
        // Inilah yang mengikat janji spec bagian 2.2: saat kredensial BPJS
        // tersedia, penerapan VClaim tinggal diikat di sini dan seluruh alur
        // tetap bekerja tanpa satu baris pun berubah di PenerbitanSep.
        $this->app->bind(PenerbitSep::class, fn () => new class implements PenerbitSep
        {
            public function terbitkan(Kunjungan $kunjungan, string $diagnosaAwal): string
            {
                return 'VCLAIM-0001';
            }

            public function batalkan(Sep $sep, string $alasan): void {}

            public function nama(): string
            {
                return 'vclaim';
            }
        });

        $sep = $this->terbitkan();

        $this->assertSame('VCLAIM-0001', $sep->no_sep);
        $this->assertSame('vclaim', $sep->diterbitkan_dengan);
    }
}
