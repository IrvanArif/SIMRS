# SIMRS Fase 2 (Farmasi) — Rencana Implementasi

> **Untuk pekerja agentik:** SUB-SKILL WAJIB: gunakan superpowers:subagent-driven-development (disarankan) atau superpowers:executing-plans untuk mengerjakan rencana ini tugas demi tugas. Setiap langkah memakai checkbox (`- [ ]`).

**Goal:** Melengkapi alur rawat jalan dengan apotek — stok obat berbasis batch, penyiapan resep dengan alokasi FEFO, penyerahan obat, dan pembebanan biaya obat ke tagihan kunjungan yang sama.

**Architecture:** Mengikuti pola Fase 1 tanpa kecuali. Aturan bisnis tinggal di kelas Service di `app/Services`; komponen Livewire hanya memindahkan pesan ke layar. Tagihan tidak disusun ulang — apotek menambahkan baris obat ke tagihan yang sudah ada, dijaga sepasang aturan: kasir terkunci sampai resep disiapkan (aturan 29), dan obat pasien tunai tertahan sampai lunas (aturan 30).

**Tech Stack:** PHP 8.5, Laravel 13, Livewire 4, Blade, Tailwind (Vite), MySQL, spatie/laravel-permission, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-08-18-simrs-fase2-farmasi-design.md`

## Global Constraints

Berlaku untuk semua tugas. Sebagian besar diwarisi dari Fase 1 dan **tidak boleh dilanggar hanya karena ini modul baru**.

- **Bahasa Indonesia** untuk nama tabel, kolom, rute, label UI, pesan validasi, dan nama test (`test_...`).
- **TDD tanpa pengecualian:** test ditulis lebih dulu, dijalankan sampai terbukti GAGAL, baru implementasinya.
- **Nominal uang** berupa bilangan bulat rupiah (`unsignedBigInteger`).
- **Penomoran** lewat `App\Services\PencatatNomor`; `max() + 1` dilarang.
- **Enum PHP** untuk semua kolom berstatus. Tidak ada string mentah di komponen maupun service.
- **Model klinis baru wajib didaftarkan** di `AppServiceProvider::modelTerauditkan()`.
- **Database:** `simrs` (aplikasi), `simrs_test` (pengujian).
- **Commit setiap selesai satu tugas**, pesan berbahasa Indonesia.
- **Aturan bisnis** yang dirujuk mengacu ke bagian 8 spec Fase 2 (nomor 21–34) atau spec Fase 1 (nomor 1–20).
- **Tidak boleh ada nama rumah sakit nyata** di berkas mana pun. Data contoh memakai "RS Sampel".
- **Jangan mengubah perilaku Fase 1** selain dua titik yang memang dirancang berubah: `PenyusunTagihan` (tambah method) dan `ProsesPembayaran` (tambah penjaga).

## Pola yang Sudah Ada — Baca Sebelum Menulis

Berkas berikut adalah acuan gaya. Tugas di bawah menyebut "ikuti pola X" merujuk ke sini:

| Kebutuhan | Contoh yang sudah ada |
|---|---|
| Service dengan validasi + transaksi | `app/Services/PendaftaranKunjungan.php` |
| Pencarian tarif dengan jatuh tempo + log | `app/Services/PencariTarif.php` |
| Model + cast enum + relasi | `app/Models/Kunjungan.php` |
| Factory | `database/factories/KunjunganFactory.php` |
| Policy | `app/Policies/KunjunganPolicy.php` |
| Komponen Livewire form + pemetaan error | `app/Livewire/Pendaftaran/FormPasien.php` |
| Komponen Livewire dengan aksi majemuk | `app/Livewire/Poli/FormSoap.php` |
| Layar master CRUD | `app/Livewire/Master/DaftarPoli.php` |
| Test service | `tests/Feature/TarifTest.php` |
| Test layar + hak akses | `tests/Feature/LayarPoliTest.php` |

## Struktur Berkas

**Service (inti yang paling banyak diuji)**

| Berkas | Tanggung jawab |
|---|---|
| `app/Services/PencariHargaObat.php` | Harga jual menurut penjamin, jatuh tempo ke UMUM |
| `app/Services/PenerimaanObat.php` | Batch masuk dan mutasi masuk |
| `app/Services/PenyiapanResep.php` | Alokasi FEFO, potong stok, salin harga, bebankan ke tagihan |
| `app/Services/PenyerahanObat.php` | Penyerahan ke pasien beserta penjaga pelunasan |
| `app/Services/PenyesuaianStok.php` | Koreksi opname beralasan |

**Model:** `HargaObat`, `BatchObat`, `PengambilanBatch`, `MutasiStok` di `app/Models`.

**Enum:** `app/Enums/StatusResep.php`, `app/Enums/JenisMutasiStok.php`; `Peran` bertambah `Apoteker`.

**Policy:** `app/Policies/ResepPolicy.php`.

**Layar:** `app/Livewire/Apotek/{AntreanResep,PenyiapanResep,PenyerahanObat,PenerimaanBatch,KartuStok,PeringatanStok}.php`, plus `app/Livewire/Master/DaftarHargaObat.php`.

---

### Task 1: Enum baru dan peran apoteker

**Files:**
- Create: `app/Enums/StatusResep.php`, `app/Enums/JenisMutasiStok.php`
- Modify: `app/Enums/Peran.php`, `app/Models/Resep.php`
- Test: `tests/Unit/EnumFarmasiTest.php`

**Interfaces:**
- Consumes: —
- Produces: `StatusResep` dengan case `Dibuat`, `Disiapkan`, `Diserahkan`, `Batal` dan method `bisaDisiapkan(): bool` (true hanya untuk `Dibuat`). `JenisMutasiStok` dengan case `Masuk`, `Keluar`, `Pengembalian`, `Penyesuaian`. `Peran::Apoteker` bernilai `'apoteker'`. Model `Resep` meng-cast `status` ke `StatusResep`.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Unit/EnumFarmasiTest.php`:

```php
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
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=EnumFarmasiTest`
Diharapkan: FAIL dengan "Class App\Enums\StatusResep not found".

- [ ] **Step 3: Tulis enum**

`app/Enums/StatusResep.php`:

```php
<?php

namespace App\Enums;

enum StatusResep: string
{
    case Dibuat = 'dibuat';
    case Disiapkan = 'disiapkan';
    case Diserahkan = 'diserahkan';
    case Batal = 'batal';

    public function bisaDisiapkan(): bool
    {
        return $this === self::Dibuat;
    }

    public function label(): string
    {
        return match ($this) {
            self::Dibuat => 'Menunggu Disiapkan',
            self::Disiapkan => 'Sudah Disiapkan',
            self::Diserahkan => 'Sudah Diserahkan',
            self::Batal => 'Batal',
        };
    }
}
```

`app/Enums/JenisMutasiStok.php`:

```php
<?php

namespace App\Enums;

enum JenisMutasiStok: string
{
    case Masuk = 'masuk';
    case Keluar = 'keluar';
    case Pengembalian = 'pengembalian';
    case Penyesuaian = 'penyesuaian';
}
```

- [ ] **Step 4: Tambahkan peran apoteker**

Di `app/Enums/Peran.php`, sisipkan case baru sebelum `Admin`:

```php
    case Apoteker = 'apoteker';
```

- [ ] **Step 5: Cast status pada model Resep**

Di `app/Models/Resep.php`, tambahkan import `use App\Enums\StatusResep;` dan method:

```php
    protected function casts(): array
    {
        return [
            'status' => StatusResep::class,
            'disiapkan_pada' => 'datetime',
            'diserahkan_pada' => 'datetime',
        ];
    }
```

Kolom `disiapkan_pada` dan `diserahkan_pada` baru ditambahkan di Task 4; cast ini aman ditulis sekarang karena cast pada kolom yang belum ada tidak dievaluasi sampai kolomnya dibaca.

- [ ] **Step 6: Jalankan test sampai lulus**

Run: `php artisan test --filter=EnumFarmasiTest`
Diharapkan: PASS, 4 test.

- [ ] **Step 7: Jalankan seluruh test**

Run: `php artisan test`
Diharapkan: seluruh test Fase 1 tetap lulus. `PenulisanResep` menulis `status` berupa string `'dibuat'`; dengan cast enum, Laravel tetap menerimanya karena nilainya cocok dengan case yang ada.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: tambah enum status resep, jenis mutasi stok, dan peran apoteker"
```

---

### Task 2: Harga obat

**Files:**
- Create: migration `create_harga_obat_table`, `app/Models/HargaObat.php`, `database/factories/HargaObatFactory.php`, `app/Services/PencariHargaObat.php`
- Modify: `app/Models/Obat.php`
- Test: `tests/Feature/HargaObatTest.php`

**Interfaces:**
- Consumes: `Obat`, `Penjamin` (Fase 1)
- Produces: `PencariHargaObat::untuk(int $obatId, int $penjaminId, ?CarbonInterface $tanggal = null): int` — rupiah bulat; jatuh tempo ke penjamin `UMUM` sambil mencatat peringatan; melempar `RuntimeException` bila tarif UMUM pun tidak ada. Model `HargaObat` dengan relasi `obat()` dan `penjamin()`. `Obat::harga()` mengembalikan `HasMany`.

Memenuhi aturan 27.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/HargaObatTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\HargaObat;
use App\Models\Obat;
use App\Models\Penjamin;
use App\Services\PencariHargaObat;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class HargaObatTest extends TestCase
{
    use RefreshDatabase;

    private Obat $obat;
    private Penjamin $umum;
    private Penjamin $bpjs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->obat = Obat::factory()->create(['nama' => 'Paracetamol 500 mg']);
        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $this->bpjs = Penjamin::factory()->create(['kode' => 'BPJS', 'jenis' => 'penjamin']);
    }

    private function harga(Penjamin $penjamin, int $nilai, string $berlakuMulai = '2026-01-01'): void
    {
        HargaObat::factory()->create([
            'obat_id' => $this->obat->id,
            'penjamin_id' => $penjamin->id,
            'harga' => $nilai,
            'berlaku_mulai' => $berlakuMulai,
        ]);
    }

    public function test_harga_diambil_sesuai_penjamin(): void
    {
        $this->harga($this->umum, 1500);
        $this->harga($this->bpjs, 1000);

        $this->assertSame(1000, app(PencariHargaObat::class)->untuk($this->obat->id, $this->bpjs->id));
    }

    public function test_harga_jatuh_tempo_ke_umum_bila_penjamin_belum_punya_harga(): void
    {
        $this->harga($this->umum, 1500);

        $this->assertSame(1500, app(PencariHargaObat::class)->untuk($this->obat->id, $this->bpjs->id));
    }

    public function test_ketiadaan_harga_penjamin_dicatat_sebagai_peringatan(): void
    {
        $this->harga($this->umum, 1500);
        Log::spy();

        app(PencariHargaObat::class)->untuk($this->obat->id, $this->bpjs->id);

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_harga_terbaru_yang_sudah_berlaku_yang_dipakai(): void
    {
        $this->harga($this->umum, 1500, '2026-01-01');
        $this->harga($this->umum, 1800, '2026-06-01');

        $pencari = app(PencariHargaObat::class);

        $this->assertSame(1500, $pencari->untuk($this->obat->id, $this->umum->id, Carbon::parse('2026-03-01')));
        $this->assertSame(1800, $pencari->untuk($this->obat->id, $this->umum->id, Carbon::parse('2026-08-18')));
    }

    public function test_tanpa_harga_sama_sekali_maka_gagal_dengan_pesan_jelas(): void
    {
        $this->expectException(RuntimeException::class);

        app(PencariHargaObat::class)->untuk($this->obat->id, $this->bpjs->id);
    }

    public function test_harga_ganda_untuk_penjamin_dan_tanggal_berlaku_sama_ditolak_database(): void
    {
        $this->harga($this->umum, 1500, '2026-01-01');

        $this->expectException(QueryException::class);

        $this->harga($this->umum, 1600, '2026-01-01');
    }
}
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=HargaObatTest`
Diharapkan: FAIL dengan "Class App\Models\HargaObat not found".

- [ ] **Step 3: Tulis migration**

```bash
php artisan make:migration create_harga_obat_table
```

```php
Schema::create('harga_obat', function (Blueprint $table) {
    $table->id();
    $table->foreignId('obat_id')->constrained('obat')->cascadeOnDelete();
    $table->foreignId('penjamin_id')->constrained('penjamin');
    $table->unsignedBigInteger('harga');
    $table->date('berlaku_mulai');
    $table->timestamps();
    $table->unique(['obat_id', 'penjamin_id', 'berlaku_mulai']);
});
```

- [ ] **Step 4: Tulis model dan factory**

`app/Models/HargaObat.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HargaObat extends Model
{
    use HasFactory;

    protected $table = 'harga_obat';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['berlaku_mulai' => 'date'];
    }

    public function obat(): BelongsTo
    {
        return $this->belongsTo(Obat::class);
    }

    public function penjamin(): BelongsTo
    {
        return $this->belongsTo(Penjamin::class);
    }
}
```

