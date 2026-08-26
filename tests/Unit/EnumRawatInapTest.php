<?php

namespace Tests\Unit;

use App\Enums\CaraPulang;
use App\Enums\JenisLayanan;
use App\Enums\StatusKunjungan;
use App\Enums\StatusRawatInap;
use PHPUnit\Framework\TestCase;

class EnumRawatInapTest extends TestCase
{
    public function test_status_dirawat_termasuk_aktif(): void
    {
        $this->assertTrue(StatusRawatInap::Dirawat->aktif());
    }

    public function test_status_pulang_dan_batal_tidak_aktif(): void
    {
        $this->assertFalse(StatusRawatInap::Pulang->aktif());
        $this->assertFalse(StatusRawatInap::Batal->aktif());
    }

    public function test_kunjungan_dalam_perawatan_termasuk_status_aktif(): void
    {
        // Kunjungan rawat inap tetap terbuka selama pasien belum pulang, sehingga
        // tindakan, resep, lab, dan radiologi masih boleh ditambahkan padanya.
        $this->assertTrue(StatusKunjungan::DalamPerawatan->aktif());
        $this->assertSame('dalam_perawatan', StatusKunjungan::DalamPerawatan->value);
    }

    public function test_kamar_termasuk_jenis_layanan_bertarif(): void
    {
        $this->assertSame('kamar', JenisLayanan::Kamar->value);
        $this->assertSame('Kamar Rawat Inap', JenisLayanan::Kamar->label());
    }

    public function test_cara_pulang_punya_label_yang_bisa_dibaca(): void
    {
        $this->assertSame('Sembuh', CaraPulang::Sembuh->label());
        $this->assertSame('Rujuk Keluar', CaraPulang::RujukKeluar->label());
        $this->assertSame('Pulang Paksa', CaraPulang::PulangPaksa->label());
        $this->assertSame('Meninggal', CaraPulang::Meninggal->label());

        foreach (CaraPulang::cases() as $cara) {
            $this->assertNotSame('', $cara->label());
        }
    }
}
