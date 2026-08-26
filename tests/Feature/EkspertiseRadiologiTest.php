<?php

namespace Tests\Feature;

use App\Enums\JenisLayanan;
use App\Enums\StatusOrderRadiologi;
use App\Models\EkspertiseRadiologi;
use App\Models\Kunjungan;
use App\Models\OrderRadiologi;
use App\Models\PemeriksaanRadiologi;
use App\Models\Penjamin;
use App\Models\Tarif;
use App\Models\User;
use App\Services\PelaksanaanRadiologi;
use App\Services\PemesananRadiologi;
use App\Services\PenulisanEkspertise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class EkspertiseRadiologiTest extends TestCase
{
    use RefreshDatabase;

    private Penjamin $umum;

    private PemeriksaanRadiologi $toraks;

    protected function setUp(): void
    {
        parent::setUp();

        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $this->toraks = PemeriksaanRadiologi::factory()->create([
            'nama' => 'Rontgen Toraks PA', 'modalitas' => 'rontgen',
        ]);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Radiologi, 'layanan_id' => $this->toraks->id,
            'penjamin_id' => $this->umum->id, 'harga' => 150000, 'berlaku_mulai' => '2026-01-01',
        ]);
    }

    private function order(): OrderRadiologi
    {
        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]);

        return app(PemesananRadiologi::class)
            ->pesan($kunjungan, [$this->toraks->id], User::factory()->create(), 'Batuk kronis');
    }

    private function bacaan(OrderRadiologi $order, array $ganti = []): array
    {
        return [
            $order->detail->first()->id => array_merge([
                'temuan' => 'Corakan bronkovaskular meningkat, tidak tampak infiltrat.',
                'kesan' => 'Bronkitis kronis.',
                'saran' => null,
            ], $ganti),
        ];
    }

    public function test_pencitraan_dikerjakan_mencatat_nomor_film_waktu_dan_pelakunya(): void
    {
        $order = $this->order();
        $radiografer = User::factory()->create();

        $hasil = app(PelaksanaanRadiologi::class)->kerjakan($order, 'FILM-2026-0001', $radiografer);

        $this->assertSame(StatusOrderRadiologi::Dikerjakan, $hasil->status);
        $this->assertSame('FILM-2026-0001', $hasil->no_film);
        $this->assertNotNull($hasil->waktu_dikerjakan);
        $this->assertSame($radiografer->id, $hasil->dikerjakan_oleh);
    }

    public function test_pencitraan_wajib_menyertakan_nomor_film(): void
    {
        $this->expectException(ValidationException::class);

        app(PelaksanaanRadiologi::class)->kerjakan($this->order(), '   ', User::factory()->create());
    }

    public function test_pencitraan_tidak_bisa_dikerjakan_dua_kali(): void
    {
        $order = $this->order();
        $layanan = app(PelaksanaanRadiologi::class);

        $layanan->kerjakan($order, 'FILM-1', User::factory()->create());

        $this->expectException(RuntimeException::class);

        $layanan->kerjakan($order->refresh(), 'FILM-2', User::factory()->create());
    }

    public function test_ekspertise_tidak_bisa_ditulis_sebelum_pencitraan_dikerjakan(): void
    {
        $order = $this->order();

        $this->expectException(RuntimeException::class);

        app(PenulisanEkspertise::class)->tulis($order, $this->bacaan($order), User::factory()->create());
    }

    public function test_ekspertise_tersimpan_dan_order_menjadi_selesai(): void
    {
        $order = $this->order();
        $dokter = User::factory()->create();

        app(PelaksanaanRadiologi::class)->kerjakan($order, 'FILM-1', User::factory()->create());
        $hasil = app(PenulisanEkspertise::class)->tulis($order->refresh(), $this->bacaan($order), $dokter);

        $this->assertSame(StatusOrderRadiologi::Selesai, $hasil->status);
        $this->assertSame($dokter->id, $hasil->ditulis_oleh);
        $this->assertNotNull($hasil->waktu_ekspertise);

        $ekspertise = EkspertiseRadiologi::first();

        $this->assertStringContainsString('bronkovaskular', $ekspertise->temuan);
        $this->assertSame('Bronkitis kronis.', $ekspertise->kesan);
    }

    public function test_ekspertise_wajib_memuat_temuan(): void
    {
        $order = $this->order();
        app(PelaksanaanRadiologi::class)->kerjakan($order, 'FILM-1', User::factory()->create());

        $this->expectException(ValidationException::class);

        app(PenulisanEkspertise::class)
            ->tulis($order->refresh(), $this->bacaan($order, ['temuan' => '']), User::factory()->create());
    }

    public function test_ekspertise_wajib_memuat_kesan(): void
    {
        $order = $this->order();
        app(PelaksanaanRadiologi::class)->kerjakan($order, 'FILM-1', User::factory()->create());

        $this->expectException(ValidationException::class);

        app(PenulisanEkspertise::class)
            ->tulis($order->refresh(), $this->bacaan($order, ['kesan' => '']), User::factory()->create());
    }

    public function test_saran_boleh_dikosongkan(): void
    {
        $order = $this->order();
        app(PelaksanaanRadiologi::class)->kerjakan($order, 'FILM-1', User::factory()->create());

        $hasil = app(PenulisanEkspertise::class)
            ->tulis($order->refresh(), $this->bacaan($order, ['saran' => null]), User::factory()->create());

        $this->assertSame(StatusOrderRadiologi::Selesai, $hasil->status);
        $this->assertNull(EkspertiseRadiologi::first()->saran);
    }

    public function test_hasil_belum_terbaca_dokter_sebelum_ekspertise_ditulis(): void
    {
        $order = $this->order();
        app(PelaksanaanRadiologi::class)->kerjakan($order, 'FILM-1', User::factory()->create());

        $this->assertFalse($order->refresh()->terbacaDokter());

        app(PenulisanEkspertise::class)
            ->tulis($order->refresh(), $this->bacaan($order), User::factory()->create());

        $this->assertTrue($order->refresh()->terbacaDokter());
    }

    public function test_koreksi_ekspertise_wajib_beralasan(): void
    {
        $order = $this->order();
        $dokter = User::factory()->create();

        app(PelaksanaanRadiologi::class)->kerjakan($order, 'FILM-1', User::factory()->create());
        app(PenulisanEkspertise::class)->tulis($order->refresh(), $this->bacaan($order), $dokter);

        $this->expectException(ValidationException::class);

        app(PenulisanEkspertise::class)->koreksi(
            $order->refresh(), $this->bacaan($order, ['kesan' => 'Normal.']), $dokter, '   '
        );
    }

    public function test_koreksi_mengubah_bacaan_dan_tercatat_di_audit_log(): void
    {
        $order = $this->order();
        $dokter = User::factory()->create();

        app(PelaksanaanRadiologi::class)->kerjakan($order, 'FILM-1', User::factory()->create());
        app(PenulisanEkspertise::class)->tulis($order->refresh(), $this->bacaan($order), $dokter);

        app(PenulisanEkspertise::class)->koreksi(
            $order->refresh(),
            $this->bacaan($order, ['kesan' => 'Tidak tampak kelainan.']),
            $dokter,
            'Salah membaca sisi'
        );

        $this->assertSame('Tidak tampak kelainan.', EkspertiseRadiologi::first()->kesan);
        $this->assertDatabaseHas('audit_logs', ['alasan' => 'Salah membaca sisi']);
    }

    public function test_objek_order_usang_tidak_bisa_menimpa_ekspertise_yang_sudah_ditulis(): void
    {
        $order = $this->order();
        $dokter = User::factory()->create();

        app(PelaksanaanRadiologi::class)->kerjakan($order, 'FILM-1', User::factory()->create());

        // Dokter pertama membuka layar; objek ini akan tetap berstatus dikerjakan
        // di memori meski dokter lain sudah menulis ekspertisenya lebih dulu.
        $usang = OrderRadiologi::find($order->id);

        app(PenulisanEkspertise::class)
            ->tulis($order->refresh(), $this->bacaan($order), $dokter);

        // Tanpa penguncian, tulis() akan lolos dan menimpa bacaan dokter pertama
        // tanpa alasan dan tanpa jejak audit — pintu belakang aturan 56.
        try {
            app(PenulisanEkspertise::class)->tulis(
                $usang, $this->bacaan($order, ['kesan' => 'Normal.']), User::factory()->create()
            );
            $this->fail('Ekspertise yang sudah ditulis seharusnya tidak bisa ditimpa lewat tulis().');
        } catch (RuntimeException $e) {
            // Inilah yang diharapkan.
        }

        $this->assertSame('Bronkitis kronis.', EkspertiseRadiologi::first()->kesan);
        $this->assertSame($dokter->id, $order->refresh()->ditulis_oleh);
    }

    public function test_koreksi_menolak_order_yang_dibatalkan_di_tengah_jalan(): void
    {
        $order = $this->order();
        $dokter = User::factory()->create();

        app(PelaksanaanRadiologi::class)->kerjakan($order, 'FILM-1', User::factory()->create());
        app(PenulisanEkspertise::class)->tulis($order->refresh(), $this->bacaan($order), $dokter);

        $usang = OrderRadiologi::find($order->id);
        $order->refresh()->update(['status' => StatusOrderRadiologi::Batal]);

        $this->expectException(RuntimeException::class);

        app(PenulisanEkspertise::class)->koreksi(
            $usang, $this->bacaan($order, ['kesan' => 'Normal.']), $dokter, 'Perbaikan'
        );
    }

    public function test_koreksi_hanya_berlaku_untuk_ekspertise_yang_sudah_ditulis(): void
    {
        $order = $this->order();
        app(PelaksanaanRadiologi::class)->kerjakan($order, 'FILM-1', User::factory()->create());

        $this->expectException(RuntimeException::class);

        app(PenulisanEkspertise::class)->koreksi(
            $order->refresh(), $this->bacaan($order), User::factory()->create(), 'Perbaikan'
        );
    }
}