`database/factories/HargaObatFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\HargaObat;
use App\Models\Obat;
use App\Models\Penjamin;
use Illuminate\Database\Eloquent\Factories\Factory;

class HargaObatFactory extends Factory
{
    protected $model = HargaObat::class;

    public function definition(): array
    {
        return [
            'obat_id' => Obat::factory(),
            'penjamin_id' => Penjamin::factory(),
            'harga' => $this->faker->numberBetween(500, 50000),
            'berlaku_mulai' => '2026-01-01',
        ];
    }
}
```

Di `app/Models/Obat.php` tambahkan relasi (import `Illuminate\Database\Eloquent\Relations\HasMany`):

```php
    public function harga(): HasMany
    {
        return $this->hasMany(HargaObat::class);
    }
```

- [ ] **Step 5: Tulis PencariHargaObat**

`app/Services/PencariHargaObat.php` — kembar dari `PencariTarif`, dengan tabel dan kolom yang berbeda:

```php
<?php

namespace App\Services;

use App\Models\HargaObat;
use App\Models\Penjamin;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Harga jual obat menurut penjamin kunjungan. Bila penjamin belum punya harga,
 * dipakai harga UMUM dan kejadiannya dicatat agar admin menindaklanjuti (aturan 27).
 */
class PencariHargaObat
{
    public function untuk(int $obatId, int $penjaminId, ?CarbonInterface $tanggal = null): int
    {
        $tanggal ??= Carbon::today();

        $harga = $this->cari($obatId, $penjaminId, $tanggal);

        if ($harga !== null) {
            return $harga;
        }

        $umum = Penjamin::where('kode', 'UMUM')->first();

        Log::warning('Harga khusus penjamin tidak ditemukan, memakai harga UMUM.', [
            'obat_id' => $obatId,
            'penjamin_id' => $penjaminId,
            'tanggal' => $tanggal->toDateString(),
        ]);

        $hargaUmum = $umum ? $this->cari($obatId, $umum->id, $tanggal) : null;

        if ($hargaUmum === null) {
            throw new RuntimeException(
                "Harga untuk obat #{$obatId} belum diisi, termasuk harga UMUM. Hubungi admin master data."
            );
        }

        return $hargaUmum;
    }

    private function cari(int $obatId, int $penjaminId, CarbonInterface $tanggal): ?int
    {
        $baris = HargaObat::where('obat_id', $obatId)
            ->where('penjamin_id', $penjaminId)
            ->whereDate('berlaku_mulai', '<=', $tanggal->toDateString())
            ->orderByDesc('berlaku_mulai')
            ->first();

        return $baris ? (int) $baris->harga : null;
    }
}
```

- [ ] **Step 6: Jalankan test sampai lulus**

Run: `php artisan test --filter=HargaObatTest`
Diharapkan: PASS, 6 test.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: tambah harga obat per penjamin beserta pencariannya"
```

---
### Task 3: Batch obat, kartu stok, dan penerimaan

**Files:**
- Create: migration `create_batch_obat_dan_mutasi_stok_tables`, `app/Models/BatchObat.php`, `app/Models/MutasiStok.php`, `database/factories/BatchObatFactory.php`, `app/Services/PenerimaanObat.php`
- Modify: migration terpisah `tambah_stok_minimum_ke_obat`, `app/Models/Obat.php`, `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/StokObatTest.php`

**Interfaces:**
- Consumes: `Obat` (Fase 1), `JenisMutasiStok` (Task 1)
- Produces:
  - Model `BatchObat` dengan scope `layakPakai(?CarbonInterface $tanggal = null)` — jumlah tersisa > 0 dan belum kedaluwarsa; method `kedaluwarsa(?CarbonInterface $tanggal = null): bool`.
  - Model `MutasiStok`.
  - `PenerimaanObat::terima(array $data, User $apoteker): BatchObat` — `$data` memuat `obat_id`, `no_batch`, `tanggal_kedaluwarsa`, `jumlah`, `harga_beli`.
  - `Obat::stokTersedia(): int` dan scope `Obat::menipis()`.

Memenuhi aturan 21, 25, dan 34.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/StokObatTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\JenisMutasiStok;
use App\Models\BatchObat;
use App\Models\MutasiStok;
use App\Models\Obat;
use App\Models\User;
use App\Services\PenerimaanObat;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StokObatTest extends TestCase
{
    use RefreshDatabase;

    private function terima(array $ganti = []): BatchObat
    {
        return app(PenerimaanObat::class)->terima(array_merge([
            'obat_id' => Obat::factory()->create()->id,
            'no_batch' => 'B2026001',
            'tanggal_kedaluwarsa' => '2027-12-31',
            'jumlah' => 100,
            'harga_beli' => 800,
        ], $ganti), User::factory()->create());
    }

    public function test_penerimaan_obat_menambah_stok_dan_mencatat_mutasi_masuk(): void
    {
        $batch = $this->terima();

        $this->assertSame(100, (int) $batch->jumlah_awal);
        $this->assertSame(100, (int) $batch->jumlah_tersisa);

        $mutasi = MutasiStok::where('batch_obat_id', $batch->id)->first();

        $this->assertSame(JenisMutasiStok::Masuk, $mutasi->jenis);
        $this->assertSame(100, (int) $mutasi->jumlah);
        $this->assertSame(100, (int) $mutasi->stok_sesudah);
        $this->assertNotNull($mutasi->dilakukan_oleh);
    }

    public function test_nomor_batch_ganda_untuk_obat_yang_sama_ditolak_database(): void
    {
        $batch = $this->terima();

        $this->expectException(QueryException::class);

        BatchObat::create([
            'obat_id' => $batch->obat_id,
            'no_batch' => $batch->no_batch,
            'tanggal_kedaluwarsa' => '2028-01-01',
            'jumlah_awal' => 10,
            'jumlah_tersisa' => 10,
            'harga_beli' => 900,
            'diterima_pada' => now(),
        ]);
    }

    public function test_nomor_batch_sama_boleh_dipakai_obat_berbeda(): void
    {
        $this->terima(['no_batch' => 'B2026001']);
        $kedua = $this->terima(['no_batch' => 'B2026001']);

        $this->assertSame('B2026001', $kedua->no_batch);
    }

    public function test_jumlah_penerimaan_minimal_satu(): void
    {
        $this->expectException(ValidationException::class);

        $this->terima(['jumlah' => 0]);
    }

    public function test_tanggal_kedaluwarsa_di_masa_lalu_ditolak_saat_penerimaan(): void
    {
        $this->expectException(ValidationException::class);

        $this->terima(['tanggal_kedaluwarsa' => now()->subDay()->toDateString()]);
    }

    public function test_batch_kedaluwarsa_tidak_masuk_scope_layak_pakai(): void
    {
        $obat = Obat::factory()->create();

        BatchObat::factory()->create([
            'obat_id' => $obat->id, 'jumlah_tersisa' => 50,
            'tanggal_kedaluwarsa' => '2020-01-01',
        ]);
        BatchObat::factory()->create([
            'obat_id' => $obat->id, 'jumlah_tersisa' => 30,
            'tanggal_kedaluwarsa' => '2030-01-01',
        ]);

        $this->assertSame(1, BatchObat::layakPakai()->where('obat_id', $obat->id)->count());
        $this->assertSame(30, $obat->stokTersedia());
    }

    public function test_batch_habis_tidak_masuk_scope_layak_pakai(): void
    {
        $obat = Obat::factory()->create();

        BatchObat::factory()->create([
            'obat_id' => $obat->id, 'jumlah_tersisa' => 0,
            'tanggal_kedaluwarsa' => '2030-01-01',
        ]);

        $this->assertSame(0, BatchObat::layakPakai()->where('obat_id', $obat->id)->count());
    }

    public function test_obat_di_bawah_stok_minimum_masuk_daftar_menipis(): void
    {
        $menipis = Obat::factory()->create(['stok_minimum' => 20]);
        $aman = Obat::factory()->create(['stok_minimum' => 20]);

        BatchObat::factory()->create([
            'obat_id' => $menipis->id, 'jumlah_tersisa' => 5,
            'tanggal_kedaluwarsa' => '2030-01-01',
        ]);
        BatchObat::factory()->create([
            'obat_id' => $aman->id, 'jumlah_tersisa' => 100,
            'tanggal_kedaluwarsa' => '2030-01-01',
        ]);

        $hasil = Obat::menipis()->pluck('id');

        $this->assertTrue($hasil->contains($menipis->id));
        $this->assertFalse($hasil->contains($aman->id));
    }

    public function test_obat_tanpa_batch_sama_sekali_dianggap_menipis(): void
    {
        $obat = Obat::factory()->create(['stok_minimum' => 10]);

        $this->assertTrue(Obat::menipis()->pluck('id')->contains($obat->id));
    }

    public function test_penerimaan_batch_tercatat_di_audit_log(): void
    {
        $batch = $this->terima();

        $this->assertDatabaseHas('audit_logs', [
            'model_tipe' => BatchObat::class,
            'model_id' => $batch->id,
            'aksi' => 'create',
        ]);
    }
}
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=StokObatTest`
Diharapkan: FAIL dengan "Target class [App\Services\PenerimaanObat] does not exist."

- [ ] **Step 3: Tulis migration kolom stok_minimum**

```bash
php artisan make:migration tambah_stok_minimum_ke_obat
```

```php
public function up(): void
{
    Schema::table('obat', function (Blueprint $table) {
        $table->unsignedInteger('stok_minimum')->default(10)->after('bentuk_sediaan');
    });
}

public function down(): void
{
    Schema::table('obat', function (Blueprint $table) {
        $table->dropColumn('stok_minimum');
    });
}
```

- [ ] **Step 4: Tulis migration batch dan mutasi**

```bash
php artisan make:migration create_batch_obat_dan_mutasi_stok_tables
```

```php
Schema::create('batch_obat', function (Blueprint $table) {
    $table->id();
    $table->foreignId('obat_id')->constrained('obat');
    $table->string('no_batch', 40);
    $table->date('tanggal_kedaluwarsa');
    $table->unsignedInteger('jumlah_awal');
    $table->unsignedInteger('jumlah_tersisa');
    $table->unsignedBigInteger('harga_beli')->default(0);
    $table->timestamp('diterima_pada')->nullable();
    $table->foreignId('diterima_oleh')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->unique(['obat_id', 'no_batch']);
    $table->index(['obat_id', 'tanggal_kedaluwarsa']);
});

Schema::create('mutasi_stok', function (Blueprint $table) {
    $table->id();
    $table->foreignId('batch_obat_id')->constrained('batch_obat')->cascadeOnDelete();
    $table->foreignId('obat_id')->constrained('obat');
    $table->string('jenis', 20);
    $table->integer('jumlah');
    $table->unsignedInteger('stok_sesudah');
    $table->foreignId('resep_id')->nullable()->constrained('resep')->nullOnDelete();
    $table->string('catatan', 255)->nullable();
    $table->foreignId('dilakukan_oleh')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('created_at')->useCurrent();
    $table->index(['obat_id', 'created_at']);
});
```

`jumlah` bertipe `integer` bertanda, bukan unsigned: mutasi keluar dicatat negatif supaya kartu stok bisa dijumlahkan langsung tanpa memeriksa jenisnya.

- [ ] **Step 5: Tulis model**

`app/Models/BatchObat.php`:

```php
<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class BatchObat extends Model
{
    use HasFactory;

    protected $table = 'batch_obat';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal_kedaluwarsa' => 'date',
            'diterima_pada' => 'datetime',
        ];
    }

    public function obat(): BelongsTo
    {
        return $this->belongsTo(Obat::class);
    }

    public function mutasi(): HasMany
    {
        return $this->hasMany(MutasiStok::class);
    }

    /**
     * Batch yang masih bersisa dan belum kedaluwarsa (aturan 22).
     */
    public function scopeLayakPakai(Builder $query, ?CarbonInterface $tanggal = null): Builder
    {
        $tanggal ??= Carbon::today();

        return $query->where('jumlah_tersisa', '>', 0)
            ->whereDate('tanggal_kedaluwarsa', '>=', $tanggal->toDateString());
    }

    public function kedaluwarsa(?CarbonInterface $tanggal = null): bool
    {
        return $this->tanggal_kedaluwarsa->lt($tanggal ?? Carbon::today());
    }
}
```

`app/Models/MutasiStok.php`:

```php
<?php

namespace App\Models;

use App\Enums\JenisMutasiStok;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MutasiStok extends Model
{
    protected $table = 'mutasi_stok';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['jenis' => JenisMutasiStok::class, 'created_at' => 'datetime'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(BatchObat::class, 'batch_obat_id');
    }

    public function obat(): BelongsTo
    {
        return $this->belongsTo(Obat::class);
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dilakukan_oleh');
    }
}
```

- [ ] **Step 6: Lengkapi model Obat**

Tambahkan ke `app/Models/Obat.php` (import `Builder`, `HasMany`):

