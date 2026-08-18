<?php

namespace Tests\Unit;

use App\Enums\JenisMutasiStok;
use App\Enums\Peran;
use App\Enums\StatusResep;
use PHPUnit\Framework\TestCase;

class EnumFarmasiTest extends TestCase
{
    public function test_hanya_resep_berstatus_dibuat_yang_bisa_disiapkan(): void
    {
        $this->assertTrue(StatusResep::Dibuat->bisaDisiapkan());
        $this->assertFalse(StatusResep::Disiapkan->bisaDisiapkan());
        $this->assertFalse(StatusResep::Diserahkan->bisaDisiapkan());
        $this->assertFalse(StatusResep::Batal->bisaDisiapkan());
    }

    public function test_nilai_status_resep_sesuai_spec(): void
    {
        $this->assertSame('dibuat', StatusResep::Dibuat->value);
        $this->assertSame('disiapkan', StatusResep::Disiapkan->value);
        $this->assertSame('diserahkan', StatusResep::Diserahkan->value);
    }

    public function test_jenis_mutasi_stok_lengkap(): void
    {
        $this->assertSame(
            ['masuk', 'keluar', 'pengembalian', 'penyesuaian'],
            array_column(JenisMutasiStok::cases(), 'value')
        );
    }

    public function test_apoteker_termasuk_daftar_peran(): void
    {
        $this->assertContains('apoteker', Peran::semua());
        $this->assertCount(7, Peran::semua());
    }
}
