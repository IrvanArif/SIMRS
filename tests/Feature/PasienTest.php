<?php

namespace Tests\Feature;

use App\Models\Pasien;
use App\Services\PendaftaranPasien;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PasienTest extends TestCase
{
    use RefreshDatabase;

    private function layanan(): PendaftaranPasien
    {
        return app(PendaftaranPasien::class);
    }

    private function dataSah(array $ganti = []): array
    {
        return array_merge([
            'nik' => '3202011203900001',
            'nama' => 'Siti Aminah',
            'tempat_lahir' => 'Kabupaten Sampel',
            'tanggal_lahir' => '1990-03-12',
            'jenis_kelamin' => 'P',
            'alamat' => 'Jl. Melati No. 12',
            'kelurahan' => 'Sukamaju',
            'kecamatan' => 'Sukamaju',
            'kabupaten' => 'Kabupaten Sampel',
            'no_hp' => '081234567890',
        ], $ganti);
    }

    public function test_pendaftaran_pasien_baru_mendapat_nomor_rekam_medis_berurutan(): void
    {
        $pertama = $this->layanan()->daftarkan($this->dataSah());
        $kedua = $this->layanan()->daftarkan($this->dataSah(['nik' => '3202011203900002']));

        $this->assertSame('000001', $pertama->no_rm);
        $this->assertSame('000002', $kedua->no_rm);
    }

    public function test_nik_kurang_dari_16_digit_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        $this->layanan()->daftarkan($this->dataSah(['nik' => '320201120390']));
    }

    public function test_nik_berisi_huruf_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        $this->layanan()->daftarkan($this->dataSah(['nik' => '32020112039000AB']));
    }

    public function test_nik_yang_sudah_terdaftar_ditolak(): void
    {
        $this->layanan()->daftarkan($this->dataSah());

        $this->expectException(ValidationException::class);

        $this->layanan()->daftarkan($this->dataSah(['nama' => 'Orang Lain']));
    }

    public function test_tanggal_lahir_di_masa_depan_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        $this->layanan()->daftarkan($this->dataSah(['tanggal_lahir' => now()->addDay()->toDateString()]));
    }

    public function test_jenis_kelamin_selain_l_dan_p_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        $this->layanan()->daftarkan($this->dataSah(['jenis_kelamin' => 'X']));
    }

    public function test_pembaruan_boleh_memakai_nik_miliknya_sendiri(): void
    {
        $pasien = $this->layanan()->daftarkan($this->dataSah());

        $diperbarui = $this->layanan()->perbarui($pasien, $this->dataSah(['nama' => 'Siti Aminah binti Umar']));

        $this->assertSame('Siti Aminah binti Umar', $diperbarui->nama);
        $this->assertSame('000001', $diperbarui->no_rm);
    }

    public function test_nomor_rekam_medis_tidak_dipakai_ulang_setelah_pasien_dihapus(): void
    {
        $pertama = $this->layanan()->daftarkan($this->dataSah());
        $pertama->delete();

        $kedua = $this->layanan()->daftarkan($this->dataSah(['nik' => '3202011203900002']));

        $this->assertSame('000002', $kedua->no_rm);
        $this->assertSoftDeleted('pasien', ['id' => $pertama->id]);
    }

    public function test_pasien_bisa_dicari_berdasarkan_nik_nama_atau_nomor_rm(): void
    {
        $this->layanan()->daftarkan($this->dataSah());

        $this->assertSame(1, Pasien::cari('aminah')->count());
        $this->assertSame(1, Pasien::cari('3202011203900001')->count());
        $this->assertSame(1, Pasien::cari('000001')->count());
        $this->assertSame(0, Pasien::cari('tidak ada')->count());
    }
}