```php
    public function batch(): HasMany
    {
        return $this->hasMany(BatchObat::class);
    }

    public function stokTersedia(): int
    {
        return (int) $this->batch()->layakPakai()->sum('jumlah_tersisa');
    }

    /**
     * Obat yang stok layak pakainya di bawah stok_minimum (aturan 34).
     * Obat tanpa batch sama sekali ikut terjaring karena stoknya nol.
     */
    public function scopeMenipis(Builder $query): Builder
    {
        return $query->whereRaw(
            '(SELECT COALESCE(SUM(jumlah_tersisa), 0) FROM batch_obat
              WHERE batch_obat.obat_id = obat.id
                AND batch_obat.jumlah_tersisa > 0
                AND batch_obat.tanggal_kedaluwarsa >= CURDATE()) < obat.stok_minimum'
        );
    }
```

- [ ] **Step 7: Tulis factory**

`database/factories/BatchObatFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\BatchObat;
use App\Models\Obat;
use Illuminate\Database\Eloquent\Factories\Factory;

class BatchObatFactory extends Factory
{
    protected $model = BatchObat::class;

    public function definition(): array
    {
        $jumlah = $this->faker->numberBetween(50, 200);

        return [
            'obat_id' => Obat::factory(),
            'no_batch' => strtoupper($this->faker->unique()->bothify('B####??')),
            'tanggal_kedaluwarsa' => $this->faker->dateTimeBetween('+6 months', '+3 years')->format('Y-m-d'),
            'jumlah_awal' => $jumlah,
            'jumlah_tersisa' => $jumlah,
            'harga_beli' => $this->faker->numberBetween(300, 30000),
            'diterima_pada' => now(),
        ];
    }
}
```

- [ ] **Step 8: Tulis PenerimaanObat**

`app/Services/PenerimaanObat.php`:

```php
<?php

namespace App\Services;

use App\Enums\JenisMutasiStok;
use App\Models\BatchObat;
use App\Models\MutasiStok;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PenerimaanObat
{
    public function terima(array $data, User $apoteker): BatchObat
    {
        $tervalidasi = Validator::make($data, [
            'obat_id' => ['required', 'exists:obat,id'],
            'no_batch' => ['required', 'string', 'max:40'],
            'tanggal_kedaluwarsa' => ['required', 'date', 'after_or_equal:today'],
            'jumlah' => ['required', 'integer', 'min:1'],
            'harga_beli' => ['required', 'integer', 'min:0'],
        ], [
            'no_batch.required' => 'Nomor batch wajib diisi.',
            'tanggal_kedaluwarsa.required' => 'Tanggal kedaluwarsa wajib diisi.',
            'tanggal_kedaluwarsa.after_or_equal' => 'Obat yang sudah kedaluwarsa tidak boleh diterima.',
            'jumlah.min' => 'Jumlah penerimaan minimal 1.',
        ])->validate();

        return DB::transaction(function () use ($tervalidasi, $apoteker) {
            $batch = BatchObat::create([
                'obat_id' => $tervalidasi['obat_id'],
                'no_batch' => $tervalidasi['no_batch'],
                'tanggal_kedaluwarsa' => $tervalidasi['tanggal_kedaluwarsa'],
                'jumlah_awal' => $tervalidasi['jumlah'],
                'jumlah_tersisa' => $tervalidasi['jumlah'],
                'harga_beli' => $tervalidasi['harga_beli'],
                'diterima_pada' => now(),
                'diterima_oleh' => $apoteker->id,
            ]);

            MutasiStok::create([
                'batch_obat_id' => $batch->id,
                'obat_id' => $batch->obat_id,
                'jenis' => JenisMutasiStok::Masuk,
                'jumlah' => $tervalidasi['jumlah'],
                'stok_sesudah' => $tervalidasi['jumlah'],
                'catatan' => 'Penerimaan batch '.$batch->no_batch,
                'dilakukan_oleh' => $apoteker->id,
                'created_at' => now(),
            ]);

            return $batch;
        });
    }
}
```

- [ ] **Step 9: Daftarkan BatchObat ke audit**

Di `app/Providers/AppServiceProvider.php`, tambahkan import `use App\Models\BatchObat;` dan ubah daftar menjadi:

```php
return [
    Pasien::class, Kunjungan::class, Pemeriksaan::class,
    Diagnosa::class, Tagihan::class, BatchObat::class,
];
```

- [ ] **Step 10: Jalankan test sampai lulus**

Run: `php artisan test --filter=StokObatTest`
Diharapkan: PASS, 10 test.

- [ ] **Step 11: Commit**

```bash
git add -A
git commit -m "feat: tambah stok obat berbasis batch, kartu stok, dan penerimaan barang"
```

---
### Task 4: Penyiapan resep dengan alokasi FEFO

**Files:**
- Create: migration `tambah_kolom_penyiapan_ke_resep`, migration `create_pengambilan_batch_table`, `app/Models/PengambilanBatch.php`, `app/Exceptions/StokTidakCukup.php`, `app/Exceptions/SeluruhBatchKedaluwarsa.php`, `app/Services/PenyiapanResep.php`
- Modify: `app/Models/Resep.php`, `app/Models/ResepDetail.php`
- Test: `tests/Feature/PenyiapanResepTest.php`

**Interfaces:**
- Consumes: `BatchObat`, `MutasiStok` (Task 3), `PencariHargaObat` (Task 2), `StatusResep`, `JenisMutasiStok` (Task 1), `Resep`, `ResepDetail` (Fase 1)
- Produces:
  - `PenyiapanResep::siapkan(Resep $resep, User $apoteker): Resep` — mengalokasikan batch secara FEFO, memotong stok, menyalin harga ke `resep_detail.harga_satuan`, mengisi `jumlah_diserahkan`, dan mengubah status menjadi `Disiapkan`.
  - Model `PengambilanBatch` dengan relasi `resepDetail()` dan `batch()`.
  - `StokTidakCukup` dan `SeluruhBatchKedaluwarsa`, keduanya turunan `RuntimeException`, dengan pesan yang menyebut nama obat.
  - `ResepDetail::pengambilan(): HasMany` dan `ResepDetail::subtotal(): int`.

Memenuhi aturan 22, 23, 24, 26, dan 31.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/PenyiapanResepTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\JenisMutasiStok;
use App\Enums\StatusResep;
use App\Exceptions\SeluruhBatchKedaluwarsa;
use App\Exceptions\StokTidakCukup;
use App\Models\BatchObat;
use App\Models\HargaObat;
use App\Models\Kunjungan;
use App\Models\MutasiStok;
use App\Models\Obat;
use App\Models\Penjamin;
use App\Models\Resep;
use App\Models\User;
use App\Services\PenulisanResep;
use App\Services\PenyiapanResep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PenyiapanResepTest extends TestCase
{
    use RefreshDatabase;

    private Penjamin $umum;
    private Obat $obat;
    private Kunjungan $kunjungan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $this->obat = Obat::factory()->create(['nama' => 'Paracetamol 500 mg']);
        $this->kunjungan = Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]);

        // Tagihan sudah ada sejak dokter menyelesaikan kunjungan — apotek hanya
        // menambahinya. Task 5 membuat penyiapan bergantung pada tagihan ini.
        \App\Models\Tagihan::factory()->create([
            'kunjungan_id' => $this->kunjungan->id,
            'penjamin_id' => $this->umum->id,
        ]);

        HargaObat::factory()->create([
            'obat_id' => $this->obat->id,
            'penjamin_id' => $this->umum->id,
            'harga' => 1500,
            'berlaku_mulai' => '2026-01-01',
        ]);
    }

    private function batch(string $noBatch, string $kedaluwarsa, int $jumlah): BatchObat
    {
        return BatchObat::factory()->create([
            'obat_id' => $this->obat->id,
            'no_batch' => $noBatch,
            'tanggal_kedaluwarsa' => $kedaluwarsa,
            'jumlah_awal' => $jumlah,
            'jumlah_tersisa' => $jumlah,
            'harga_beli' => 800,
        ]);
    }

    private function resep(int $jumlah = 10): Resep
    {
        return app(PenulisanResep::class)->tulis($this->kunjungan, [[
            'obat_id' => $this->obat->id,
            'jumlah' => $jumlah,
            'aturan_pakai' => '3x1 sesudah makan',
        ]], User::factory()->create());
    }

    private function apoteker(): User
    {
        return User::factory()->create();
    }

    public function test_penyiapan_mengambil_batch_yang_paling_dekat_kedaluwarsa(): void
    {
        $lama = $this->batch('LAMA', '2027-01-31', 100);
        $baru = $this->batch('BARU', '2029-01-31', 100);

        app(PenyiapanResep::class)->siapkan($this->resep(10), $this->apoteker());

        $this->assertSame(90, (int) $lama->refresh()->jumlah_tersisa);
        $this->assertSame(100, (int) $baru->refresh()->jumlah_tersisa);
    }

    public function test_satu_baris_resep_bisa_ditarik_dari_dua_batch(): void
    {
        $lama = $this->batch('LAMA', '2027-01-31', 6);
        $baru = $this->batch('BARU', '2029-01-31', 50);

        $resep = app(PenyiapanResep::class)->siapkan($this->resep(10), $this->apoteker());

        $this->assertSame(0, (int) $lama->refresh()->jumlah_tersisa);
        $this->assertSame(46, (int) $baru->refresh()->jumlah_tersisa);

        $pengambilan = $resep->detail->first()->pengambilan;

        $this->assertCount(2, $pengambilan);
        $this->assertSame(6, (int) $pengambilan->firstWhere('batch_obat_id', $lama->id)->jumlah);
        $this->assertSame(4, (int) $pengambilan->firstWhere('batch_obat_id', $baru->id)->jumlah);
    }

    public function test_batch_kedaluwarsa_tidak_ikut_dialokasikan(): void
    {
        $kedaluwarsa = $this->batch('KEDALUWARSA', now()->subDay()->toDateString(), 100);
        $layak = $this->batch('LAYAK', '2029-01-31', 100);

        app(PenyiapanResep::class)->siapkan($this->resep(10), $this->apoteker());

        $this->assertSame(100, (int) $kedaluwarsa->refresh()->jumlah_tersisa);
        $this->assertSame(90, (int) $layak->refresh()->jumlah_tersisa);
    }

    public function test_stok_kurang_menolak_penyiapan_dan_tidak_mengubah_stok(): void
    {
        $batch = $this->batch('SATU', '2029-01-31', 4);
        $resep = $this->resep(10);

        try {
            app(PenyiapanResep::class)->siapkan($resep, $this->apoteker());
            $this->fail('Penyiapan seharusnya ditolak karena stok kurang.');
        } catch (StokTidakCukup $e) {
            $this->assertStringContainsString('Paracetamol 500 mg', $e->getMessage());
        }

        $this->assertSame(4, (int) $batch->refresh()->jumlah_tersisa);
        $this->assertSame(StatusResep::Dibuat, $resep->refresh()->status);
        $this->assertSame(0, MutasiStok::where('jenis', JenisMutasiStok::Keluar)->count());
    }

    public function test_seluruh_batch_kedaluwarsa_ditolak_dengan_pesan_berbeda(): void
    {
        $this->batch('KEDALUWARSA', now()->subDay()->toDateString(), 100);

        $this->expectException(SeluruhBatchKedaluwarsa::class);

        app(PenyiapanResep::class)->siapkan($this->resep(10), $this->apoteker());
    }

    public function test_penyiapan_menyalin_harga_sesuai_penjamin_kunjungan(): void
    {
        $this->batch('SATU', '2029-01-31', 100);

        $resep = app(PenyiapanResep::class)->siapkan($this->resep(10), $this->apoteker());
        $baris = $resep->detail->first();

        $this->assertSame(1500, (int) $baris->harga_satuan);
        $this->assertSame(10, (int) $baris->jumlah_diserahkan);
        $this->assertSame(15000, $baris->subtotal());
    }

    public function test_perubahan_master_harga_tidak_mengubah_resep_yang_sudah_disiapkan(): void
    {
        $this->batch('SATU', '2029-01-31', 100);
        $resep = app(PenyiapanResep::class)->siapkan($this->resep(10), $this->apoteker());

        HargaObat::where('obat_id', $this->obat->id)->update(['harga' => 9999]);

        $this->assertSame(1500, (int) $resep->detail->first()->refresh()->harga_satuan);
    }

    public function test_penyiapan_mencatat_mutasi_keluar_bernilai_negatif(): void
    {
        $batch = $this->batch('SATU', '2029-01-31', 100);

        $resep = app(PenyiapanResep::class)->siapkan($this->resep(10), $this->apoteker());

        $mutasi = MutasiStok::where('jenis', JenisMutasiStok::Keluar)->first();

        $this->assertSame(-10, (int) $mutasi->jumlah);
        $this->assertSame(90, (int) $mutasi->stok_sesudah);
        $this->assertSame($batch->id, $mutasi->batch_obat_id);
        $this->assertSame($resep->id, $mutasi->resep_id);
    }

    public function test_status_resep_berubah_menjadi_disiapkan(): void
    {
        $this->batch('SATU', '2029-01-31', 100);

        $resep = app(PenyiapanResep::class)->siapkan($this->resep(10), $this->apoteker());

        $this->assertSame(StatusResep::Disiapkan, $resep->status);
        $this->assertNotNull($resep->disiapkan_pada);
        $this->assertNotNull($resep->disiapkan_oleh);
    }

    public function test_resep_yang_sudah_disiapkan_tidak_bisa_disiapkan_ulang(): void
    {
        $this->batch('SATU', '2029-01-31', 100);
        $layanan = app(PenyiapanResep::class);
        $resep = $layanan->siapkan($this->resep(10), $this->apoteker());

        $this->expectException(\RuntimeException::class);

        $layanan->siapkan($resep, $this->apoteker());
    }
}
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=PenyiapanResepTest`
Diharapkan: FAIL dengan "Class App\Exceptions\StokTidakCukup not found".

- [ ] **Step 3: Tulis migration**

```bash
php artisan make:migration tambah_kolom_penyiapan_ke_resep
```

```php
public function up(): void
{
    Schema::table('resep', function (Blueprint $table) {
        $table->timestamp('disiapkan_pada')->nullable()->after('status');
        $table->foreignId('disiapkan_oleh')->nullable()->after('disiapkan_pada')
            ->constrained('users')->nullOnDelete();
        $table->timestamp('diserahkan_pada')->nullable()->after('disiapkan_oleh');
        $table->foreignId('diserahkan_oleh')->nullable()->after('diserahkan_pada')
            ->constrained('users')->nullOnDelete();
    });

    Schema::table('resep_detail', function (Blueprint $table) {
        $table->unsignedSmallInteger('jumlah_diserahkan')->default(0)->after('jumlah');
        $table->unsignedBigInteger('harga_satuan')->default(0)->after('jumlah_diserahkan');
    });
}

