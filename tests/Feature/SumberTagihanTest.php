<?php

namespace Tests\Feature;

use App\Enums\JenisLayanan;
use App\Enums\StatusTagihan;
use App\Models\BatchObat;
use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\Penjamin;
use App\Models\ResepDetail;
use App\Models\Tagihan;
use App\Models\Tarif;
use App\Models\Tindakan;
use App\Models\TindakanKunjungan;
use App\Models\User;
use App\Services\PenulisanResep;
use App\Services\PenyiapanResep;
use App\Services\PenyusunTagihan;
use App\Services\TindakanPelayanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SumberTagihanTest extends TestCase
{
    use RefreshDatabase;

    public function test_baris_obat_menunjuk_resep_detail_sebagai_sumbernya(): void
    {
        $umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $obat = Obat::factory()->create(['nama' => 'Paracetamol 500 mg']);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Obat, 'layanan_id' => $obat->id,
            'penjamin_id' => $umum->id, 'harga' => 1500, 'berlaku_mulai' => '2026-01-01',
        ]);

        BatchObat::factory()->create([
            'obat_id' => $obat->id, 'tanggal_kedaluwarsa' => '2029-01-31',
            'jumlah_awal' => 100, 'jumlah_tersisa' => 100,
        ]);

        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $umum->id]);
        $tagihan = Tagihan::factory()->create([
            'kunjungan_id' => $kunjungan->id, 'penjamin_id' => $umum->id,
            'status' => StatusTagihan::BelumBayar,
        ]);

        $resep = app(PenulisanResep::class)->tulis($kunjungan, [[
            'obat_id' => $obat->id, 'jumlah' => 10, 'aturan_pakai' => '3x1',
        ]], User::factory()->create());

        app(PenyiapanResep::class)->siapkan($resep, User::factory()->create());

        $baris = $tagihan->refresh()->detail()->where('sumber_tipe', ResepDetail::class)->first();

        $this->assertNotNull($baris);
        $this->assertInstanceOf(ResepDetail::class, $baris->sumber);
        $this->assertSame('Paracetamol 500 mg', $baris->deskripsi);
    }

    public function test_baris_tindakan_menunjuk_tindakan_kunjungan_sebagai_sumbernya(): void
    {
        $umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $tindakan = Tindakan::factory()->create(['nama' => 'Konsultasi Dokter Umum']);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Tindakan, 'layanan_id' => $tindakan->id,
            'penjamin_id' => $umum->id, 'harga' => 50000, 'berlaku_mulai' => '2026-01-01',
        ]);

        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $umum->id]);
        app(TindakanPelayanan::class)->tambah($kunjungan, $tindakan->id, 1, User::factory()->create());

        $tagihan = app(PenyusunTagihan::class)->susun($kunjungan->refresh());
        $baris = $tagihan->detail()->first();

        $this->assertSame(TindakanKunjungan::class, $baris->sumber_tipe);
        $this->assertInstanceOf(TindakanKunjungan::class, $baris->sumber);
    }

    public function test_seluruh_komponen_biaya_satu_kunjungan_terbaca_dalam_satu_query(): void
    {
        $umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $umum->id]);
        $tagihan = Tagihan::factory()->create([
            'kunjungan_id' => $kunjungan->id, 'penjamin_id' => $umum->id,
        ]);

        $tagihan->detail()->create([
            'sumber_tipe' => TindakanKunjungan::class, 'sumber_id' => 1,
            'deskripsi' => 'Konsultasi', 'jumlah' => 1, 'tarif_satuan' => 50000, 'subtotal' => 50000,
        ]);
        $tagihan->detail()->create([
            'sumber_tipe' => ResepDetail::class, 'sumber_id' => 1,
            'deskripsi' => 'Paracetamol', 'jumlah' => 10, 'tarif_satuan' => 1500, 'subtotal' => 15000,
        ]);

        // Inilah alasan seluruh penyatuan ini dikerjakan: satu query mengembalikan
        // rincian biaya per jenis layanan, yang dibutuhkan modul klaim nanti.
        $ringkasan = $tagihan->detail()
            ->selectRaw('sumber_tipe, SUM(subtotal) AS total')
            ->groupBy('sumber_tipe')
            ->pluck('total', 'sumber_tipe');

        $this->assertSame(50000, (int) $ringkasan[TindakanKunjungan::class]);
        $this->assertSame(15000, (int) $ringkasan[ResepDetail::class]);
    }
}
