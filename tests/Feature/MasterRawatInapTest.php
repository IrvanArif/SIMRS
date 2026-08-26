<?php

namespace Tests\Feature;

use App\Enums\JenisLayanan;
use App\Models\Bed;
use App\Models\KelasKamar;
use App\Models\Penjamin;
use App\Models\Ruang;
use App\Models\Tarif;
use App\Services\PencariTarif;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterRawatInapTest extends TestCase
{
    use RefreshDatabase;

    public function test_bed_menyimpan_kelas_dan_ruangnya(): void
    {
        $ruang = Ruang::factory()->create(['nama' => 'Melati']);
        $kelas = KelasKamar::factory()->create(['nama' => 'Kelas 3']);

        $bed = Bed::factory()->create([
            'ruang_id' => $ruang->id, 'kelas_kamar_id' => $kelas->id, 'nomor' => '03',
        ]);

        $this->assertSame('Melati', $bed->ruang->nama);
        $this->assertSame('Kelas 3', $bed->kelas->nama);
        $this->assertTrue($bed->aktif);
        $this->assertFalse($bed->terisi());
    }

    public function test_nomor_bed_ganda_dalam_satu_ruang_ditolak_database(): void
    {
        $ruang = Ruang::factory()->create();
        Bed::factory()->create(['ruang_id' => $ruang->id, 'nomor' => '01']);

        $this->expectException(QueryException::class);

        Bed::factory()->create(['ruang_id' => $ruang->id, 'nomor' => '01']);
    }

    public function test_nomor_bed_sama_boleh_di_ruang_berbeda(): void
    {
        Bed::factory()->create(['ruang_id' => Ruang::factory()->create()->id, 'nomor' => '01']);
        $kedua = Bed::factory()->create(['ruang_id' => Ruang::factory()->create()->id, 'nomor' => '01']);

        $this->assertSame('01', $kedua->nomor);
        $this->assertSame(2, Bed::count());
    }

    public function test_tarif_kamar_memakai_tabel_tarif_yang_sama(): void
    {
        $kelas = KelasKamar::factory()->create();
        $umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Kamar,
            'layanan_id' => $kelas->id,
            'penjamin_id' => $umum->id,
            'harga' => 300000,
            'berlaku_mulai' => '2026-01-01',
        ]);

        $this->assertSame(
            300000,
            app(PencariTarif::class)->untuk(JenisLayanan::Kamar, $kelas->id, $umum->id)
        );
    }

    public function test_nama_layanan_pada_tarif_menampilkan_nama_kelas(): void
    {
        $kelas = KelasKamar::factory()->create(['nama' => 'Kelas 1']);
        $umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);

        $tarif = Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Kamar,
            'layanan_id' => $kelas->id,
            'penjamin_id' => $umum->id,
            'harga' => 450000,
            'berlaku_mulai' => '2026-01-01',
        ]);

        $this->assertSame('Kelas 1', $tarif->namaLayanan());
    }

    public function test_bed_kosong_terpilih_oleh_scope_kosong(): void
    {
        $kosong = Bed::factory()->create();
        $nonaktif = Bed::factory()->create(['aktif' => false]);

        $terpilih = Bed::kosong()->pluck('id');

        $this->assertTrue($terpilih->contains($kosong->id));
        // Bed nonaktif bukan bed kosong: ia tidak tersedia, hanya kebetulan
        // tidak ada pasiennya.
        $this->assertFalse($terpilih->contains($nonaktif->id));
    }
}