public function down(): void
{
    Schema::table('resep_detail', function (Blueprint $table) {
        $table->dropColumn(['jumlah_diserahkan', 'harga_satuan']);
    });

    Schema::table('resep', function (Blueprint $table) {
        $table->dropConstrainedForeignId('disiapkan_oleh');
        $table->dropConstrainedForeignId('diserahkan_oleh');
        $table->dropColumn(['disiapkan_pada', 'diserahkan_pada']);
    });
}
```

```bash
php artisan make:migration create_pengambilan_batch_table
```

```php
Schema::create('pengambilan_batch', function (Blueprint $table) {
    $table->id();
    $table->foreignId('resep_detail_id')->constrained('resep_detail')->cascadeOnDelete();
    $table->foreignId('batch_obat_id')->constrained('batch_obat');
    $table->unsignedInteger('jumlah');
    $table->unsignedBigInteger('harga_beli');
    $table->timestamps();
});
```

- [ ] **Step 4: Tulis exception**

`app/Exceptions/StokTidakCukup.php`:

```php
<?php

namespace App\Exceptions;

use RuntimeException;

class StokTidakCukup extends RuntimeException
{
    public static function untuk(string $namaObat, int $diminta, int $tersedia): self
    {
        return new self(
            "Stok {$namaObat} tidak cukup: diminta {$diminta}, tersedia {$tersedia}."
        );
    }
}
```

`app/Exceptions/SeluruhBatchKedaluwarsa.php`:

```php
<?php

namespace App\Exceptions;

use RuntimeException;

class SeluruhBatchKedaluwarsa extends RuntimeException
{
    public static function untuk(string $namaObat): self
    {
        return new self(
            "Seluruh batch {$namaObat} sudah kedaluwarsa dan tidak boleh diserahkan. Perlu pemusnahan dan pemesanan ulang."
        );
    }
}
```

Dua exception terpisah, bukan satu dengan kode berbeda: tindak lanjutnya memang berbeda — yang satu perlu pemesanan, yang satu perlu pemusnahan.

- [ ] **Step 5: Tulis model PengambilanBatch dan lengkapi ResepDetail**

`app/Models/PengambilanBatch.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengambilanBatch extends Model
{
    protected $table = 'pengambilan_batch';

    protected $guarded = [];

    public function resepDetail(): BelongsTo
    {
        return $this->belongsTo(ResepDetail::class, 'resep_detail_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(BatchObat::class, 'batch_obat_id');
    }
}
```

Tambahkan ke `app/Models/ResepDetail.php` (import `HasMany`):

```php
    public function pengambilan(): HasMany
    {
        return $this->hasMany(PengambilanBatch::class, 'resep_detail_id');
    }

    public function subtotal(): int
    {
        return (int) $this->jumlah_diserahkan * (int) $this->harga_satuan;
    }
```

- [ ] **Step 6: Tulis PenyiapanResep**

`app/Services/PenyiapanResep.php`:

```php
<?php

namespace App\Services;

use App\Enums\JenisMutasiStok;
use App\Enums\StatusResep;
use App\Exceptions\SeluruhBatchKedaluwarsa;
use App\Exceptions\StokTidakCukup;
use App\Models\BatchObat;
use App\Models\MutasiStok;
use App\Models\Resep;
use App\Models\ResepDetail;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PenyiapanResep
{
    public function __construct(private readonly PencariHargaObat $pencariHarga) {}

    public function siapkan(Resep $resep, User $apoteker): Resep
    {
        if (! $resep->status->bisaDisiapkan()) {
            throw new RuntimeException(
                "Resep {$resep->no_resep} sudah berstatus {$resep->status->label()} dan tidak bisa disiapkan lagi."
            );
        }

        $kunjungan = $resep->kunjungan;
        $tanggal = Carbon::today();

        return DB::transaction(function () use ($resep, $apoteker, $kunjungan, $tanggal) {
            // Kunci baris resep supaya dua apoteker tidak menyiapkan resep yang sama.
            $terkunci = Resep::whereKey($resep->id)->lockForUpdate()->first();

            if (! $terkunci->status->bisaDisiapkan()) {
                throw new RuntimeException('Resep ini baru saja disiapkan petugas lain.');
            }

            foreach ($terkunci->detail as $baris) {
                $alokasi = $this->alokasikan($baris, $tanggal);

                foreach ($alokasi as ['batch' => $batch, 'jumlah' => $jumlah]) {
                    $sisa = (int) $batch->jumlah_tersisa - $jumlah;

                    $batch->update(['jumlah_tersisa' => $sisa]);

                    $baris->pengambilan()->create([
                        'batch_obat_id' => $batch->id,
                        'jumlah' => $jumlah,
                        'harga_beli' => $batch->harga_beli,
                    ]);

                    MutasiStok::create([
                        'batch_obat_id' => $batch->id,
                        'obat_id' => $batch->obat_id,
                        'jenis' => JenisMutasiStok::Keluar,
                        'jumlah' => -$jumlah,
                        'stok_sesudah' => $sisa,
                        'resep_id' => $terkunci->id,
                        'catatan' => 'Penyiapan resep '.$terkunci->no_resep,
                        'dilakukan_oleh' => $apoteker->id,
                        'created_at' => now(),
                    ]);
                }

                $baris->update([
                    'jumlah_diserahkan' => $baris->jumlah,
                    'harga_satuan' => $this->pencariHarga->untuk(
                        $baris->obat_id, $kunjungan->penjamin_id, $tanggal
                    ),
                ]);
            }

            $terkunci->update([
                'status' => StatusResep::Disiapkan,
                'disiapkan_pada' => now(),
                'disiapkan_oleh' => $apoteker->id,
            ]);

            return $terkunci->refresh()->load('detail.pengambilan');
        });
    }

    /**
     * Alokasi FEFO: batch berkedaluwarsa terdekat diambil lebih dulu, dan satu baris
     * resep boleh ditarik dari beberapa batch sekaligus (aturan 23).
     *
     * @return list<array{batch: BatchObat, jumlah: int}>
     */
    private function alokasikan(ResepDetail $baris, Carbon $tanggal): array
    {
        $dibutuhkan = (int) $baris->jumlah;

        $batch = BatchObat::where('obat_id', $baris->obat_id)
            ->layakPakai($tanggal)
            ->orderBy('tanggal_kedaluwarsa')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $tersedia = (int) $batch->sum('jumlah_tersisa');

        if ($tersedia < $dibutuhkan) {
            $namaObat = $baris->obat->nama;

            // Bedakan "tidak punya stok layak pakai sama sekali padahal batchnya ada"
            // dari "stok memang kurang" — tindak lanjutnya berbeda.
            $adaBatchKedaluwarsa = BatchObat::where('obat_id', $baris->obat_id)
                ->where('jumlah_tersisa', '>', 0)
                ->whereDate('tanggal_kedaluwarsa', '<', $tanggal->toDateString())
                ->exists();

            if ($tersedia === 0 && $adaBatchKedaluwarsa) {
                throw SeluruhBatchKedaluwarsa::untuk($namaObat);
            }

            throw StokTidakCukup::untuk($namaObat, $dibutuhkan, $tersedia);
        }

        $alokasi = [];
        $sisa = $dibutuhkan;

        foreach ($batch as $b) {
            if ($sisa === 0) {
                break;
            }

            $ambil = min($sisa, (int) $b->jumlah_tersisa);
            $alokasi[] = ['batch' => $b, 'jumlah' => $ambil];
            $sisa -= $ambil;
        }

        return $alokasi;
    }
}
```

`ResepDetail` perlu relasi `obat()` — sudah ada sejak Fase 1.

- [ ] **Step 7: Jalankan test sampai lulus**

Run: `php artisan test --filter=PenyiapanResepTest`
Diharapkan: PASS, 10 test.

- [ ] **Step 8: Jalankan seluruh test**

Run: `php artisan test`
Diharapkan: seluruh test tetap lulus.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: tambah penyiapan resep dengan alokasi FEFO dan penelusuran batch"
```

---
### Task 5: Biaya obat masuk tagihan dan kunci kasir

**Files:**
- Create: migration `tambah_resep_detail_id_ke_tagihan_detail`
- Modify: `app/Services/PenyusunTagihan.php`, `app/Services/PenyiapanResep.php`, `app/Services/ProsesPembayaran.php`, `app/Models/TagihanDetail.php`
- Test: `tests/Feature/TagihanObatTest.php`

**Interfaces:**
- Consumes: `PenyiapanResep` (Task 4), `Tagihan`, `ProsesPembayaran` (Fase 1)
- Produces: `PenyusunTagihan::tambahObat(Resep $resep): Tagihan` — menambahkan satu baris `tagihan_detail` per obat yang disiapkan lalu menghitung ulang total tagihan. `ProsesPembayaran::bayar()` menolak tagihan yang kunjungannya masih punya resep berstatus `dibuat`.

Memenuhi aturan 28 dan 29.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/TagihanObatTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\MetodePembayaran;
use App\Enums\StatusTagihan;
use App\Models\BatchObat;
use App\Models\HargaObat;
use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\Penjamin;
use App\Models\Resep;
use App\Models\Tagihan;
use App\Models\User;
use App\Services\PenulisanResep;
use App\Services\PenyiapanResep;
use App\Services\ProsesPembayaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class TagihanObatTest extends TestCase
{
    use RefreshDatabase;

    private Obat $obat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->obat = Obat::factory()->create(['nama' => 'Paracetamol 500 mg']);
    }

    private function siapkanSkenario(string $jenisPenjamin): array
    {
        $penjamin = Penjamin::factory()->create([
            'kode' => $jenisPenjamin === 'tunai' ? 'UMUM' : 'BPJS',
            'jenis' => $jenisPenjamin,
        ]);

        HargaObat::factory()->create([
            'obat_id' => $this->obat->id, 'penjamin_id' => $penjamin->id,
            'harga' => 1500, 'berlaku_mulai' => '2026-01-01',
        ]);

        BatchObat::factory()->create([
            'obat_id' => $this->obat->id, 'tanggal_kedaluwarsa' => '2029-01-31',
            'jumlah_awal' => 100, 'jumlah_tersisa' => 100, 'harga_beli' => 800,
        ]);

        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $penjamin->id]);

        $tagihan = Tagihan::factory()->create([
            'kunjungan_id' => $kunjungan->id,
            'penjamin_id' => $penjamin->id,
            'total' => 50000,
            'ditanggung_penjamin' => $jenisPenjamin === 'penjamin' ? 50000 : 0,
            'ditagihkan_ke_pasien' => $jenisPenjamin === 'penjamin' ? 0 : 50000,
            'status' => $jenisPenjamin === 'penjamin'
                ? StatusTagihan::DitanggungPenjamin
                : StatusTagihan::BelumBayar,
        ]);

        // Baris tindakan harus benar-benar ada: hitungUlang() menjumlahkan dari
        // rincian, bukan dari kolom total. Tagihan bertotal tanpa rincian adalah
        // keadaan yang tidak pernah terjadi di sistem sungguhan.
        $tagihan->detail()->create([
            'deskripsi' => 'Konsultasi Dokter Umum',
            'jumlah' => 1,
            'tarif_satuan' => 50000,
            'subtotal' => 50000,
        ]);

        $resep = app(PenulisanResep::class)->tulis($kunjungan, [[
            'obat_id' => $this->obat->id, 'jumlah' => 10, 'aturan_pakai' => '3x1',
        ]], User::factory()->create());

        return [$kunjungan, $tagihan, $resep];
    }

    public function test_biaya_obat_masuk_ke_tagihan_kunjungan_yang_sama(): void
    {
        [$kunjungan, $tagihan, $resep] = $this->siapkanSkenario('tunai');

        app(PenyiapanResep::class)->siapkan($resep, User::factory()->create());

        $tagihan->refresh();

        $this->assertSame(65000, (int) $tagihan->total);
        $this->assertSame(65000, (int) $tagihan->ditagihkan_ke_pasien);
        $this->assertDatabaseHas('tagihan_detail', [
            'tagihan_id' => $tagihan->id,
            'deskripsi' => 'Paracetamol 500 mg',
            'jumlah' => 10,
            'tarif_satuan' => 1500,
            'subtotal' => 15000,
        ]);
    }

    public function test_obat_pasien_bpjs_menambah_total_tapi_tetap_tidak_ditagihkan(): void
    {
        [$kunjungan, $tagihan, $resep] = $this->siapkanSkenario('penjamin');

        app(PenyiapanResep::class)->siapkan($resep, User::factory()->create());

        $tagihan->refresh();

        $this->assertSame(65000, (int) $tagihan->total);
        $this->assertSame(65000, (int) $tagihan->ditanggung_penjamin);
        $this->assertSame(0, (int) $tagihan->ditagihkan_ke_pasien);
        $this->assertSame(StatusTagihan::DitanggungPenjamin, $tagihan->status);
    }

    public function test_tagihan_yang_sudah_lunas_tidak_bisa_ditambahi_baris_obat(): void
    {
        [$kunjungan, $tagihan, $resep] = $this->siapkanSkenario('tunai');
        $tagihan->update(['status' => StatusTagihan::Lunas]);

        $this->expectException(RuntimeException::class);

        app(PenyiapanResep::class)->siapkan($resep, User::factory()->create());
    }

    public function test_tagihan_tidak_bisa_dilunasi_saat_resep_belum_disiapkan(): void
    {
        [$kunjungan, $tagihan, $resep] = $this->siapkanSkenario('tunai');
        $kasir = User::factory()->create();

        try {
            app(ProsesPembayaran::class)->bayar($tagihan, MetodePembayaran::Tunai, 50000, $kasir);
            $this->fail('Pembayaran seharusnya ditolak karena resep belum disiapkan.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString($resep->no_resep, $e->getMessage());
        }

        $this->assertSame(StatusTagihan::BelumBayar, $tagihan->refresh()->status);
    }

    public function test_tagihan_bisa_dilunasi_setelah_resep_disiapkan(): void
    {
        [$kunjungan, $tagihan, $resep] = $this->siapkanSkenario('tunai');

        app(PenyiapanResep::class)->siapkan($resep, User::factory()->create());
        $tagihan->refresh();

        app(ProsesPembayaran::class)->bayar($tagihan, MetodePembayaran::Tunai, 65000, User::factory()->create());

        $this->assertSame(StatusTagihan::Lunas, $tagihan->refresh()->status);
    }

    public function test_kunjungan_tanpa_resep_tetap_bisa_dilunasi(): void
    {
        $umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $umum->id]);

        $tagihan = Tagihan::factory()->create([
            'kunjungan_id' => $kunjungan->id, 'penjamin_id' => $umum->id,
            'total' => 50000, 'ditagihkan_ke_pasien' => 50000,
            'status' => StatusTagihan::BelumBayar,
        ]);

        app(ProsesPembayaran::class)->bayar($tagihan, MetodePembayaran::Tunai, 50000, User::factory()->create());

        $this->assertSame(StatusTagihan::Lunas, $tagihan->refresh()->status);
    }
}
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=TagihanObatTest`
Diharapkan: FAIL — biaya obat belum masuk tagihan, dan pembayaran belum terkunci.

- [ ] **Step 3: Tulis migration penelusuran baris obat**

```bash
php artisan make:migration tambah_resep_detail_id_ke_tagihan_detail
```

```php
public function up(): void
{
    Schema::table('tagihan_detail', function (Blueprint $table) {
        $table->foreignId('resep_detail_id')->nullable()->after('tindakan_kunjungan_id')
            ->constrained('resep_detail')->nullOnDelete();
    });
}

