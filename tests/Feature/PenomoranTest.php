<?php

namespace Tests\Feature;

use App\Services\NomorDokumen;
use App\Services\NomorRekamMedis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PenomoranTest extends TestCase
{
    use RefreshDatabase;

    public function test_nomor_rekam_medis_pertama_adalah_enam_digit_berisi_satu(): void
    {
        $this->assertSame('000001', app(NomorRekamMedis::class)->berikutnya());
    }

    public function test_nomor_rekam_medis_berurutan_tanpa_pengulangan(): void
    {
        $layanan = app(NomorRekamMedis::class);

        $this->assertSame('000001', $layanan->berikutnya());
        $this->assertSame('000002', $layanan->berikutnya());
        $this->assertSame('000003', $layanan->berikutnya());
    }

    public function test_nomor_kunjungan_memuat_tanggal_dan_urutan_harian(): void
    {
        $layanan = app(NomorDokumen::class);
        $tanggal = Carbon::parse('2026-08-18');

        $this->assertSame('KJ-20260818-0001', $layanan->berikutnya('kunjungan', $tanggal));
        $this->assertSame('KJ-20260818-0002', $layanan->berikutnya('kunjungan', $tanggal));
    }

    public function test_urutan_dokumen_mulai_dari_satu_lagi_pada_hari_berikutnya(): void
    {
        $layanan = app(NomorDokumen::class);

        $layanan->berikutnya('tagihan', Carbon::parse('2026-08-18'));

        $this->assertSame('TG-20260819-0001', $layanan->berikutnya('tagihan', Carbon::parse('2026-08-19')));
    }

    public function test_setiap_jenis_dokumen_punya_awalan_sendiri(): void
    {
        $layanan = app(NomorDokumen::class);
        $tanggal = Carbon::parse('2026-08-18');

        $this->assertStringStartsWith('RS-', $layanan->berikutnya('resep', $tanggal));
        $this->assertStringStartsWith('KW-', $layanan->berikutnya('kuitansi', $tanggal));
    }

    public function test_jenis_dokumen_tak_dikenal_ditolak(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(NomorDokumen::class)->berikutnya('faktur');
    }
}
