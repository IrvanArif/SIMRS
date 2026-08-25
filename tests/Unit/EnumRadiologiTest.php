<?php

namespace Tests\Unit;

use App\Enums\JenisLayanan;
use App\Enums\Peran;
use App\Enums\StatusOrderRadiologi;
use PHPUnit\Framework\TestCase;

class EnumRadiologiTest extends TestCase
{
    public function test_pencitraan_hanya_bisa_dikerjakan_saat_berstatus_dipesan(): void
    {
        $this->assertTrue(StatusOrderRadiologi::Dipesan->bisaDikerjakan());
        $this->assertFalse(StatusOrderRadiologi::Dikerjakan->bisaDikerjakan());
        $this->assertFalse(StatusOrderRadiologi::Selesai->bisaDikerjakan());
        $this->assertFalse(StatusOrderRadiologi::Batal->bisaDikerjakan());
    }

    public function test_ekspertise_hanya_bisa_ditulis_setelah_dikerjakan(): void
    {
        $this->assertFalse(StatusOrderRadiologi::Dipesan->bisaDiekspertise());
        $this->assertTrue(StatusOrderRadiologi::Dikerjakan->bisaDiekspertise());
        $this->assertFalse(StatusOrderRadiologi::Selesai->bisaDiekspertise());
        $this->assertFalse(StatusOrderRadiologi::Batal->bisaDiekspertise());
    }

    public function test_order_dianggap_selesai_saat_selesai_atau_batal(): void
    {
        $this->assertTrue(StatusOrderRadiologi::Selesai->selesai());
        $this->assertTrue(StatusOrderRadiologi::Batal->selesai());
        $this->assertFalse(StatusOrderRadiologi::Dipesan->selesai());
        $this->assertFalse(StatusOrderRadiologi::Dikerjakan->selesai());
    }

    public function test_radiografer_termasuk_daftar_peran(): void
    {
        $this->assertContains('radiografer', Peran::semua());

        foreach (['admisi', 'perawat', 'dokter', 'apoteker', 'analis', 'kasir', 'rekam_medis', 'admin'] as $peran) {
            $this->assertContains($peran, Peran::semua());
        }
    }

    public function test_radiologi_termasuk_jenis_layanan_bertarif(): void
    {
        $this->assertSame('radiologi', JenisLayanan::Radiologi->value);
        $this->assertSame('Pemeriksaan Radiologi', JenisLayanan::Radiologi->label());
    }
}