public function down(): void
{
    Schema::table('tagihan_detail', function (Blueprint $table) {
        $table->dropConstrainedForeignId('resep_detail_id');
    });
}
```

- [ ] **Step 4: Tambahkan method tambahObat pada PenyusunTagihan**

Di `app/Services/PenyusunTagihan.php`, tambahkan import `use App\Enums\StatusTagihan;` (sudah ada), `use App\Models\Resep;`, `use RuntimeException;`, lalu method:

```php
    /**
     * Menambahkan baris obat ke tagihan kunjungan yang sudah ada (aturan 28).
     * Tagihan tidak disusun ulang — hanya ditambahi, dan hanya selama belum lunas.
     */
    public function tambahObat(Resep $resep): Tagihan
    {
        $tagihan = $resep->kunjungan->tagihan;

        if ($tagihan === null) {
            throw new RuntimeException(
                'Kunjungan ini belum punya tagihan. Dokter harus menyelesaikan kunjungan lebih dulu.'
            );
        }

        if ($tagihan->status === StatusTagihan::Lunas) {
            throw new RuntimeException(
                "Tagihan {$tagihan->no_tagihan} sudah lunas dan tidak bisa ditambahi biaya obat."
            );
        }

        return DB::transaction(function () use ($resep, $tagihan) {
            foreach ($resep->detail as $baris) {
                if ((int) $baris->jumlah_diserahkan === 0) {
                    continue;
                }

                $tagihan->detail()->create([
                    'resep_detail_id' => $baris->id,
                    'deskripsi' => $baris->obat->nama,
                    'jumlah' => $baris->jumlah_diserahkan,
                    'tarif_satuan' => $baris->harga_satuan,
                    'subtotal' => $baris->subtotal(),
                ]);
            }

            return $this->hitungUlang($tagihan);
        });
    }

    /**
     * Menyetel ulang total dari seluruh rinciannya. Dipakai setiap kali baris
     * ditambahkan atau dibatalkan, supaya angkanya tidak pernah dihitung dua tempat.
     */
    public function hitungUlang(Tagihan $tagihan): Tagihan
    {
        $total = (int) $tagihan->detail()->sum('subtotal');
        $ditanggung = $tagihan->penjamin->ditanggung();

        $tagihan->update([
            'total' => $total,
            'ditanggung_penjamin' => $ditanggung ? $total : 0,
            'ditagihkan_ke_pasien' => $ditanggung ? 0 : $total,
        ]);

        return $tagihan->refresh();
    }
```

- [ ] **Step 5: Panggil dari PenyiapanResep**

Di `app/Services/PenyiapanResep.php`, ubah constructor dan tambahkan pemanggilan sebelum `return` pada transaksi `siapkan()`:

```php
    public function __construct(
        private readonly PencariHargaObat $pencariHarga,
        private readonly PenyusunTagihan $penyusunTagihan,
    ) {}
```

Setelah `$terkunci->update([...])` dan sebelum `return`:

```php
            $this->penyusunTagihan->tambahObat($terkunci->refresh()->load('detail.obat'));
```

Pengecekan "tagihan sudah lunas" berada di dalam transaksi yang sama, jadi bila ditolak, seluruh pemotongan stok ikut dibatalkan.

- [ ] **Step 6: Tambahkan penjaga pada ProsesPembayaran**

Di `app/Services/ProsesPembayaran.php`, tambahkan import `use App\Enums\StatusResep;` dan `use App\Models\Resep;`, lalu sisipkan tepat setelah baris `$terkunci = Tagihan::whereKey(...)->lockForUpdate()->first();`:

```php
            // Aturan 29: uang tidak boleh diterima sebelum apotek selesai, karena
            // biaya obat baru masuk tagihan pada saat penyiapan.
            $menunggu = Resep::where('kunjungan_id', $terkunci->kunjungan_id)
                ->where('status', StatusResep::Dibuat->value)
                ->first();

            if ($menunggu !== null) {
                throw new RuntimeException(
                    "Tagihan belum bisa dilunasi: resep {$menunggu->no_resep} belum disiapkan apotek."
                );
            }
```

- [ ] **Step 7: Jalankan test sampai lulus**

Run: `php artisan test --filter=TagihanObatTest`
Diharapkan: PASS, 6 test.

- [ ] **Step 8: Jalankan seluruh test**

Run: `php artisan test`
Diharapkan: seluruh test lulus. Bila `PembayaranTest` Fase 1 gagal, periksa apakah `TagihanFactory` membuat kunjungan tanpa resep — seharusnya ya, sehingga penjaga baru tidak aktif.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: bebankan biaya obat ke tagihan dan kunci kasir sampai resep disiapkan"
```

---

### Task 6: Penyerahan obat dan pembatalan penyiapan

**Files:**
- Create: `app/Services/PenyerahanObat.php`
- Modify: `app/Services/PenyiapanResep.php` (method `batalkan`)
- Test: `tests/Feature/PenyerahanObatTest.php`

**Interfaces:**
- Consumes: `PenyiapanResep` (Task 4), `PenyusunTagihan::hitungUlang()` (Task 5)
- Produces:
  - `PenyerahanObat::serahkan(Resep $resep, User $apoteker): Resep`
  - `PenyiapanResep::batalkan(Resep $resep, User $apoteker, string $alasan): Resep`

Memenuhi aturan 30, 31, dan 32.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/PenyerahanObatTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\JenisMutasiStok;
use App\Enums\MetodePembayaran;
use App\Enums\StatusResep;
use App\Enums\StatusTagihan;
use App\Models\AuditLog;
use App\Models\BatchObat;
use App\Models\HargaObat;
use App\Models\Kunjungan;
use App\Models\MutasiStok;
use App\Models\Obat;
use App\Models\Penjamin;
use App\Models\Resep;
use App\Models\Tagihan;
use App\Models\User;
use App\Services\PenulisanResep;
use App\Services\PenyerahanObat;
use App\Services\PenyiapanResep;
use App\Services\ProsesPembayaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class PenyerahanObatTest extends TestCase
{
    use RefreshDatabase;

    private Obat $obat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->obat = Obat::factory()->create(['nama' => 'Amoksisilin 500 mg']);
    }

    /** @return array{0: Tagihan, 1: Resep, 2: BatchObat} */
    private function resepSiap(string $jenisPenjamin): array
    {
        $penjamin = Penjamin::factory()->create([
            'kode' => $jenisPenjamin === 'tunai' ? 'UMUM' : 'BPJS',
            'jenis' => $jenisPenjamin,
        ]);

        HargaObat::factory()->create([
            'obat_id' => $this->obat->id, 'penjamin_id' => $penjamin->id,
            'harga' => 2000, 'berlaku_mulai' => '2026-01-01',
        ]);

        $batch = BatchObat::factory()->create([
            'obat_id' => $this->obat->id, 'tanggal_kedaluwarsa' => '2029-01-31',
            'jumlah_awal' => 100, 'jumlah_tersisa' => 100, 'harga_beli' => 1200,
        ]);

        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $penjamin->id]);

        $tagihan = Tagihan::factory()->create([
            'kunjungan_id' => $kunjungan->id, 'penjamin_id' => $penjamin->id,
            'status' => $jenisPenjamin === 'penjamin'
                ? StatusTagihan::DitanggungPenjamin
                : StatusTagihan::BelumBayar,
        ]);

        $resep = app(PenulisanResep::class)->tulis($kunjungan, [[
            'obat_id' => $this->obat->id, 'jumlah' => 10, 'aturan_pakai' => '3x1',
        ]], User::factory()->create());

        $resep = app(PenyiapanResep::class)->siapkan($resep, User::factory()->create());

        return [$tagihan->refresh(), $resep, $batch];
    }

    public function test_obat_pasien_umum_tidak_bisa_diserahkan_sebelum_lunas(): void
    {
        [$tagihan, $resep] = $this->resepSiap('tunai');

        $this->expectException(RuntimeException::class);

        app(PenyerahanObat::class)->serahkan($resep, User::factory()->create());
    }

    public function test_obat_pasien_umum_bisa_diserahkan_setelah_lunas(): void
    {
        [$tagihan, $resep] = $this->resepSiap('tunai');

        app(ProsesPembayaran::class)->bayar(
            $tagihan, MetodePembayaran::Tunai, (int) $tagihan->ditagihkan_ke_pasien, User::factory()->create()
        );

        $diserahkan = app(PenyerahanObat::class)->serahkan($resep, User::factory()->create());

        $this->assertSame(StatusResep::Diserahkan, $diserahkan->status);
        $this->assertNotNull($diserahkan->diserahkan_pada);
    }

    public function test_obat_pasien_bpjs_bisa_diserahkan_tanpa_ke_kasir(): void
    {
        [$tagihan, $resep] = $this->resepSiap('penjamin');

        $diserahkan = app(PenyerahanObat::class)->serahkan($resep, User::factory()->create());

        $this->assertSame(StatusResep::Diserahkan, $diserahkan->status);
        $this->assertSame(StatusTagihan::DitanggungPenjamin, $tagihan->refresh()->status);
    }

    public function test_resep_yang_belum_disiapkan_tidak_bisa_diserahkan(): void
    {
        $umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $umum->id]);

        $resep = app(PenulisanResep::class)->tulis($kunjungan, [[
            'obat_id' => $this->obat->id, 'jumlah' => 5, 'aturan_pakai' => '2x1',
        ]], User::factory()->create());

        $this->expectException(RuntimeException::class);

        app(PenyerahanObat::class)->serahkan($resep, User::factory()->create());
    }

    public function test_resep_yang_sudah_diserahkan_tidak_bisa_dibatalkan(): void
    {
        [$tagihan, $resep] = $this->resepSiap('penjamin');
        $diserahkan = app(PenyerahanObat::class)->serahkan($resep, User::factory()->create());

        $this->expectException(RuntimeException::class);

        app(PenyiapanResep::class)->batalkan($diserahkan, User::factory()->create(), 'Salah pasien');
    }

    public function test_pembatalan_penyiapan_mengembalikan_stok_ke_batch_asal(): void
    {
        [$tagihan, $resep, $batch] = $this->resepSiap('tunai');

        $this->assertSame(90, (int) $batch->refresh()->jumlah_tersisa);

        $dibatalkan = app(PenyiapanResep::class)
            ->batalkan($resep, User::factory()->create(), 'Pasien menolak obat');

        $this->assertSame(100, (int) $batch->refresh()->jumlah_tersisa);
        $this->assertSame(StatusResep::Dibuat, $dibatalkan->status);
        $this->assertSame(0, (int) $dibatalkan->detail->first()->jumlah_diserahkan);
    }

    public function test_pembatalan_mencatat_mutasi_pengembalian(): void
    {
        [$tagihan, $resep, $batch] = $this->resepSiap('tunai');

        app(PenyiapanResep::class)->batalkan($resep, User::factory()->create(), 'Pasien menolak obat');

        $mutasi = MutasiStok::where('jenis', JenisMutasiStok::Pengembalian)->first();

        $this->assertSame(10, (int) $mutasi->jumlah);
        $this->assertSame(100, (int) $mutasi->stok_sesudah);
    }

    public function test_pembatalan_menghapus_baris_obat_dari_tagihan(): void
    {
        [$tagihan, $resep] = $this->resepSiap('tunai');

        $this->assertSame(20000, (int) $tagihan->refresh()->total);

        app(PenyiapanResep::class)->batalkan($resep, User::factory()->create(), 'Pasien menolak obat');

        $this->assertSame(0, (int) $tagihan->refresh()->total);
        $this->assertSame(0, $tagihan->detail()->whereNotNull('resep_detail_id')->count());
    }

    public function test_pembatalan_wajib_menyertakan_alasan(): void
    {
        [$tagihan, $resep] = $this->resepSiap('tunai');

        $this->expectException(ValidationException::class);

        app(PenyiapanResep::class)->batalkan($resep, User::factory()->create(), '   ');
    }

    public function test_alasan_pembatalan_tercatat_di_audit_log(): void
    {
        [$tagihan, $resep] = $this->resepSiap('tunai');

        app(PenyiapanResep::class)->batalkan($resep, User::factory()->create(), 'Stok salah hitung');

        $this->assertDatabaseHas('audit_logs', ['alasan' => 'Stok salah hitung']);
    }
}
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=PenyerahanObatTest`
Diharapkan: FAIL dengan "Target class [App\Services\PenyerahanObat] does not exist."

- [ ] **Step 3: Tulis PenyerahanObat**

`app/Services/PenyerahanObat.php`:

```php
<?php

