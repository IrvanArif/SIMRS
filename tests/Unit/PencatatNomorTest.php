<?php

namespace Tests\Unit;

use App\Services\PencatatNomor;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PencatatNomorTest extends TestCase
{
    use RefreshDatabase;

    private function pencatat(): PencatatNomor
    {
        return app(PencatatNomor::class);
    }

    public function test_pengambilan_pertama_menghasilkan_angka_1(): void
    {
        $this->assertSame(1, $this->pencatat()->ambil('rm'));
    }

    public function test_pengambilan_berikutnya_bertambah_satu(): void
    {
        $this->pencatat()->ambil('rm');

        $this->assertSame(2, $this->pencatat()->ambil('rm'));
    }

    public function test_sepuluh_pengambilan_menghasilkan_sepuluh_angka_berbeda(): void
    {
        $hasil = [];

        for ($i = 0; $i < 10; $i++) {
            $hasil[] = $this->pencatat()->ambil('antrian:1', '2026-08-18');
        }

        $this->assertCount(10, array_unique($hasil));
        $this->assertSame(range(1, 10), $hasil);
    }

    public function test_periode_berbeda_punya_penghitung_sendiri(): void
    {
        $this->pencatat()->ambil('antrian:1', '2026-08-18');
        $this->pencatat()->ambil('antrian:1', '2026-08-18');

        $this->assertSame(1, $this->pencatat()->ambil('antrian:1', '2026-08-19'));
    }

    public function test_kunci_berbeda_punya_penghitung_sendiri(): void
    {
        $this->pencatat()->ambil('antrian:1', '2026-08-18');

        $this->assertSame(1, $this->pencatat()->ambil('antrian:2', '2026-08-18'));
    }

    public function test_database_menolak_dua_penghitung_dengan_kunci_dan_periode_sama(): void
    {
        $this->pencatat()->ambil('rm');

        $this->expectException(QueryException::class);

        DB::table('nomor_counter')->insert([
            'kunci' => 'rm', 'periode' => 'global', 'nilai' => 99,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
