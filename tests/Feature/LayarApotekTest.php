<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Enums\StatusResep;
use App\Livewire\Apotek\AntreanResep;
use App\Livewire\Apotek\LayarPenyiapan;
use App\Livewire\Apotek\PenerimaanBatch;
use App\Models\BatchObat;
use App\Models\HargaObat;
use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\Penjamin;
use App\Models\Resep;
use App\Models\Tagihan;
use App\Models\User;
use App\Services\PenulisanResep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LayarApotekTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Peran::semua() as $peran) {
            Role::findOrCreate($peran);
        }
    }

    private function apoteker(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Peran::Apoteker->value);

        return $user;
    }

    private function resepMenunggu(): Resep
    {
        $umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $obat = Obat::factory()->create(['nama' => 'Paracetamol 500 mg']);

        HargaObat::factory()->create([
            'obat_id' => $obat->id, 'penjamin_id' => $umum->id,
            'harga' => 1500, 'berlaku_mulai' => '2026-01-01',
        ]);

        BatchObat::factory()->create([
            'obat_id' => $obat->id, 'tanggal_kedaluwarsa' => '2029-01-31',
            'jumlah_awal' => 100, 'jumlah_tersisa' => 100,
        ]);

        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $umum->id]);
        Tagihan::factory()->create(['kunjungan_id' => $kunjungan->id, 'penjamin_id' => $umum->id]);

        return app(PenulisanResep::class)->tulis($kunjungan, [[
            'obat_id' => $obat->id, 'jumlah' => 10, 'aturan_pakai' => '3x1',
        ]], User::factory()->create());
    }

    public function test_antrean_resep_menampilkan_resep_yang_belum_disiapkan(): void
    {
        $resep = $this->resepMenunggu();

        Livewire::actingAs($this->apoteker())
            ->test(AntreanResep::class)
            ->assertSee($resep->no_resep);
    }

    public function test_resep_yang_sudah_diserahkan_tidak_muncul_di_antrean(): void
    {
        $resep = $this->resepMenunggu();
        $resep->update(['status' => StatusResep::Diserahkan]);

        Livewire::actingAs($this->apoteker())
            ->test(AntreanResep::class)
            ->assertDontSee($resep->no_resep);
    }

    public function test_apoteker_menyiapkan_resep_lewat_layar(): void
    {
        $resep = $this->resepMenunggu();

        Livewire::actingAs($this->apoteker())
            ->test(LayarPenyiapan::class, ['resep' => $resep])
            ->call('siapkan')
            ->assertHasNoErrors();

        $this->assertSame(StatusResep::Disiapkan, $resep->refresh()->status);
    }

    public function test_stok_kurang_menampilkan_pesan_di_layar_bukan_error(): void
    {
        $resep = $this->resepMenunggu();
        BatchObat::query()->update(['jumlah_tersisa' => 2]);

        Livewire::actingAs($this->apoteker())
            ->test(LayarPenyiapan::class, ['resep' => $resep])
            ->call('siapkan')
            ->assertHasErrors('penyiapan');

        $this->assertSame(StatusResep::Dibuat, $resep->refresh()->status);
    }

    public function test_apoteker_menerima_batch_lewat_layar(): void
    {
        $obat = Obat::factory()->create();

        Livewire::actingAs($this->apoteker())
            ->test(PenerimaanBatch::class)
            ->set('obat_id', $obat->id)
            ->set('no_batch', 'B2026099')
            ->set('tanggal_kedaluwarsa', now()->addYear()->toDateString())
            ->set('jumlah', 250)
            ->set('harga_beli', 900)
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('batch_obat', ['no_batch' => 'B2026099', 'jumlah_tersisa' => 250]);
    }

    public function test_batch_kedaluwarsa_ditolak_dengan_pesan_di_layar(): void
    {
        $obat = Obat::factory()->create();

        Livewire::actingAs($this->apoteker())
            ->test(PenerimaanBatch::class)
            ->set('obat_id', $obat->id)
            ->set('no_batch', 'B2026098')
            ->set('tanggal_kedaluwarsa', now()->subDay()->toDateString())
            ->set('jumlah', 10)
            ->set('harga_beli', 900)
            ->call('simpan')
            ->assertHasErrors('tanggal_kedaluwarsa');
    }

    public function test_dokter_tidak_bisa_membuka_layar_apotek(): void
    {
        $dokter = User::factory()->create();
        $dokter->assignRole(Peran::Dokter->value);

        $this->actingAs($dokter)->get(route('apotek.antrean'))->assertForbidden();
    }

    public function test_apoteker_tidak_bisa_membuka_layar_kasir(): void
    {
        $this->actingAs($this->apoteker())->get(route('kasir.tagihan'))->assertForbidden();
    }
}