namespace App\Services;

use App\Enums\StatusResep;
use App\Enums\StatusTagihan;
use App\Models\Resep;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PenyerahanObat
{
    public function serahkan(Resep $resep, User $apoteker): Resep
    {
        if ($resep->status !== StatusResep::Disiapkan) {
            throw new RuntimeException(
                "Resep {$resep->no_resep} berstatus {$resep->status->label()} dan belum siap diserahkan."
            );
        }

        $kunjungan = $resep->kunjungan;

        // Aturan 30: pasien tunai menunggu lunas; pasien berpenjamin tidak.
        if (! $kunjungan->penjamin->ditanggung()) {
            $tagihan = $kunjungan->tagihan;

            if ($tagihan === null || $tagihan->status !== StatusTagihan::Lunas) {
                throw new RuntimeException(
                    'Obat belum bisa diserahkan: tagihan pasien belum lunas di kasir.'
                );
            }
        }

        return DB::transaction(function () use ($resep, $apoteker) {
            $resep->update([
                'status' => StatusResep::Diserahkan,
                'diserahkan_pada' => now(),
                'diserahkan_oleh' => $apoteker->id,
            ]);

            return $resep->refresh();
        });
    }
}
```

- [ ] **Step 4: Tambahkan method batalkan pada PenyiapanResep**

Di `app/Services/PenyiapanResep.php`, tambahkan import `use App\Support\KonteksAudit;` dan `use Illuminate\Validation\ValidationException;`, lalu method:

```php
    /**
     * Mengembalikan seluruh jumlah ke batch asalnya dan mencabut baris obat dari
     * tagihan (aturan 32). Resep kembali berstatus dibuat sehingga bisa disiapkan ulang.
     */
    public function batalkan(Resep $resep, User $apoteker, string $alasan): Resep
    {
        if (trim($alasan) === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan pembatalan penyiapan wajib diisi.',
            ]);
        }

        if ($resep->status !== StatusResep::Disiapkan) {
            throw new RuntimeException(
                "Hanya resep berstatus disiapkan yang bisa dibatalkan. Resep ini berstatus {$resep->status->label()}."
            );
        }

        return KonteksAudit::dengan(trim($alasan), function () use ($resep, $apoteker) {
            return DB::transaction(function () use ($resep, $apoteker) {
                foreach ($resep->detail as $baris) {
                    foreach ($baris->pengambilan as $pengambilan) {
                        $batch = BatchObat::whereKey($pengambilan->batch_obat_id)->lockForUpdate()->first();
                        $sisa = (int) $batch->jumlah_tersisa + (int) $pengambilan->jumlah;

                        $batch->update(['jumlah_tersisa' => $sisa]);

                        MutasiStok::create([
                            'batch_obat_id' => $batch->id,
                            'obat_id' => $batch->obat_id,
                            'jenis' => JenisMutasiStok::Pengembalian,
                            'jumlah' => (int) $pengambilan->jumlah,
                            'stok_sesudah' => $sisa,
                            'resep_id' => $resep->id,
                            'catatan' => 'Pembatalan penyiapan resep '.$resep->no_resep,
                            'dilakukan_oleh' => $apoteker->id,
                            'created_at' => now(),
                        ]);
                    }

                    $baris->pengambilan()->delete();
                    $baris->update(['jumlah_diserahkan' => 0, 'harga_satuan' => 0]);
                }

                $tagihan = $resep->kunjungan->tagihan;

                if ($tagihan !== null) {
                    $tagihan->detail()->whereNotNull('resep_detail_id')->delete();
                    $this->penyusunTagihan->hitungUlang($tagihan);
                }

                $resep->update([
                    'status' => StatusResep::Dibuat,
                    'disiapkan_pada' => null,
                    'disiapkan_oleh' => null,
                ]);

                return $resep->refresh();
            });
        });
    }
```

- [ ] **Step 5: Jalankan test sampai lulus**

Run: `php artisan test --filter=PenyerahanObatTest`
Diharapkan: PASS, 10 test.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: tambah penyerahan obat dan pembatalan penyiapan yang mengembalikan stok"
```

---
### Task 7: Penyesuaian stok hasil opname

**Files:**
- Create: `app/Services/PenyesuaianStok.php`
- Test: `tests/Feature/PenyesuaianStokTest.php`

**Interfaces:**
- Consumes: `BatchObat`, `MutasiStok` (Task 3), `KonteksAudit` (Fase 1)
- Produces: `PenyesuaianStok::sesuaikan(BatchObat $batch, int $jumlahBaru, string $alasan, User $apoteker): BatchObat`

Memenuhi aturan 33.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/PenyesuaianStokTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\JenisMutasiStok;
use App\Models\BatchObat;
use App\Models\MutasiStok;
use App\Models\User;
use App\Services\PenyesuaianStok;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PenyesuaianStokTest extends TestCase
{
    use RefreshDatabase;

    private function batch(int $tersisa = 50): BatchObat
    {
        return BatchObat::factory()->create([
            'jumlah_awal' => 100, 'jumlah_tersisa' => $tersisa,
            'tanggal_kedaluwarsa' => '2029-01-31',
        ]);
    }

    public function test_penyesuaian_turun_mencatat_selisih_negatif(): void
    {
        $batch = $this->batch(50);

        app(PenyesuaianStok::class)->sesuaikan($batch, 45, 'Selisih hasil opname', User::factory()->create());

        $this->assertSame(45, (int) $batch->refresh()->jumlah_tersisa);

        $mutasi = MutasiStok::where('jenis', JenisMutasiStok::Penyesuaian)->latest('id')->first();

        $this->assertSame(-5, (int) $mutasi->jumlah);
        $this->assertSame(45, (int) $mutasi->stok_sesudah);
    }

    public function test_penyesuaian_naik_mencatat_selisih_positif(): void
    {
        $batch = $this->batch(50);

        app(PenyesuaianStok::class)->sesuaikan($batch, 58, 'Temuan opname', User::factory()->create());

        $mutasi = MutasiStok::where('jenis', JenisMutasiStok::Penyesuaian)->latest('id')->first();

        $this->assertSame(8, (int) $mutasi->jumlah);
        $this->assertSame(58, (int) $mutasi->stok_sesudah);
    }

    public function test_penyesuaian_tanpa_alasan_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        app(PenyesuaianStok::class)->sesuaikan($this->batch(), 40, '  ', User::factory()->create());
    }

    public function test_jumlah_baru_negatif_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        app(PenyesuaianStok::class)->sesuaikan($this->batch(), -1, 'Salah input', User::factory()->create());
    }

    public function test_jumlah_yang_sama_tidak_menghasilkan_mutasi(): void
    {
        $batch = $this->batch(50);

        app(PenyesuaianStok::class)->sesuaikan($batch, 50, 'Cocok dengan fisik', User::factory()->create());

        $this->assertSame(0, MutasiStok::where('jenis', JenisMutasiStok::Penyesuaian)->count());
    }

    public function test_alasan_penyesuaian_tercatat_di_audit_log(): void
    {
        $batch = $this->batch(50);

        app(PenyesuaianStok::class)->sesuaikan($batch, 45, 'Selisih hasil opname', User::factory()->create());

        $this->assertDatabaseHas('audit_logs', ['alasan' => 'Selisih hasil opname']);
    }
}
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=PenyesuaianStokTest`
Diharapkan: FAIL dengan "Target class [App\Services\PenyesuaianStok] does not exist."

- [ ] **Step 3: Tulis PenyesuaianStok**

`app/Services/PenyesuaianStok.php`:

```php
<?php

namespace App\Services;

use App\Enums\JenisMutasiStok;
use App\Models\BatchObat;
use App\Models\MutasiStok;
use App\Models\User;
use App\Support\KonteksAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PenyesuaianStok
{
    public function sesuaikan(BatchObat $batch, int $jumlahBaru, string $alasan, User $apoteker): BatchObat
    {
        if (trim($alasan) === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan penyesuaian stok wajib diisi.',
            ]);
        }

        if ($jumlahBaru < 0) {
            throw ValidationException::withMessages([
                'jumlah_baru' => 'Jumlah stok tidak boleh negatif.',
            ]);
        }

        return KonteksAudit::dengan(trim($alasan), function () use ($batch, $jumlahBaru, $alasan, $apoteker) {
            return DB::transaction(function () use ($batch, $jumlahBaru, $alasan, $apoteker) {
                $terkunci = BatchObat::whereKey($batch->id)->lockForUpdate()->first();
                $selisih = $jumlahBaru - (int) $terkunci->jumlah_tersisa;

                if ($selisih === 0) {
                    return $terkunci;
                }

                $terkunci->update(['jumlah_tersisa' => $jumlahBaru]);

                MutasiStok::create([
                    'batch_obat_id' => $terkunci->id,
                    'obat_id' => $terkunci->obat_id,
                    'jenis' => JenisMutasiStok::Penyesuaian,
                    'jumlah' => $selisih,
                    'stok_sesudah' => $jumlahBaru,
                    'catatan' => trim($alasan),
                    'dilakukan_oleh' => $apoteker->id,
                    'created_at' => now(),
                ]);

                return $terkunci->refresh();
            });
        });
    }
}
```

- [ ] **Step 4: Jalankan test sampai lulus**

Run: `php artisan test --filter=PenyesuaianStokTest`
Diharapkan: PASS, 6 test.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: tambah penyesuaian stok hasil opname yang wajib beralasan"
```

---

### Task 8: Hak akses apoteker

**Files:**
- Create: `app/Policies/ResepPolicy.php`
- Modify: `app/Policies/PemeriksaanPolicy.php` (tidak ada perubahan perilaku, hanya diverifikasi test)
- Test: `tests/Feature/HakAksesApotekTest.php`

