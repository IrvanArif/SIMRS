<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Enums\StatusTagihan;
use App\Livewire\Kasir\DaftarTagihan;
use App\Livewire\Kasir\ProsesPembayaran as ProsesPembayaranLayar;
use App\Models\Tagihan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LayarKasirTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Peran::semua() as $peran) {
            Role::findOrCreate($peran);
        }
    }

    private function penggunaBerperan(string $peran): User
    {
        $user = User::factory()->create();
        $user->assignRole($peran);

        return $user;
    }

    private function kasir(): User
    {
        return $this->penggunaBerperan(Peran::Kasir->value);
    }

    private function tagihanBelumLunas(int $nominal = 100000): Tagihan
    {
        return Tagihan::factory()->create([
            'total' => $nominal,
            'ditagihkan_ke_pasien' => $nominal,
            'status' => StatusTagihan::BelumBayar,
        ]);
    }

    public function test_kasir_melihat_daftar_tagihan_belum_lunas(): void
    {
        $tagihan = $this->tagihanBelumLunas();

        Livewire::actingAs($this->kasir())
            ->test(DaftarTagihan::class)
            ->assertSee($tagihan->no_tagihan);
    }

    public function test_tagihan_lunas_tidak_muncul_di_daftar_belum_lunas(): void
    {
        $lunas = Tagihan::factory()->create(['status' => StatusTagihan::Lunas]);

        Livewire::actingAs($this->kasir())
            ->test(DaftarTagihan::class)
            ->assertDontSee($lunas->no_tagihan);
    }

    public function test_kasir_memproses_pembayaran_tunai_dan_kembalian_terhitung(): void
    {
        $tagihan = $this->tagihanBelumLunas();

        Livewire::actingAs($this->kasir())
            ->test(ProsesPembayaranLayar::class, ['tagihan' => $tagihan])
            ->set('metode', 'tunai')
            ->set('nominal', 150000)
            ->call('bayar')
            ->assertHasNoErrors();

        $this->assertSame(StatusTagihan::Lunas, $tagihan->refresh()->status);
        $this->assertSame(50000, (int) $tagihan->pembayaran()->first()->kembalian);
    }

    public function test_nominal_kurang_menampilkan_pesan_kesalahan(): void
    {
        $tagihan = $this->tagihanBelumLunas();

        Livewire::actingAs($this->kasir())
            ->test(ProsesPembayaranLayar::class, ['tagihan' => $tagihan])
            ->set('metode', 'tunai')->set('nominal', 90000)
            ->call('bayar')
            ->assertHasErrors('nominal');

        $this->assertSame(StatusTagihan::BelumBayar, $tagihan->refresh()->status);
    }

    public function test_perawat_tidak_bisa_membuka_layar_kasir(): void
    {
        $this->actingAs($this->penggunaBerperan(Peran::Perawat->value))
            ->get(route('kasir.tagihan'))
            ->assertForbidden();
    }
}
