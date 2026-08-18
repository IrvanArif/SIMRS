<?php

namespace Tests\Unit;

use App\Enums\StatusKunjungan;
use App\Enums\StatusTagihan;
use PHPUnit\Framework\TestCase;

class EnumTest extends TestCase
{
    public function test_status_kunjungan_terdaftar_dan_diperiksa_terhitung_aktif(): void
    {
        $this->assertTrue(StatusKunjungan::Terdaftar->aktif());
        $this->assertTrue(StatusKunjungan::DiperiksaPerawat->aktif());
        $this->assertTrue(StatusKunjungan::DiperiksaDokter->aktif());
    }

    public function test_status_kunjungan_selesai_dan_batal_tidak_aktif(): void
    {
        $this->assertFalse(StatusKunjungan::Selesai->aktif());
        $this->assertFalse(StatusKunjungan::Batal->aktif());
    }

    public function test_nilai_status_tagihan_sesuai_spec(): void
    {
        $this->assertSame('belum_bayar', StatusTagihan::BelumBayar->value);
        $this->assertSame('ditanggung_penjamin', StatusTagihan::DitanggungPenjamin->value);
    }
}
