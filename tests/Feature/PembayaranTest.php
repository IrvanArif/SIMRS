<?php

namespace Tests\Feature;

use App\Enums\MetodePembayaran;
use App\Enums\Peran;
use App\Enums\StatusTagihan;
use App\Models\Tagihan;
use App\Models\User;
use App\Services\ProsesPembayaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PembayaranTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Peran::semua() as $peran) {
            Role::findOrCreate($peran);
        }
    }

    private function tagihanUmum(int $nominal = 100000): Tagihan
    {
        return Tagihan::factory()->create([
            'total' => $nominal,
            'ditagihkan_ke_pasien' => $nominal,
            'ditanggung_penjamin' => 0,
            'status' => StatusTagihan::BelumBayar,
        ]);
    }

    private function kasir(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Peran::Kasir->value);

        return $user;
    }

    public function test_pembayaran_tunai_pas_melunasi_tagihan_tanpa_kembalian(): void
    {
        $tagihan = $this->tagihanUmum();

        $pembayaran = app(ProsesPembayaran::class)
            ->bayar($tagihan, MetodePembayaran::Tunai, 100000, $this->kasir());

        $this->assertSame(0, (int) $pembayaran->kembalian);
        $this->assertStringStartsWith('KW-', $pembayaran->no_kuitansi);
        $this->assertSame(StatusTagihan::Lunas, $tagihan->refresh()->status);
    }

    public function test_pembayaran_tunai_berlebih_menghasilkan_kembalian(): void
    {
        $pembayaran = app(ProsesPembayaran::class)
            ->bayar($this->tagihanUmum(), MetodePembayaran::Tunai, 150000, $this->kasir());

        $this->assertSame(50000, (int) $pembayaran->kembalian);
    }

    public function test_pembayaran_tunai_kurang_ditolak(): void
    {
        $this->expectException(RuntimeException::class);

        app(ProsesPembayaran::class)
            ->bayar($this->tagihanUmum(), MetodePembayaran::Tunai, 90000, $this->kasir());
    }

    public function test_pembayaran_debit_harus_persis_sejumlah_tagihan(): void
    {
        $this->expectException(RuntimeException::class);

        app(ProsesPembayaran::class)
            ->bayar($this->tagihanUmum(), MetodePembayaran::Debit, 150000, $this->kasir());
    }

    public function test_tagihan_yang_sudah_lunas_tidak_bisa_dibayar_ulang(): void
    {
        $tagihan = $this->tagihanUmum();
        $kasir = $this->kasir();

        app(ProsesPembayaran::class)->bayar($tagihan, MetodePembayaran::Tunai, 100000, $kasir);

        $this->expectException(RuntimeException::class);

        app(ProsesPembayaran::class)->bayar($tagihan->refresh(), MetodePembayaran::Tunai, 100000, $kasir);
    }

    public function test_tagihan_yang_ditanggung_penjamin_tidak_diproses_di_kasir(): void
    {
        $tagihan = Tagihan::factory()->create([
            'total' => 65000, 'ditanggung_penjamin' => 65000,
            'ditagihkan_ke_pasien' => 0, 'status' => StatusTagihan::DitanggungPenjamin,
        ]);

        $this->expectException(RuntimeException::class);

        app(ProsesPembayaran::class)->bayar($tagihan, MetodePembayaran::Tunai, 0, $this->kasir());
    }

    public function test_hanya_kasir_yang_boleh_memproses_pembayaran(): void
    {
        $tagihan = $this->tagihanUmum();
        $perawat = User::factory()->create();
        $perawat->assignRole(Peran::Perawat->value);

        $this->assertTrue(Gate::forUser($this->kasir())->allows('proses', $tagihan));
        $this->assertFalse(Gate::forUser($perawat)->allows('proses', $tagihan));
    }
}