**Interfaces:**
- Consumes: `Peran::Apoteker` (Task 1), `Resep` (Fase 1)
- Produces: `ResepPolicy::siapkan(User $user, Resep $resep): bool`, `ResepPolicy::serahkan(User $user, Resep $resep): bool`, `ResepPolicy::kelolaStok(User $user): bool`

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/HakAksesApotekTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Kunjungan;
use App\Models\Pemeriksaan;
use App\Models\Resep;
use App\Models\User;
use App\Services\PenulisanResep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HakAksesApotekTest extends TestCase
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

    private function resep(): Resep
    {
        return app(PenulisanResep::class)->tulis(
            Kunjungan::factory()->create(),
            [['obat_id' => \App\Models\Obat::factory()->create()->id, 'jumlah' => 5, 'aturan_pakai' => '2x1']],
            User::factory()->create()
        );
    }

    public function test_apoteker_boleh_menyiapkan_dan_menyerahkan_resep(): void
    {
        $resep = $this->resep();
        $apoteker = $this->penggunaBerperan(Peran::Apoteker->value);

        $this->assertTrue(Gate::forUser($apoteker)->allows('siapkan', $resep));
        $this->assertTrue(Gate::forUser($apoteker)->allows('serahkan', $resep));
    }

    public function test_dokter_tidak_bisa_menyiapkan_resep(): void
    {
        $resep = $this->resep();
        $dokter = $this->penggunaBerperan(Peran::Dokter->value);

        $this->assertFalse(Gate::forUser($dokter)->allows('siapkan', $resep));
    }

    public function test_kasir_tidak_bisa_menyerahkan_obat(): void
    {
        $resep = $this->resep();
        $kasir = $this->penggunaBerperan(Peran::Kasir->value);

        $this->assertFalse(Gate::forUser($kasir)->allows('serahkan', $resep));
    }

    public function test_apoteker_tidak_bisa_mengubah_rekam_medis(): void
    {
        $pemeriksaan = Pemeriksaan::factory()->create();
        $apoteker = $this->penggunaBerperan(Peran::Apoteker->value);

        $this->assertFalse(Gate::forUser($apoteker)->allows('ubah', $pemeriksaan));
    }

    public function test_apoteker_tidak_bisa_memeriksa_kunjungan(): void
    {
        $kunjungan = Kunjungan::factory()->create();
        $apoteker = $this->penggunaBerperan(Peran::Apoteker->value);

        $this->assertFalse(Gate::forUser($apoteker)->allows('periksa', $kunjungan));
    }

    public function test_apoteker_tidak_bisa_memproses_pembayaran(): void
    {
        $tagihan = \App\Models\Tagihan::factory()->create();
        $apoteker = $this->penggunaBerperan(Peran::Apoteker->value);

        $this->assertFalse(Gate::forUser($apoteker)->allows('proses', $tagihan));
    }

    public function test_hanya_apoteker_yang_boleh_mengelola_stok(): void
    {
        $this->assertTrue(Gate::forUser($this->penggunaBerperan(Peran::Apoteker->value))->allows('kelolaStok', Resep::class));
        $this->assertFalse(Gate::forUser($this->penggunaBerperan(Peran::Perawat->value))->allows('kelolaStok', Resep::class));
    }
}
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=HakAksesApotekTest`
Diharapkan: FAIL — kemampuan `siapkan` belum terdaftar sehingga Gate menolak semuanya, termasuk untuk apoteker.

- [ ] **Step 3: Tulis ResepPolicy**

`app/Policies/ResepPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Enums\StatusResep;
use App\Models\Resep;
use App\Models\User;

class ResepPolicy
{
    public function siapkan(User $user, Resep $resep): bool
    {
        return $user->hasRole(Peran::Apoteker->value) && $resep->status->bisaDisiapkan();
    }

    public function serahkan(User $user, Resep $resep): bool
    {
        return $user->hasRole(Peran::Apoteker->value)
            && in_array($resep->status, [StatusResep::Disiapkan, StatusResep::Dibuat], true);
    }

    public function kelolaStok(User $user): bool
    {
        return $user->hasRole(Peran::Apoteker->value);
    }
}
```

`kelolaStok` sengaja tanpa parameter model kedua supaya bisa dipanggil `Gate::allows('kelolaStok', Resep::class)` dari layar yang tidak punya resep tertentu, seperti penerimaan batch dan kartu stok.

- [ ] **Step 4: Jalankan test sampai lulus**

Run: `php artisan test --filter=HakAksesApotekTest`
Diharapkan: PASS, 7 test.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: tambah hak akses apoteker dan batasannya terhadap layar klinis dan kasir"
```

---

### Task 9: Layar apotek

**Files:**
- Create: `app/Livewire/Apotek/{AntreanResep,LayarPenyiapan,LayarPenyerahan,PenerimaanBatch,KartuStok,PeringatanStok}.php`, `app/Livewire/Master/DaftarHargaObat.php`, view masing-masing di `resources/views/livewire/apotek/` dan `resources/views/livewire/master/`
- Modify: `routes/web.php`
- Test: `tests/Feature/LayarApotekTest.php`

**Interfaces:**
- Consumes: seluruh service Task 2–7, `ResepPolicy` (Task 8)
- Produces: rute `apotek.antrean`, `apotek.siapkan`, `apotek.serahkan`, `apotek.penerimaan`, `apotek.kartu-stok`, `apotek.peringatan` di belakang `role:apoteker`; `master.harga-obat` di belakang `role:admin`.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/LayarApotekTest.php`:

```php
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
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=LayarApotekTest`
Diharapkan: FAIL dengan "Unable to find component: [App\Livewire\Apotek\AntreanResep]".

- [ ] **Step 3: Tulis AntreanResep dan LayarPenyiapan**

`app/Livewire/Apotek/AntreanResep.php`:

```php
<?php

namespace App\Livewire\Apotek;

use App\Enums\StatusResep;
use App\Models\Resep;
use Livewire\Component;
use Livewire\WithPagination;

class AntreanResep extends Component
{
    use WithPagination;

    public string $status = 'dibuat';

    public function render()
    {
        return view('livewire.apotek.antrean-resep', [
            'daftarResep' => Resep::with('kunjungan.pasien', 'kunjungan.penjamin', 'detail.obat')
                ->where('status', $this->status)
                ->latest('id')
                ->paginate(15),
            'pilihanStatus' => StatusResep::cases(),
        ]);
    }
}
```

`app/Livewire/Apotek/LayarPenyiapan.php`:

```php
<?php

namespace App\Livewire\Apotek;

use App\Models\BatchObat;
use App\Models\Resep;
use App\Services\PenyiapanResep;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use RuntimeException;

class LayarPenyiapan extends Component
{
    use AuthorizesRequests;

    public Resep $resep;

    public string $alasanBatal = '';

    public function mount(Resep $resep): void
    {
        $this->authorize('serahkan', $resep);

        $this->resep = $resep;
    }

    public function siapkan(PenyiapanResep $layanan): void
    {
        $this->jalankan(fn () => $layanan->siapkan($this->resep, auth()->user()));
    }

    public function batalkan(PenyiapanResep $layanan): void
    {
        $this->jalankan(fn () => $layanan->batalkan($this->resep, auth()->user(), $this->alasanBatal));
    }

    private function jalankan(callable $aksi): void
    {
        try {
            $aksi();
            $this->resep->refresh();
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kolom => $pesan) {
                $this->addError($kolom, $pesan[0]);
            }
        } catch (RuntimeException $e) {
            // Termasuk StokTidakCukup dan SeluruhBatchKedaluwarsa — keduanya
            // turunan RuntimeException dan pesannya sudah layak tampil apa adanya.
            $this->addError('penyiapan', $e->getMessage());
        }
    }

    public function render()
    {
        $rencana = [];

        foreach ($this->resep->detail as $baris) {
            $rencana[$baris->id] = BatchObat::where('obat_id', $baris->obat_id)
                ->layakPakai()
                ->orderBy('tanggal_kedaluwarsa')
                ->orderBy('id')
                ->get();
        }

        return view('livewire.apotek.layar-penyiapan', ['rencanaBatch' => $rencana]);
    }
}
```

`render()` menampilkan batch calon sumber dalam urutan FEFO supaya apoteker melihat
apa yang akan diambil **sebelum** menekan tombol, bukan sesudahnya.

- [ ] **Step 4: Tulis komponen apotek sisanya**

`app/Livewire/Apotek/LayarPenyerahan.php` memakai `PenyerahanObat::serahkan()` dengan pola `try/catch` yang sama, memetakan `RuntimeException` ke kunci error `penyerahan`.

`app/Livewire/Apotek/PenerimaanBatch.php` menyimpan properti `obat_id`, `no_batch`, `tanggal_kedaluwarsa`, `jumlah`, `harga_beli`, dan memanggil `PenerimaanObat::terima()` di `simpan()`, memetakan `ValidationException` ke kolomnya masing-masing seperti `FormPasien` (Fase 1).

`app/Livewire/Apotek/KartuStok.php` menerima properti `obat_id` dan menampilkan `MutasiStok::with('batch', 'petugas')->where('obat_id', $this->obat_id)->latest('id')->paginate(30)`.

`app/Livewire/Apotek/PeringatanStok.php` menampilkan dua daftar: `Obat::menipis()->get()` dan `BatchObat::where('jumlah_tersisa', '>', 0)->whereDate('tanggal_kedaluwarsa', '<=', now()->addMonths(3))->orderBy('tanggal_kedaluwarsa')->get()`.

`app/Livewire/Master/DaftarHargaObat.php` mengikuti pola `Master\DaftarTarif` (Fase 1) dengan isian `obat_id`, `penjamin_id`, `harga`, `berlaku_mulai`, termasuk pemeriksaan kombinasi ganda sebelum menyimpan.

- [ ] **Step 5: Tulis view**

`resources/views/livewire/apotek/antrean-resep.blade.php` berisi tabel: nomor resep, nama pasien, poli, penjamin, jumlah item, dan tautan ke layar penyiapan.

`layar-penyiapan.blade.php` menampilkan rincian resep, tabel rencana alokasi batch per obat (nomor batch, kedaluwarsa, sisa), pesan `@error('penyiapan')`, tombol Siapkan, serta isian alasan dan tombol Batalkan Penyiapan yang hanya muncul saat status resep `disiapkan`.

`layar-penyerahan.blade.php`, `penerimaan-batch.blade.php`, `kartu-stok.blade.php`, `peringatan-stok.blade.php`, dan `master/daftar-harga-obat.blade.php` mengikuti pola tabel dan form yang sama dengan layar Fase 1.

- [ ] **Step 6: Daftarkan rute**

Di `routes/web.php`, dalam grup `auth`:

```php
Route::middleware('role:apoteker')->group(function () {
    Route::get('/apotek/antrean', AntreanResep::class)->name('apotek.antrean');
    Route::get('/apotek/siapkan/{resep}', LayarPenyiapan::class)->name('apotek.siapkan');
    Route::get('/apotek/serahkan/{resep}', LayarPenyerahan::class)->name('apotek.serahkan');
    Route::get('/apotek/penerimaan', PenerimaanBatch::class)->name('apotek.penerimaan');
    Route::get('/apotek/kartu-stok/{obat}', KartuStok::class)->name('apotek.kartu-stok');
    Route::get('/apotek/peringatan', PeringatanStok::class)->name('apotek.peringatan');
});
```

Tambahkan `Route::get('/master/harga-obat', DaftarHargaObat::class)->name('master.harga-obat');` ke dalam grup `role:admin` yang sudah ada, beserta seluruh `use` statement komponen di bagian atas berkas.

- [ ] **Step 7: Jalankan test sampai lulus**

Run: `php artisan test --filter=LayarApotekTest`
Diharapkan: PASS, 7 test.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: tambah layar apotek untuk antrean, penyiapan, penyerahan, stok, dan harga obat"
```

---

### Task 10: Seeder farmasi dan verifikasi menyeluruh

**Files:**
- Create: `database/seeders/FarmasiSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`, `database/seeders/PenggunaSeeder.php`, `database/seeders/KunjunganDummySeeder.php`, `README.md`
- Test: `tests/Feature/AlurFarmasiTest.php`

**Interfaces:**
- Consumes: seluruh service Task 2–8
- Produces: `php artisan migrate:fresh --seed` menghasilkan apotek siap demo, dan satu test alur menyeluruh yang membuktikan kriteria selesai nomor 2 dan 3.

- [ ] **Step 1: Tulis test alur menyeluruh**

