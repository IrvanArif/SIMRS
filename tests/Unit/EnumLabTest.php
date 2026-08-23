<?php

namespace Tests\Unit;

use App\Enums\PenandaHasil;
use App\Enums\Peran;
use App\Enums\StatusOrderLab;
use PHPUnit\Framework\TestCase;

class EnumLabTest extends TestCase
{
    public function test_hasil_hanya_bisa_dientri_setelah_sampel_diambil(): void
    {
        $this->assertFalse(StatusOrderLab::Dipesan->bisaEntriHasil());
        $this->assertTrue(StatusOrderLab::SampelDiambil->bisaEntriHasil());
        $this->assertTrue(StatusOrderLab::HasilDientri->bisaEntriHasil());
        $this->assertFalse(StatusOrderLab::Divalidasi->bisaEntriHasil());
        $this->assertFalse(StatusOrderLab::Batal->bisaEntriHasil());
    }

    public function test_order_dianggap_selesai_saat_divalidasi_atau_batal(): void
    {
        $this->assertTrue(StatusOrderLab::Divalidasi->selesai());
        $this->assertTrue(StatusOrderLab::Batal->selesai());
        $this->assertFalse(StatusOrderLab::Dipesan->selesai());
        $this->assertFalse(StatusOrderLab::SampelDiambil->selesai());
        $this->assertFalse(StatusOrderLab::HasilDientri->selesai());
    }

    public function test_penanda_hasil_lengkap(): void
    {
        $this->assertSame(
            ['rendah', 'normal', 'tinggi'],
            array_column(PenandaHasil::cases(), 'value')
        );
    }

    public function test_hanya_normal_yang_tidak_abnormal(): void
    {
        $this->assertFalse(PenandaHasil::Normal->abnormal());
        $this->assertTrue(PenandaHasil::Rendah->abnormal());
        $this->assertTrue(PenandaHasil::Tinggi->abnormal());
    }

    public function test_analis_termasuk_daftar_peran(): void
    {
        $this->assertContains('analis', Peran::semua());
        $this->assertCount(8, Peran::semua());
    }
}
