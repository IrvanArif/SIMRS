<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Dokter;
use App\Models\User;
use App\Support\MenuNavigasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MenuNavigasiTest extends TestCase
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

    /** @return list<string> nama rute yang muncul di menu pengguna ini */
    private function ruteMenu(User $user): array
    {
        $rute = [];

        foreach (MenuNavigasi::untuk($user) as $kelompok) {
            foreach ($kelompok['tautan'] as $tautan) {
                $rute[] = $tautan['rute'];
            }
        }

        return $rute;
    }

    public function test_setiap_peran_mendapat_menu_yang_tidak_kosong(): void
    {
        foreach (Peran::semua() as $peran) {
            $this->assertNotEmpty(
                $this->ruteMenu($this->penggunaBerperan($peran)),
                "Peran {$peran} tidak punya satu pun tautan menu."
            );
        }
    }

    public function test_menu_admisi_memuat_pendaftaran_dan_papan_bed(): void
    {
        $rute = $this->ruteMenu($this->penggunaBerperan(Peran::Admisi->value));

        $this->assertContains('pendaftaran.pasien', $rute);
        $this->assertContains('pendaftaran.antrian', $rute);
        $this->assertContains('rawat-inap.papan', $rute);
        $this->assertNotContains('kasir.tagihan', $rute);
    }

    public function test_menu_analis_hanya_memuat_laboratorium(): void
    {
        $rute = $this->ruteMenu($this->penggunaBerperan(Peran::Analis->value));

        $this->assertSame(['lab.antrean'], $rute);
    }

    public function test_menu_radiografer_hanya_memuat_radiologi(): void
    {
        $rute = $this->ruteMenu($this->penggunaBerperan(Peran::Radiografer->value));

        $this->assertSame(['radiologi.antrean'], $rute);
    }

    public function test_menu_dokter_memuat_poli_radiologi_dan_rawat_inap(): void
    {
        $dokter = $this->penggunaBerperan(Peran::Dokter->value);
        $dokter->update(['dokter_id' => Dokter::factory()->create()->id]);

        $rute = $this->ruteMenu($dokter->refresh());

        $this->assertContains('poli.antrian', $rute);
        $this->assertContains('radiologi.antrean', $rute);
        $this->assertContains('rawat-inap.papan', $rute);
    }

    /**
     * Menu yang menampilkan tautan yang berujung 403 lebih buruk daripada tidak
     * ada menu: pengguna diundang ke pintu yang terkunci.
     */
    public function test_setiap_tautan_menu_benar_benar_bisa_dibuka_pemiliknya(): void
    {
        foreach (Peran::semua() as $peran) {
            $user = $this->penggunaBerperan($peran);

            foreach ($this->ruteMenu($user) as $namaRute) {
                $this->actingAs($user)
                    ->get(route($namaRute))
                    ->assertOk("Peran {$peran} melihat tautan {$namaRute} tetapi tidak bisa membukanya.");
            }
        }
    }

    public function test_tautan_yang_tidak_muncul_di_menu_memang_terlarang(): void
    {
        // Kebalikannya juga harus benar: yang disembunyikan bukan sekadar
        // disembunyikan, melainkan memang ditolak.
        $analis = $this->penggunaBerperan(Peran::Analis->value);

        foreach (['kasir.tagihan', 'apotek.antrean', 'rawat-inap.papan', 'admin.user'] as $terlarang) {
            $this->assertNotContains($terlarang, $this->ruteMenu($analis));

            $this->actingAs($analis)->get(route($terlarang))->assertForbidden();
        }
    }

    public function test_dokter_tanpa_poli_tidak_diberi_tautan_poli(): void
    {
        // Dokter radiologi berperan dokter tetapi tidak memegang poli. Tautan
        // antrian poli akan membuka daftar pasien yang satu pun tidak bisa ia
        // periksa — undangan ke jalan buntu.
        $dokterRadiologi = $this->penggunaBerperan(Peran::Dokter->value);
        $rute = $this->ruteMenu($dokterRadiologi);

        $this->assertNotContains('poli.antrian', $rute);
        $this->assertNotContains('rawat-inap.papan', $rute);
        $this->assertContains('radiologi.antrean', $rute);
    }

    public function test_dokter_berpoli_tetap_mendapat_tautan_poli(): void
    {
        $dokter = $this->penggunaBerperan(Peran::Dokter->value);
        $dokter->update(['dokter_id' => Dokter::factory()->create()->id]);

        $rute = $this->ruteMenu($dokter->refresh());

        $this->assertContains('poli.antrian', $rute);
        $this->assertContains('rawat-inap.papan', $rute);
    }

    public function test_beranda_menampilkan_menu_pengguna(): void
    {
        $this->actingAs($this->penggunaBerperan(Peran::Kasir->value))
            ->get(route('beranda'))
            ->assertOk()
            ->assertSee('Daftar Tagihan')
            ->assertDontSee('Antrean Laboratorium');
    }

    public function test_bilah_navigasi_muncul_di_setiap_layar(): void
    {
        $this->actingAs($this->penggunaBerperan(Peran::Apoteker->value))
            ->get(route('apotek.antrean'))
            ->assertOk()
            ->assertSee('Antrean Resep')
            ->assertSee('Peringatan Stok');
    }

    public function test_pengguna_tanpa_peran_mendapat_menu_kosong_tanpa_galat(): void
    {
        $tanpaPeran = User::factory()->create();

        $this->assertSame([], $this->ruteMenu($tanpaPeran));

        $this->actingAs($tanpaPeran)->get(route('beranda'))->assertOk();
    }
}