Buat `tests/Feature/AlurFarmasiTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\JenisDiagnosa;
use App\Enums\MetodePembayaran;
use App\Enums\Peran;
use App\Enums\StatusResep;
use App\Enums\StatusTagihan;
use App\Models\BatchObat;
use App\Models\Dokter;
use App\Models\HargaObat;
use App\Models\Icd10;
use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\Pasien;
use App\Models\Penjamin;
use App\Models\Poli;
use App\Models\TarifTindakan;
use App\Models\Tindakan;
use App\Models\User;
use App\Services\PemeriksaanKlinis;
use App\Services\PendaftaranKunjungan;
use App\Services\PenulisanResep;
use App\Services\PenyerahanObat;
use App\Services\PenyiapanResep;
use App\Services\ProsesPembayaran;
use App\Services\TindakanPelayanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AlurFarmasiTest extends TestCase
{
    use RefreshDatabase;

    private Poli $poli;
    private Dokter $dokter;
    private Tindakan $konsultasi;
    private Obat $obat;
    private BatchObat $batch;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Peran::semua() as $peran) {
            Role::findOrCreate($peran);
        }

        $this->poli = Poli::factory()->create(['kode' => 'UMU', 'nama' => 'Poli Umum']);
        $this->dokter = Dokter::factory()->create(['poli_id' => $this->poli->id]);
        $this->konsultasi = Tindakan::factory()->create(['nama' => 'Konsultasi Dokter Umum']);
        $this->obat = Obat::factory()->create(['nama' => 'Amoksisilin 500 mg']);

        $this->batch = BatchObat::factory()->create([
            'obat_id' => $this->obat->id,
            'no_batch' => 'B2026001',
            'tanggal_kedaluwarsa' => '2029-01-31',
            'jumlah_awal' => 100,
            'jumlah_tersisa' => 100,
            'harga_beli' => 1200,
        ]);
    }

    private function penjaminLengkap(string $kode, string $jenis, int $tarif, int $harga): Penjamin
    {
        $penjamin = Penjamin::factory()->create(['kode' => $kode, 'jenis' => $jenis]);

        TarifTindakan::factory()->create([
            'tindakan_id' => $this->konsultasi->id,
            'penjamin_id' => $penjamin->id,
            'tarif' => $tarif,
            'berlaku_mulai' => '2026-01-01',
        ]);

        HargaObat::factory()->create([
            'obat_id' => $this->obat->id,
            'penjamin_id' => $penjamin->id,
            'harga' => $harga,
            'berlaku_mulai' => '2026-01-01',
        ]);

        return $penjamin;
    }

    private function penggunaBerperan(string $peran, array $atribut = []): User
    {
        $user = User::factory()->create($atribut);
        $user->assignRole($peran);

        return $user;
    }

    /** Menjalankan alur poli sampai kunjungan selesai dan resep tertulis. */
    private function sampaiResepTertulis(Penjamin $penjamin, string $nik, ?string $noKartu): Kunjungan
    {
        $pasien = Pasien::factory()->create(['nik' => $nik]);
        $admisi = $this->penggunaBerperan(Peran::Admisi->value);

        $kunjungan = app(PendaftaranKunjungan::class)->daftarkan([
            'pasien_id' => $pasien->id,
            'poli_id' => $this->poli->id,
            'dokter_id' => $this->dokter->id,
            'penjamin_id' => $penjamin->id,
            'no_kartu_penjamin' => $noKartu,
            'tanggal' => now()->toDateString(),
        ], $admisi);

        $perawat = $this->penggunaBerperan(Peran::Perawat->value);
        $dokterUser = $this->penggunaBerperan(Peran::Dokter->value, ['dokter_id' => $this->dokter->id]);
        $klinis = app(PemeriksaanKlinis::class);

        $klinis->catatVital($kunjungan, [
            'sistolik' => 120, 'diastolik' => 80, 'nadi' => 78, 'suhu' => 36.7,
            'respirasi' => 18, 'berat_badan' => 62.5, 'tinggi_badan' => 165,
            'keluhan_awal' => 'Nyeri tenggorokan',
        ], $perawat);

        $klinis->catatSoap($kunjungan, [
            'subjective' => 'Nyeri tenggorokan tiga hari',
            'objective' => 'Tonsil hiperemis',
            'assessment' => 'Tonsilitis akut',
            'plan' => 'Antibiotik',
        ], $dokterUser);

        $klinis->tambahDiagnosa($kunjungan, Icd10::factory()->create()->id, JenisDiagnosa::Primer);
        app(TindakanPelayanan::class)->tambah($kunjungan, $this->konsultasi->id, 1, $dokterUser);

        app(PenulisanResep::class)->tulis($kunjungan, [[
            'obat_id' => $this->obat->id,
            'jumlah' => 10,
            'aturan_pakai' => '3x1 sesudah makan',
        ]], $dokterUser);

        $klinis->selesaikan($kunjungan, $dokterUser);

        return $kunjungan->refresh();
    }

    public function test_alur_lengkap_pasien_umum_dari_resep_sampai_obat_diserahkan(): void
    {
        $umum = $this->penjaminLengkap('UMUM', 'tunai', 50000, 2000);
        $kunjungan = $this->sampaiResepTertulis($umum, '3202011203900001', null);

        // Sebelum apotek bekerja, tagihan hanya berisi tindakan dan kasir terkunci.
        $this->assertSame(50000, (int) $kunjungan->tagihan->total);

        $apoteker = $this->penggunaBerperan(Peran::Apoteker->value);
        app(PenyiapanResep::class)->siapkan($kunjungan->resep, $apoteker);

        $kunjungan->refresh();
        $tagihan = $kunjungan->tagihan->refresh();

        $this->assertSame(70000, (int) $tagihan->total);
        $this->assertSame(70000, (int) $tagihan->ditagihkan_ke_pasien);
        $this->assertSame(90, (int) $this->batch->refresh()->jumlah_tersisa);

        $kasir = $this->penggunaBerperan(Peran::Kasir->value);
        $pembayaran = app(ProsesPembayaran::class)
            ->bayar($tagihan, MetodePembayaran::Tunai, 70000, $kasir);

        $diserahkan = app(PenyerahanObat::class)->serahkan($kunjungan->resep->refresh(), $apoteker);

        $this->assertSame(StatusTagihan::Lunas, $tagihan->refresh()->status);
        $this->assertSame(70000, (int) $pembayaran->nominal);
        $this->assertSame(StatusResep::Diserahkan, $diserahkan->status);
        $this->assertSame(2, $tagihan->detail()->count());
    }

    public function test_alur_lengkap_pasien_bpjs_menerima_obat_tanpa_ke_kasir(): void
    {
        $this->penjaminLengkap('UMUM', 'tunai', 50000, 2000);
        $bpjs = $this->penjaminLengkap('BPJS', 'penjamin', 35000, 1400);

        $kunjungan = $this->sampaiResepTertulis($bpjs, '3202011203900002', '0001234567890');

        $apoteker = $this->penggunaBerperan(Peran::Apoteker->value);
        app(PenyiapanResep::class)->siapkan($kunjungan->resep, $apoteker);

        $diserahkan = app(PenyerahanObat::class)->serahkan($kunjungan->resep->refresh(), $apoteker);
        $tagihan = $kunjungan->tagihan->refresh();

        $this->assertSame(StatusResep::Diserahkan, $diserahkan->status);
        $this->assertSame(49000, (int) $tagihan->total);
        $this->assertSame(49000, (int) $tagihan->ditanggung_penjamin);
        $this->assertSame(0, (int) $tagihan->ditagihkan_ke_pasien);
        $this->assertSame(StatusTagihan::DitanggungPenjamin, $tagihan->status);
        $this->assertSame(0, $tagihan->pembayaran()->count());
    }
}
```

- [ ] **Step 2: Jalankan dan perbaiki sampai lulus**

Run: `php artisan test --filter=AlurFarmasiTest`
Diharapkan: PASS, 2 test. Kegagalan di sini menandakan cacat integrasi antar tugas, bukan fitur yang belum ada — telusuri penyebabnya, jangan longgarkan assertion-nya.

- [ ] **Step 3: Tulis FarmasiSeeder**

`database/seeders/FarmasiSeeder.php` mengisi:

- Harga jual seluruh obat pada kedua penjamin, berlaku sejak `2026-01-01`. Harga umum diacak wajar menurut bentuk sediaan (tablet Rp 500–5.000, sirup Rp 8.000–35.000, salep Rp 15.000–60.000); harga BPJS sekitar 70% harga umum dibulatkan ke ratusan.
- Dua batch per obat dengan tanggal kedaluwarsa berbeda — satu sekitar 8 bulan lagi, satu sekitar 2 tahun lagi — supaya FEFO terlihat bekerja saat didemokan.
- Satu batch **sengaja sudah kedaluwarsa** pada tiga obat pertama, sebagai bahan uji aturan 22. Batch ini dibuat lewat `BatchObat::factory()` langsung, bukan lewat `PenerimaanObat`, karena servicenya memang menolak tanggal kedaluwarsa di masa lalu.
- Lima obat sengaja bersaldo di bawah `stok_minimum` supaya layar peringatan tidak kosong.

- [ ] **Step 4: Tambahkan pengguna apoteker**

Di `database/seeders/PenggunaSeeder.php`, tambahkan satu baris ke array `$daftar`:

```php
            [Peran::Apoteker, 'Apoteker', 'apoteker@rs.test'],
```

- [ ] **Step 5: Sambungkan ke data dummy kunjungan**

Di `database/seeders/KunjunganDummySeeder.php`, setelah `$klinis->selesaikan(...)`, siapkan resep untuk sebagian kunjungan supaya antrean apotek tidak kosong:

- 10 resep dibiarkan berstatus `dibuat` (menunggu apotek)
- 5 resep disiapkan tapi belum diserahkan
- 5 resep pasien BPJS disiapkan dan langsung diserahkan

Karena aturan 29 mengunci kasir sampai resep disiapkan, urutannya wajib: siapkan resep dulu, baru proses pembayaran. Bila terbalik, seeder akan gagal dengan pesan "resep belum disiapkan apotek" — dan itu justru bukti aturannya bekerja.

Tambahkan `FarmasiSeeder::class` ke `DatabaseSeeder` **sebelum** `KunjunganDummySeeder::class`, karena penyiapan resep membutuhkan harga dan stok.

- [ ] **Step 6: Jalankan seluruh alur dari nol**

```bash
php artisan migrate:fresh --seed
```

Periksa hasilnya:

```bash
mysql -u irvan -p1 simrs -e "
SELECT (SELECT COUNT(*) FROM batch_obat) AS batch,
       (SELECT COUNT(*) FROM harga_obat) AS harga,
       (SELECT COUNT(*) FROM mutasi_stok) AS mutasi,
       (SELECT COUNT(*) FROM resep WHERE status='dibuat') AS antre_apotek,
       (SELECT COUNT(*) FROM resep WHERE status='diserahkan') AS sudah_diserahkan,
       (SELECT COUNT(*) FROM tagihan_detail WHERE resep_detail_id IS NOT NULL) AS baris_obat;"
```

Diharapkan: batch ≥ 100, harga ≥ 100, mutasi > 0, antre_apotek ≥ 10, sudah_diserahkan ≥ 5, baris_obat > 0.

- [ ] **Step 7: Jalankan seluruh test**

Run: `php artisan test`
Diharapkan: seluruh berkas test PASS, tanpa satu pun yang di-skip.

- [ ] **Step 8: Telusuri manual**

```bash
php artisan serve
```

1. Masuk sebagai `apoteker@rs.test` (sandi `rahasia123`), buka antrean resep, siapkan satu resep, perhatikan alokasi batch yang ditampilkan sebelum konfirmasi.
2. Masuk sebagai `kasir@rs.test`, pastikan tagihan pasien tersebut kini memuat baris obat, lalu lunasi.
3. Kembali sebagai apoteker, serahkan obatnya.
4. Coba lunasi tagihan pasien yang resepnya belum disiapkan — harus ditolak dengan pesan menyebut nomor resep.
5. Buka kartu stok obat tadi, pastikan mutasi keluarnya tercatat beserta nama apoteker.
6. Buka layar peringatan, pastikan obat menipis dan batch mendekati kedaluwarsa muncul.

- [ ] **Step 9: Perbarui README**

Tambahkan bagian Fase 2 ke tabel cakupan, akun `apoteker@rs.test` ke tabel akun demo, dan catatan singkat tentang alur apotek yang berbeda antara pasien umum dan berpenjamin.

- [ ] **Step 10: Commit dan dorong**

```bash
git add -A
git commit -m "feat: tambah seeder farmasi dan test alur apotek menyeluruh"
git push
```

---

## Ringkasan Cakupan

| Aturan (spec Fase 2 bagian 8) | Tugas |
|---|---|
| 21 Nomor batch dan kedaluwarsa wajib, unik per obat | Task 3 |
| 22 Batch kedaluwarsa tidak dialokasikan | Task 3, 4 |
| 23 Alokasi FEFO, boleh dipecah antar batch | Task 4 |
| 24 Stok tidak boleh negatif | Task 4 |
| 25 Setiap perubahan stok tercatat di kartu stok | Task 3, 4, 6, 7 |
| 26 Harga disalin saat penyiapan | Task 4 |
| 27 Harga menurut penjamin, jatuh tempo ke UMUM | Task 2 |
| 28 Biaya obat masuk tagihan, tagihan lunas tak bisa ditambahi | Task 5 |
| 29 Tagihan terkunci sampai resep disiapkan | Task 5 |
| 30 Obat pasien tunai menunggu lunas | Task 6 |
| 31 Resep terserah tidak bisa disiapkan ulang | Task 4, 6 |
| 32 Pembatalan mengembalikan stok | Task 6 |
| 33 Penyesuaian stok wajib beralasan | Task 7 |
| 34 Peringatan stok menipis | Task 3, 9 |
