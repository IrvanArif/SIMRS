# SIMRS Fase 4 (Radiologi) — Rencana Implementasi

> **Untuk pekerja agentik:** SUB-SKILL WAJIB: gunakan superpowers:subagent-driven-development (disarankan) atau superpowers:executing-plans untuk mengerjakan rencana ini tugas demi tugas. Setiap langkah memakai checkbox (`- [ ]`).

**Goal:** Menambahkan radiologi ke alur rawat jalan — dokter memesan pencitraan, radiografer mengerjakannya, dokter radiologi menulis ekspertise, dan kunjungan baru bisa ditutup setelah bacaannya ada.

**Architecture:** Mencerminkan laboratorium yang sudah berdiri, dengan tiga perbedaan: hasilnya naratif sehingga tidak ada parameter maupun nilai rujukan; pekerjaannya terbagi dua peran karena radiografer tidak berwenang menyimpulkan temuan; dan citranya tidak disimpan sebagai berkas, hanya nomor film dan lokasi arsipnya. Fondasi tarif dan sumber tagihan polimorfik dari Fase 3 dipakai apa adanya — tidak ada tabel harga baru.

**Tech Stack:** PHP 8.5, Laravel 13, Livewire 4, Blade, Tailwind (Vite), MySQL, spatie/laravel-permission, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-08-18-simrs-fase4-radiologi-design.md`

## Global Constraints

- **Bahasa Indonesia** untuk nama tabel, kolom, rute, label UI, pesan validasi, dan nama test (`test_...`).
- **TDD tanpa pengecualian:** test ditulis lebih dulu, dijalankan sampai terbukti GAGAL, baru implementasinya.
- **Nominal uang** berupa bilangan bulat rupiah (`unsignedBigInteger`).
- **Penomoran** lewat `App\Services\PencatatNomor`; `max() + 1` dilarang.
- **Enum PHP** untuk semua kolom berstatus.
- **Model klinis baru wajib didaftarkan** di `AppServiceProvider::modelTerauditkan()`.
- **Tidak boleh ada tabel harga baru.** Radiologi memakai tabel `tarif` dengan `jenis_layanan` baru.
- **Aturan bisnis 1–46 tidak boleh berubah perilakunya.** 298 test yang ada wajib tetap hijau di akhir setiap tugas.
- **Periksa import ganda** setiap kali menyunting berkas yang sudah ada — `grep "^use " berkas | sort | uniq -d` harus kosong. PHP mati dengan pesan yang tidak menyebut penyebabnya bila ini terlewat.
- **Jalankan test lalu baca hasilnya sebelum commit.** Jangan merangkai test dan commit dengan `&&` dalam satu perintah.
- **Commit setiap selesai satu tugas**, pesan berbahasa Indonesia.

## Pola yang Sudah Ada — Baca Sebelum Menulis

| Kebutuhan | Acuan |
|---|---|
| Order berstatus dengan jejak pelaku tiap tahap | `app/Services/PemesananLab.php`, `app/Services/PemeriksaanLaboratorium.php` |
| Penguncian penyelesaian kunjungan | `app/Services/PemeriksaanKlinis.php` (`selesaikan`) |
| Pembebanan biaya ke tagihan | `app/Services/PenyusunTagihan.php` (`susun`) |
| Koreksi beralasan yang berjejak | `app/Services/PemeriksaanLaboratorium.php` (`koreksi`) |
| Policy peran | `app/Policies/OrderLabPolicy.php` |
| Komponen dan layar | `app/Livewire/Lab/*` |
| Test service | `tests/Feature/PemesananLabTest.php` |
| Test alur menyeluruh | `tests/Feature/AlurLabTest.php` |

## Struktur Berkas

| Berkas | Tanggung jawab |
|---|---|
| `app/Enums/StatusOrderRadiologi.php` | `dipesan`, `dikerjakan`, `selesai`, `batal` |
| `app/Models/PemeriksaanRadiologi.php` | Master pemeriksaan dan modalitasnya |
| `app/Models/OrderRadiologi.php`, `OrderRadiologiDetail.php`, `EkspertiseRadiologi.php` | Entitas transaksi |
| `app/Services/PemesananRadiologi.php` | Order, salin tarif, batalkan |
| `app/Services/PelaksanaanRadiologi.php` | Tandai dikerjakan beserta nomor film |
| `app/Services/EkspertiseRadiologi.php` | Tulis dan koreksi bacaan |
| `app/Policies/OrderRadiologiPolicy.php` | Kewenangan radiografer dan dokter |
| `app/Livewire/Radiologi/*.php` | Layar radiografer dan dokter radiologi |

---

### Task 1: Enum, peran radiografer, dan master pemeriksaan

**Files:**
- Create: `app/Enums/StatusOrderRadiologi.php`, migration `create_pemeriksaan_radiologi_table`, `app/Models/PemeriksaanRadiologi.php`, `database/factories/PemeriksaanRadiologiFactory.php`
- Modify: `app/Enums/Peran.php`, `app/Enums/JenisLayanan.php`, `app/Models/Tarif.php`
- Test: `tests/Unit/EnumRadiologiTest.php`, `tests/Feature/MasterRadiologiTest.php`

**Interfaces:**
- Consumes: `JenisLayanan` (Fase 3)
- Produces: `StatusOrderRadiologi` dengan case `Dipesan`, `Dikerjakan`, `Selesai`, `Batal`, method `bisaDikerjakan(): bool` (hanya `Dipesan`), `bisaDiekspertise(): bool` (hanya `Dikerjakan`), dan `selesai(): bool` (`Selesai` atau `Batal`). `Peran::Radiografer` bernilai `'radiografer'`. `JenisLayanan::Radiologi` bernilai `'radiologi'`. Model `PemeriksaanRadiologi`.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Unit/EnumRadiologiTest.php`:

```php
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
    }
}
```

Buat `tests/Feature/MasterRadiologiTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\JenisLayanan;
use App\Models\PemeriksaanRadiologi;
use App\Models\Penjamin;
use App\Models\Tarif;
use App\Services\PencariTarif;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterRadiologiTest extends TestCase
{
    use RefreshDatabase;

    public function test_pemeriksaan_radiologi_punya_modalitas(): void
    {
        $pemeriksaan = PemeriksaanRadiologi::factory()->create([
            'nama' => 'Rontgen Toraks PA', 'modalitas' => 'rontgen',
        ]);

        $this->assertSame('rontgen', $pemeriksaan->modalitas);
        $this->assertTrue($pemeriksaan->aktif);
    }

    public function test_kode_pemeriksaan_ganda_ditolak_database(): void
    {
        PemeriksaanRadiologi::factory()->create(['kode' => 'RAD001']);

        $this->expectException(QueryException::class);

        PemeriksaanRadiologi::factory()->create(['kode' => 'RAD001']);
    }

    public function test_tarif_radiologi_memakai_tabel_tarif_yang_sama(): void
    {
        $pemeriksaan = PemeriksaanRadiologi::factory()->create();
        $umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Radiologi,
            'layanan_id' => $pemeriksaan->id,
            'penjamin_id' => $umum->id,
            'harga' => 150000,
            'berlaku_mulai' => '2026-01-01',
        ]);

        $this->assertSame(
            150000,
            app(PencariTarif::class)->untuk(JenisLayanan::Radiologi, $pemeriksaan->id, $umum->id)
        );
    }

    public function test_nama_layanan_pada_tarif_menampilkan_nama_pemeriksaan(): void
    {
        $pemeriksaan = PemeriksaanRadiologi::factory()->create(['nama' => 'USG Abdomen']);
        $umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);

        $tarif = Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Radiologi,
            'layanan_id' => $pemeriksaan->id,
            'penjamin_id' => $umum->id,
            'harga' => 200000,
            'berlaku_mulai' => '2026-01-01',
        ]);

        $this->assertSame('USG Abdomen', $tarif->namaLayanan());
    }
}
```

- [ ] **Step 2: Jalankan kedua test untuk memastikan gagal**

Run: `php artisan test --filter="EnumRadiologiTest|MasterRadiologiTest"`
Diharapkan: FAIL dengan "Class App\Enums\StatusOrderRadiologi not found".

- [ ] **Step 3: Tulis enum**

`app/Enums/StatusOrderRadiologi.php`:

```php
<?php

namespace App\Enums;

enum StatusOrderRadiologi: string
{
    case Dipesan = 'dipesan';
    case Dikerjakan = 'dikerjakan';
    case Selesai = 'selesai';
    case Batal = 'batal';

    public function bisaDikerjakan(): bool
    {
        return $this === self::Dipesan;
    }

    /**
     * Ekspertise ditulis setelah pencitraannya benar-benar dikerjakan (aturan 52).
     */
    public function bisaDiekspertise(): bool
    {
        return $this === self::Dikerjakan;
    }

    /**
     * Order yang sudah selesai tidak lagi menahan penyelesaian kunjungan (aturan 50).
     */
    public function selesai(): bool
    {
        return in_array($this, [self::Selesai, self::Batal], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Dipesan => 'Menunggu Dikerjakan',
            self::Dikerjakan => 'Menunggu Ekspertise',
            self::Selesai => 'Selesai',
            self::Batal => 'Batal',
        };
    }
}
```

- [ ] **Step 4: Tambahkan peran dan jenis layanan**

Di `app/Enums/Peran.php`, sisipkan setelah `Analis`:

```php
    case Radiografer = 'radiografer';
```

Di `app/Enums/JenisLayanan.php`, tambahkan case dan cabang labelnya:

```php
    case Radiologi = 'radiologi';
```

```php
            self::Radiologi => 'Pemeriksaan Radiologi',
```

Di `app/Models/Tarif.php` pada `namaLayanan()`, tambahkan cabang:

```php
            JenisLayanan::Radiologi => PemeriksaanRadiologi::find($this->layanan_id)?->nama ?? '—',
```

- [ ] **Step 5: Tulis migration, model, dan factory**

```bash
php artisan make:migration create_pemeriksaan_radiologi_table
```

```php
Schema::create('pemeriksaan_radiologi', function (Blueprint $table) {
    $table->id();
    $table->string('kode', 20)->unique();
    $table->string('nama', 150);
    $table->enum('modalitas', ['rontgen', 'usg', 'ct_scan', 'mri', 'mammografi']);
    $table->string('persiapan', 255)->nullable();
    $table->boolean('aktif')->default(true);
    $table->timestamps();
});
```

`app/Models/PemeriksaanRadiologi.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PemeriksaanRadiologi extends Model
{
    use HasFactory;

    protected $table = 'pemeriksaan_radiologi';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }
}
```

`database/factories/PemeriksaanRadiologiFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\PemeriksaanRadiologi;
use Illuminate\Database\Eloquent\Factories\Factory;

class PemeriksaanRadiologiFactory extends Factory
{
    protected $model = PemeriksaanRadiologi::class;

    public function definition(): array
    {
        return [
            'kode' => strtoupper($this->faker->unique()->bothify('RAD###')),
            'nama' => 'Pemeriksaan '.$this->faker->unique()->word(),
            'modalitas' => 'rontgen',
            'persiapan' => null,
            'aktif' => true,
        ];
    }
}
```

- [ ] **Step 6: Jalankan test sampai lulus, lalu seluruh suite**

Run: `php artisan test --filter="EnumRadiologiTest|MasterRadiologiTest"` → PASS, 9 test.
Run: `php artisan test` → seluruhnya hijau.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: tambah enum status radiologi, peran radiografer, dan master pemeriksaan"
```

---

### Task 2: Pemesanan radiologi

**Files:**
- Create: migration `create_order_radiologi_tables`, `app/Models/OrderRadiologi.php`, `app/Models/OrderRadiologiDetail.php`, `app/Services/PemesananRadiologi.php`
- Modify: `app/Models/Kunjungan.php`, `app/Services/NomorDokumen.php`, `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/PemesananRadiologiTest.php`

**Interfaces:**
- Consumes: `PencariTarif`, `NomorDokumen`, `StatusOrderRadiologi` (Task 1)
- Produces:
  - `PemesananRadiologi::pesan(Kunjungan $kunjungan, array $pemeriksaanId, User $dokter, string $indikasiKlinis): OrderRadiologi`
  - `PemesananRadiologi::batalkan(OrderRadiologi $order, User $petugas, string $alasan): OrderRadiologi`
  - Model `OrderRadiologi` (scope `belumSelesai()`, method `terbacaDokter()`), `OrderRadiologiDetail`.
  - `NomorDokumen` menerima jenis `'radiologi'` berawalan `RD`.
  - `Kunjungan::orderRadiologi(): HasMany`.

Memenuhi aturan 47, 48, 49, dan 58.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/PemesananRadiologiTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\JenisLayanan;
use App\Enums\StatusKunjungan;
use App\Enums\StatusOrderRadiologi;
use App\Models\Kunjungan;
use App\Models\PemeriksaanRadiologi;
use App\Models\Penjamin;
use App\Models\Tarif;
use App\Models\User;
use App\Services\PemesananRadiologi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class PemesananRadiologiTest extends TestCase
{
    use RefreshDatabase;

    private Penjamin $umum;
    private PemeriksaanRadiologi $toraks;
    private Kunjungan $kunjungan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $this->toraks = PemeriksaanRadiologi::factory()->create([
            'nama' => 'Rontgen Toraks PA', 'modalitas' => 'rontgen',
        ]);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Radiologi,
            'layanan_id' => $this->toraks->id,
            'penjamin_id' => $this->umum->id,
            'harga' => 150000,
            'berlaku_mulai' => '2026-01-01',
        ]);

        $this->kunjungan = Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]);
    }

    private function dokter(): User
    {
        return User::factory()->create();
    }

    public function test_order_bernomor_dan_berstatus_dipesan(): void
    {
        $order = app(PemesananRadiologi::class)
            ->pesan($this->kunjungan, [$this->toraks->id], $this->dokter(), 'Batuk kronis');

        $this->assertStringStartsWith('RD-', $order->no_order);
        $this->assertSame(StatusOrderRadiologi::Dipesan, $order->status);
        $this->assertSame('Batuk kronis', $order->indikasi_klinis);
        $this->assertSame(1, $order->detail()->count());
    }

    public function test_order_wajib_memuat_minimal_satu_pemeriksaan(): void
    {
        $this->expectException(ValidationException::class);

        app(PemesananRadiologi::class)->pesan($this->kunjungan, [], $this->dokter(), 'Batuk kronis');
    }

    public function test_order_wajib_menyertakan_indikasi_klinis(): void
    {
        // Pencitraan tanpa indikasi berarti pasien menerima radiasi tanpa alasan
        // yang tercatat.
        $this->expectException(ValidationException::class);

        app(PemesananRadiologi::class)->pesan($this->kunjungan, [$this->toraks->id], $this->dokter(), '   ');
    }

    public function test_pemeriksaan_yang_sama_tidak_boleh_dipesan_dua_kali(): void
    {
        $this->expectException(ValidationException::class);

        app(PemesananRadiologi::class)->pesan(
            $this->kunjungan, [$this->toraks->id, $this->toraks->id], $this->dokter(), 'Batuk kronis'
        );
    }

    public function test_tarif_disalin_saat_order_dibuat(): void
    {
        $order = app(PemesananRadiologi::class)
            ->pesan($this->kunjungan, [$this->toraks->id], $this->dokter(), 'Batuk kronis');

        $this->assertSame(150000, (int) $order->detail->first()->tarif_satuan);
    }

    public function test_perubahan_master_tarif_tidak_mengubah_order_yang_sudah_dibuat(): void
    {
        $order = app(PemesananRadiologi::class)
            ->pesan($this->kunjungan, [$this->toraks->id], $this->dokter(), 'Batuk kronis');

        Tarif::query()->update(['harga' => 250000]);

        $this->assertSame(150000, (int) $order->detail->first()->refresh()->tarif_satuan);
    }

    public function test_order_tidak_bisa_dibuat_pada_kunjungan_yang_sudah_selesai(): void
    {
        $selesai = Kunjungan::factory()->create([
            'penjamin_id' => $this->umum->id, 'status' => StatusKunjungan::Selesai,
        ]);

        $this->expectException(RuntimeException::class);

        app(PemesananRadiologi::class)->pesan($selesai, [$this->toraks->id], $this->dokter(), 'Batuk kronis');
    }

    public function test_pembatalan_wajib_menyertakan_alasan(): void
    {
        $order = app(PemesananRadiologi::class)
            ->pesan($this->kunjungan, [$this->toraks->id], $this->dokter(), 'Batuk kronis');

        $this->expectException(ValidationException::class);

        app(PemesananRadiologi::class)->batalkan($order, $this->dokter(), '   ');
    }

    public function test_alasan_pembatalan_tercatat_di_audit_log(): void
    {
        $order = app(PemesananRadiologi::class)
            ->pesan($this->kunjungan, [$this->toraks->id], $this->dokter(), 'Batuk kronis');

        app(PemesananRadiologi::class)->batalkan($order, $this->dokter(), 'Pasien hamil, ditunda');

        $this->assertSame(StatusOrderRadiologi::Batal, $order->refresh()->status);
        $this->assertDatabaseHas('audit_logs', ['alasan' => 'Pasien hamil, ditunda']);
    }

    public function test_order_yang_sudah_batal_tidak_bisa_dibatalkan_lagi(): void
    {
        $order = app(PemesananRadiologi::class)
            ->pesan($this->kunjungan, [$this->toraks->id], $this->dokter(), 'Batuk kronis');

        app(PemesananRadiologi::class)->batalkan($order, $this->dokter(), 'Salah pesan');

        $this->expectException(RuntimeException::class);

        app(PemesananRadiologi::class)->batalkan($order->refresh(), $this->dokter(), 'Sekali lagi');
    }
}
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=PemesananRadiologiTest`
Diharapkan: FAIL dengan "Target class [App\Services\PemesananRadiologi] does not exist."

- [ ] **Step 3: Tulis migration**

```bash
php artisan make:migration create_order_radiologi_tables
```

```php
Schema::create('order_radiologi', function (Blueprint $table) {
    $table->id();
    $table->string('no_order', 20)->unique();
    $table->foreignId('kunjungan_id')->constrained('kunjungan')->cascadeOnDelete();
    $table->foreignId('dokter_id')->nullable()->constrained('users')->nullOnDelete();
    $table->string('status', 20)->default('dipesan');
    $table->string('indikasi_klinis', 255);
    $table->timestamp('waktu_dikerjakan')->nullable();
    $table->foreignId('dikerjakan_oleh')->nullable()->constrained('users')->nullOnDelete();
    $table->string('no_film', 50)->nullable();
    $table->timestamp('waktu_ekspertise')->nullable();
    $table->foreignId('ditulis_oleh')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->index(['kunjungan_id', 'status']);
});

Schema::create('order_radiologi_detail', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_radiologi_id')->constrained('order_radiologi')->cascadeOnDelete();
    $table->foreignId('pemeriksaan_radiologi_id')->constrained('pemeriksaan_radiologi');
    $table->unsignedBigInteger('tarif_satuan');
    $table->timestamps();
    $table->unique(['order_radiologi_id', 'pemeriksaan_radiologi_id'], 'order_radiologi_detail_unik');
});
```

- [ ] **Step 4: Tulis model**

`app/Models/OrderRadiologi.php`:

```php
<?php

namespace App\Models;

use App\Enums\StatusOrderRadiologi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderRadiologi extends Model
{
    use HasFactory;

    protected $table = 'order_radiologi';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => StatusOrderRadiologi::class,
            'waktu_dikerjakan' => 'datetime',
            'waktu_ekspertise' => 'datetime',
        ];
    }

    public function kunjungan(): BelongsTo
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function detail(): HasMany
    {
        return $this->hasMany(OrderRadiologiDetail::class, 'order_radiologi_id');
    }

    /**
     * Order yang masih menahan penyelesaian kunjungan (aturan 50).
     */
    public function scopeBelumSelesai(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            StatusOrderRadiologi::Selesai->value,
            StatusOrderRadiologi::Batal->value,
        ]);
    }

    /**
     * Aturan 55: hasil terbaca dokter pengirim hanya setelah ekspertise ditulis.
     */
    public function terbacaDokter(): bool
    {
        return $this->status === StatusOrderRadiologi::Selesai;
    }

    public function totalTarif(): int
    {
        return (int) $this->detail()->sum('tarif_satuan');
    }
}
```

`app/Models/OrderRadiologiDetail.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderRadiologiDetail extends Model
{
    use HasFactory;

    protected $table = 'order_radiologi_detail';

    protected $guarded = [];

    public function order(): BelongsTo
    {
        return $this->belongsTo(OrderRadiologi::class, 'order_radiologi_id');
    }

    public function pemeriksaan(): BelongsTo
    {
        return $this->belongsTo(PemeriksaanRadiologi::class, 'pemeriksaan_radiologi_id');
    }

    public function ekspertise(): HasOne
    {
        return $this->hasOne(EkspertiseRadiologi::class, 'order_radiologi_detail_id');
    }
}
```

`EkspertiseRadiologi` dibuat di Task 3; relasi ini belum dipanggil test mana pun sampai saat itu.

- [ ] **Step 5: Tulis PemesananRadiologi**

`app/Services/PemesananRadiologi.php`:

```php
<?php

namespace App\Services;

use App\Enums\JenisLayanan;
use App\Enums\StatusOrderRadiologi;
use App\Models\Kunjungan;
use App\Models\OrderRadiologi;
use App\Models\User;
use App\Support\KonteksAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PemesananRadiologi
{
    public function __construct(
        private readonly NomorDokumen $nomorDokumen,
        private readonly PencariTarif $pencariTarif,
    ) {}

    /**
     * @param  list<int>  $pemeriksaanId
     */
    public function pesan(
        Kunjungan $kunjungan,
        array $pemeriksaanId,
        User $dokter,
        string $indikasiKlinis
    ): OrderRadiologi {
        if (! $kunjungan->status->aktif()) {
            throw new RuntimeException(
                'Pemeriksaan radiologi tidak bisa dipesan pada kunjungan yang sudah selesai atau dibatalkan.'
            );
        }

        Validator::make([
            'pemeriksaan' => $pemeriksaanId,
            'indikasi_klinis' => trim($indikasiKlinis),
        ], [
            'pemeriksaan' => ['required', 'array', 'min:1'],
            'pemeriksaan.*' => ['required', 'exists:pemeriksaan_radiologi,id'],
            // Aturan 48: pencitraan tanpa indikasi berarti pasien menerima radiasi
            // tanpa alasan yang tercatat.
            'indikasi_klinis' => ['required', 'string', 'max:255'],
        ], [
            'pemeriksaan.required' => 'Order radiologi harus memuat minimal satu pemeriksaan.',
            'pemeriksaan.min' => 'Order radiologi harus memuat minimal satu pemeriksaan.',
            'indikasi_klinis.required' => 'Indikasi klinis wajib diisi.',
        ])->validate();

        if (count($pemeriksaanId) !== count(array_unique($pemeriksaanId))) {
            throw ValidationException::withMessages([
                'pemeriksaan' => 'Satu pemeriksaan hanya boleh muncul sekali dalam satu order.',
            ]);
        }

        return DB::transaction(function () use ($kunjungan, $pemeriksaanId, $dokter, $indikasiKlinis) {
            $order = OrderRadiologi::create([
                'no_order' => $this->nomorDokumen->berikutnya('radiologi', $kunjungan->tanggal),
                'kunjungan_id' => $kunjungan->id,
                'dokter_id' => $dokter->id,
                'status' => StatusOrderRadiologi::Dipesan,
                'indikasi_klinis' => trim($indikasiKlinis),
            ]);

            foreach ($pemeriksaanId as $id) {
                $order->detail()->create([
                    'pemeriksaan_radiologi_id' => $id,
                    'tarif_satuan' => $this->pencariTarif->untuk(
                        JenisLayanan::Radiologi, (int) $id, $kunjungan->penjamin_id, $kunjungan->tanggal
                    ),
                ]);
            }

            return $order->refresh()->load('detail');
        });
    }

    public function batalkan(OrderRadiologi $order, User $petugas, string $alasan): OrderRadiologi
    {
        if (trim($alasan) === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan pembatalan order radiologi wajib diisi.',
            ]);
        }

        if ($order->status->selesai()) {
            throw new RuntimeException(
                "Order {$order->no_order} berstatus {$order->status->label()} dan tidak bisa dibatalkan."
            );
        }

        return KonteksAudit::dengan(trim($alasan), function () use ($order) {
            $order->update(['status' => StatusOrderRadiologi::Batal]);

            return $order->refresh();
        });
    }
}
```

- [ ] **Step 6: Sambungkan ke berkas yang sudah ada**

Di `app/Services/NomorDokumen.php`, tambahkan pada konstanta `AWALAN`:

```php
        'radiologi' => 'RD',
```

Di `app/Models/Kunjungan.php`, tambahkan:

```php
    public function orderRadiologi(): HasMany
    {
        return $this->hasMany(OrderRadiologi::class);
    }
```

Di `app/Providers/AppServiceProvider.php`, tambahkan `OrderRadiologi::class` ke `modelTerauditkan()`.

Periksa import ganda pada ketiga berkas: `grep "^use " berkas | sort | uniq -d` harus kosong.

- [ ] **Step 7: Jalankan test sampai lulus, lalu seluruh suite**

Run: `php artisan test --filter=PemesananRadiologiTest` → PASS, 10 test.
Run: `php artisan test` → seluruhnya hijau.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: tambah pemesanan radiologi beserta indikasi klinis wajib"
```

---
### Task 3: Pelaksanaan pencitraan dan penulisan ekspertise

**Files:**
- Create: migration `create_ekspertise_radiologi_table`, `app/Models/EkspertiseRadiologi.php`, `app/Services/PelaksanaanRadiologi.php`, `app/Services/PenulisanEkspertise.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/EkspertiseRadiologiTest.php`

**Interfaces:**
- Consumes: `OrderRadiologi` (Task 2)
- Produces:
  - `PelaksanaanRadiologi::kerjakan(OrderRadiologi $order, string $noFilm, User $radiografer): OrderRadiologi`
  - `PenulisanEkspertise::tulis(OrderRadiologi $order, array $bacaan, User $dokter): OrderRadiologi` — `$bacaan` berbentuk `[order_radiologi_detail_id => ['temuan' => ..., 'kesan' => ..., 'saran' => ...]]`
  - `PenulisanEkspertise::koreksi(OrderRadiologi $order, array $bacaan, User $dokter, string $alasan): OrderRadiologi`
  - Model `EkspertiseRadiologi`.

Kelasnya dinamai `PenulisanEkspertise`, bukan `EkspertiseRadiologi`, supaya tidak
bertabrakan dengan nama modelnya.

Memenuhi aturan 51, 52, 53, dan 56.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/EkspertiseRadiologiTest.php`:

```php
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
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=EkspertiseRadiologiTest`
Diharapkan: FAIL dengan "Target class [App\Services\PelaksanaanRadiologi] does not exist."

- [ ] **Step 3: Tulis migration dan model**

```bash
php artisan make:migration create_ekspertise_radiologi_table
```

```php
Schema::create('ekspertise_radiologi', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_radiologi_detail_id')->unique()
        ->constrained('order_radiologi_detail')->cascadeOnDelete();
    $table->text('temuan');
    $table->text('kesan');
    $table->text('saran')->nullable();
    $table->timestamps();
});
```

`app/Models/EkspertiseRadiologi.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EkspertiseRadiologi extends Model
{
    use HasFactory;

    protected $table = 'ekspertise_radiologi';

    protected $guarded = [];

    public function orderDetail(): BelongsTo
    {
        return $this->belongsTo(OrderRadiologiDetail::class, 'order_radiologi_detail_id');
    }
}
```

- [ ] **Step 4: Tulis PelaksanaanRadiologi**

`app/Services/PelaksanaanRadiologi.php`:

```php
<?php

namespace App\Services;

use App\Enums\StatusOrderRadiologi;
use App\Models\OrderRadiologi;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PelaksanaanRadiologi
{
    public function kerjakan(OrderRadiologi $order, string $noFilm, User $radiografer): OrderRadiologi
    {
        if (trim($noFilm) === '') {
            // Aturan 51: tanpa nomor film, citra yang sudah diambil tidak bisa
            // ditemukan lagi di arsip.
            throw ValidationException::withMessages([
                'no_film' => 'Nomor film wajib diisi.',
            ]);
        }

        if (! $order->status->bisaDikerjakan()) {
            throw new RuntimeException(
                "Order {$order->no_order} berstatus {$order->status->label()} dan tidak bisa dikerjakan."
            );
        }

        return DB::transaction(function () use ($order, $noFilm, $radiografer) {
            $terkunci = OrderRadiologi::whereKey($order->id)->lockForUpdate()->first();

            if (! $terkunci->status->bisaDikerjakan()) {
                throw new RuntimeException('Order ini baru saja dikerjakan petugas lain.');
            }

            $terkunci->update([
                'status' => StatusOrderRadiologi::Dikerjakan,
                'no_film' => trim($noFilm),
                'waktu_dikerjakan' => now(),
                'dikerjakan_oleh' => $radiografer->id,
            ]);

            return $terkunci->refresh();
        });
    }
}
```

- [ ] **Step 5: Tulis PenulisanEkspertise**

`app/Services/PenulisanEkspertise.php`:

```php
<?php

namespace App\Services;

use App\Enums\StatusOrderRadiologi;
use App\Models\EkspertiseRadiologi;
use App\Models\OrderRadiologi;
use App\Models\User;
use App\Support\KonteksAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PenulisanEkspertise
{
    /**
     * @param  array<int, array{temuan?: ?string, kesan?: ?string, saran?: ?string}>  $bacaan
     */
    public function tulis(OrderRadiologi $order, array $bacaan, User $dokter): OrderRadiologi
    {
        if (! $order->status->bisaDiekspertise()) {
            throw new RuntimeException(
                "Ekspertise belum bisa ditulis: order {$order->no_order} berstatus {$order->status->label()}."
            );
        }

        $tervalidasi = $this->validasiBacaan($bacaan);

        return DB::transaction(function () use ($order, $tervalidasi, $dokter) {
            $this->simpan($order, $tervalidasi);

            $order->update([
                'status' => StatusOrderRadiologi::Selesai,
                'waktu_ekspertise' => now(),
                'ditulis_oleh' => $dokter->id,
            ]);

            return $order->refresh();
        });
    }

    /**
     * Mengubah bacaan yang sudah ditulis. Wajib beralasan dan berjejak (aturan 56).
     *
     * @param  array<int, array{temuan?: ?string, kesan?: ?string, saran?: ?string}>  $bacaan
     */
    public function koreksi(OrderRadiologi $order, array $bacaan, User $dokter, string $alasan): OrderRadiologi
    {
        if (trim($alasan) === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan koreksi ekspertise wajib diisi.',
            ]);
        }

        if ($order->status !== StatusOrderRadiologi::Selesai) {
            throw new RuntimeException(
                'Koreksi hanya berlaku untuk ekspertise yang sudah ditulis. Yang belum ditulis cukup ditulis biasa.'
            );
        }

        $tervalidasi = $this->validasiBacaan($bacaan);

        return KonteksAudit::dengan(trim($alasan), function () use ($order, $tervalidasi, $dokter) {
            return DB::transaction(function () use ($order, $tervalidasi, $dokter) {
                $this->simpan($order, $tervalidasi);

                $order->update([
                    'waktu_ekspertise' => now(),
                    'ditulis_oleh' => $dokter->id,
                ]);

                return $order->refresh();
            });
        });
    }

    /**
     * @param  array<int, array<string, ?string>>  $tervalidasi
     */
    private function simpan(OrderRadiologi $order, array $tervalidasi): void
    {
        foreach ($tervalidasi as $detailId => $isi) {
            // firstOrFail memastikan detail yang dituju memang milik order ini,
            // sehingga bacaan tidak bisa nyasar ke order pasien lain.
            $detail = $order->detail()->whereKey($detailId)->firstOrFail();

            EkspertiseRadiologi::updateOrCreate(
                ['order_radiologi_detail_id' => $detail->id],
                [
                    'temuan' => $isi['temuan'],
                    'kesan' => $isi['kesan'],
                    'saran' => $isi['saran'],
                ]
            );
        }
    }

    /**
     * @param  array<int, array<string, ?string>>  $bacaan
     * @return array<int, array<string, ?string>>
     */
    private function validasiBacaan(array $bacaan): array
    {
        // Seluruh bacaan divalidasi sebelum satu baris pun ditulis, sehingga satu
        // isian kosong tidak menyisakan separuh ekspertise tersimpan.
        Validator::make(['bacaan' => $bacaan], [
            'bacaan' => ['required', 'array', 'min:1'],
            'bacaan.*.temuan' => ['required', 'string'],
            'bacaan.*.kesan' => ['required', 'string'],
            'bacaan.*.saran' => ['nullable', 'string'],
        ], [
            'bacaan.required' => 'Ekspertise harus memuat minimal satu bacaan.',
            'bacaan.*.temuan.required' => 'Temuan wajib diisi.',
            'bacaan.*.kesan.required' => 'Kesan wajib diisi.',
        ])->validate();

        $hasil = [];

        foreach ($bacaan as $detailId => $isi) {
            $hasil[(int) $detailId] = [
                'temuan' => trim((string) $isi['temuan']),
                'kesan' => trim((string) $isi['kesan']),
                'saran' => isset($isi['saran']) && trim((string) $isi['saran']) !== ''
                    ? trim((string) $isi['saran'])
                    : null,
            ];
        }

        return $hasil;
    }
}
```

- [ ] **Step 6: Daftarkan EkspertiseRadiologi ke audit**

Di `app/Providers/AppServiceProvider.php`, tambahkan `EkspertiseRadiologi::class` ke
`modelTerauditkan()`. Yang penting berjejak saat koreksi adalah isi bacaannya, bukan
cap waktu pada ordernya — pelajaran dari Fase 3, ketika mengandalkan jejak pada order
menghasilkan nol catatan karena updatenya kadang tidak mengubah apa pun.

Periksa import ganda: `grep "^use " app/Providers/AppServiceProvider.php | sort | uniq -d` harus kosong.

- [ ] **Step 7: Jalankan test sampai lulus, lalu seluruh suite**

Run: `php artisan test --filter=EkspertiseRadiologiTest` → PASS, 12 test.
Run: `php artisan test` → seluruhnya hijau.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: tambah pelaksanaan pencitraan dan penulisan ekspertise radiologi"
```

---

### Task 4: Penguncian kunjungan, pembebanan tagihan, dan hak akses

**Files:**
- Create: `app/Policies/OrderRadiologiPolicy.php`
- Modify: `app/Services/PemeriksaanKlinis.php`, `app/Services/PenyusunTagihan.php`
- Test: `tests/Feature/TagihanRadiologiTest.php`, `tests/Feature/HakAksesRadiologiTest.php`

**Interfaces:**
- Consumes: `PenulisanEkspertise` (Task 3)
- Produces: `PemeriksaanKlinis::selesaikan()` menolak selama ada order radiologi belum selesai; `PenyusunTagihan::susun()` ikut memasukkan baris radiologi; `OrderRadiologiPolicy` dengan `pesan`, `kerjakan`, `ekspertise`, `baca`.

Memenuhi aturan 50, 54, 55, dan 57.

- [ ] **Step 1: Tulis test tagihan yang gagal**

Buat `tests/Feature/TagihanRadiologiTest.php` dengan lima test, disusun memakai
`tests/Feature/TagihanLabTest.php` sebagai kerangka — ganti pemeriksaan lab dengan
radiologi, dan tahap sampel/entri/validasi dengan kerjakan/ekspertise:

```php
    public function test_biaya_radiologi_masuk_ke_tagihan_saat_kunjungan_diselesaikan(): void
    // konsultasi 50.000 + rontgen 150.000 = 200.000; baris radiologi bersumber
    // OrderRadiologiDetail

    public function test_rincian_tagihan_memuat_tindakan_dan_radiologi_sebagai_sumber_berbeda(): void
    // groupBy sumber_tipe mengembalikan dua baris dengan nilai masing-masing

    public function test_order_yang_dibatalkan_sebelum_dikerjakan_tidak_ditagihkan(): void
    // total tetap 50.000

    public function test_order_yang_dibatalkan_setelah_dikerjakan_tetap_ditagihkan(): void
    // total 200.000 — film dan waktu alatnya sudah terpakai (aturan 57)

    public function test_kunjungan_tidak_bisa_diselesaikan_saat_ekspertise_belum_ada(): void
    // RuntimeException memuat nomor order
```

Tulis kelimanya utuh; komentar di atas hanya penanda maksud, bukan pengganti kode.

- [ ] **Step 2: Tulis test hak akses yang gagal**

Buat `tests/Feature/HakAksesRadiologiTest.php` memakai pola
`tests/Feature/HakAksesLabTest.php`:

```php
    public function test_radiografer_boleh_mengerjakan_pencitraan(): void
    public function test_radiografer_tidak_bisa_menulis_ekspertise(): void
    public function test_dokter_boleh_menulis_ekspertise(): void
    public function test_hanya_dokter_yang_boleh_memesan_radiologi(): void
    public function test_radiografer_tidak_bisa_mengubah_rekam_medis(): void
    public function test_radiografer_tidak_bisa_menyiapkan_resep(): void
    public function test_radiografer_tidak_bisa_mengerjakan_order_lab(): void
    public function test_radiografer_tidak_bisa_memproses_pembayaran(): void
    public function test_hasil_belum_diekspertise_tidak_boleh_dibaca_dokter(): void
```

Tulis kesembilannya utuh.

- [ ] **Step 3: Jalankan kedua test untuk memastikan gagal**

Run: `php artisan test --filter="TagihanRadiologiTest|HakAksesRadiologiTest"`
Diharapkan: FAIL.

- [ ] **Step 4: Kunci penyelesaian kunjungan**

Di `app/Services/PemeriksaanKlinis.php` pada `selesaikan()`, tepat setelah penjaga
order laboratorium, tambahkan:

```php
        $radiologiMenunggu = $kunjungan->orderRadiologi()->belumSelesai()->first();

        if ($radiologiMenunggu !== null) {
            throw new RuntimeException(
                "Kunjungan belum bisa diselesaikan: ekspertise order {$radiologiMenunggu->no_order} belum ditulis."
            );
        }
```

- [ ] **Step 5: Masukkan baris radiologi ke tagihan**

Di `app/Services/PenyusunTagihan.php` pada `susun()`, setelah perulangan order
laboratorium, tambahkan blok serupa untuk radiologi:

```php
            // Aturan 57: yang dibatalkan sebelum dikerjakan tidak ditagihkan;
            // yang dibatalkan setelah dikerjakan tetap ditagihkan karena film dan
            // waktu alatnya sudah terpakai.
            $orderRadiologi = $kunjungan->orderRadiologi()
                ->where(function ($q) {
                    $q->where('status', '!=', StatusOrderRadiologi::Batal->value)
                        ->orWhereNotNull('waktu_dikerjakan');
                })
                ->with('detail.pemeriksaan')
                ->get();

            foreach ($orderRadiologi as $order) {
                foreach ($order->detail as $item) {
                    $tagihan->detail()->create([
                        'sumber_tipe' => $item::class,
                        'sumber_id' => $item->id,
                        'deskripsi' => $item->pemeriksaan->nama,
                        'jumlah' => 1,
                        'tarif_satuan' => $item->tarif_satuan,
                        'subtotal' => $item->tarif_satuan,
                    ]);
                }
            }
```

Tambahkan `use App\Enums\StatusOrderRadiologi;` bila belum ada, lalu periksa import ganda.

- [ ] **Step 6: Tulis OrderRadiologiPolicy**

`app/Policies/OrderRadiologiPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Models\OrderRadiologi;
use App\Models\User;

class OrderRadiologiPolicy
{
    public function pesan(User $user): bool
    {
        return $user->hasRole(Peran::Dokter->value);
    }

    public function kerjakan(User $user, OrderRadiologi $order): bool
    {
        return $user->hasRole(Peran::Radiografer->value) && ! $order->status->selesai();
    }

    /**
     * Aturan 54: radiografer mengerjakan pencitraannya, dokter yang menyimpulkan.
     * Pemisahan ini bukan formalitas — menyimpulkan temuan adalah tindakan medis.
     */
    public function ekspertise(User $user, OrderRadiologi $order): bool
    {
        return $user->hasRole(Peran::Dokter->value);
    }

    public function baca(User $user, OrderRadiologi $order): bool
    {
        return $user->hasAnyRole([
            Peran::Dokter->value, Peran::Radiografer->value, Peran::RekamMedis->value,
        ]) && $order->terbacaDokter();
    }
}
```

- [ ] **Step 7: Jalankan kedua test sampai lulus, lalu seluruh suite**

Run: `php artisan test --filter="TagihanRadiologiTest|HakAksesRadiologiTest"` → PASS.
Run: `php artisan test` → seluruhnya hijau.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: kunci penyelesaian kunjungan sampai ekspertise ada dan bebankan biaya radiologi"
```

---

### Task 5: Layar radiologi

**Files:**
- Create: `app/Livewire/Radiologi/{AntreanOrder,LayarPelaksanaan,LayarEkspertise}.php`, `app/Livewire/Master/DaftarPemeriksaanRadiologi.php`, view masing-masing
- Modify: `app/Livewire/Poli/FormSoap.php`, `resources/views/livewire/poli/form-soap.blade.php`, `routes/web.php`
- Test: `tests/Feature/LayarRadiologiTest.php`

**Interfaces:**
- Consumes: seluruh service Task 2–4, `OrderRadiologiPolicy`
- Produces: rute `radiologi.antrean`, `radiologi.kerjakan` di belakang `role:radiografer`; `radiologi.ekspertise` di belakang `role:dokter`; `master.pemeriksaan-radiologi` di belakang `role:admin`. `FormSoap` mendapat aksi `pesanRadiologi()` dan menampilkan ekspertise yang sudah ditulis.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/LayarRadiologiTest.php` dengan delapan test, ditulis utuh memakai
pola `tests/Feature/LayarLabTest.php`:

```php
    public function test_antrean_menampilkan_order_yang_belum_dikerjakan(): void
    public function test_radiografer_mengerjakan_pencitraan_lewat_layar(): void
    public function test_nomor_film_kosong_menampilkan_pesan_di_layar(): void
    public function test_layar_pelaksanaan_menampilkan_indikasi_klinis_dan_persiapan(): void
    public function test_dokter_menulis_ekspertise_lewat_layar(): void
    public function test_temuan_kosong_menampilkan_pesan_di_layar(): void
    public function test_dokter_memesan_radiologi_dari_layar_soap(): void
    public function test_radiografer_tidak_bisa_membuka_layar_kasir(): void
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=LayarRadiologiTest`
Diharapkan: FAIL dengan "Unable to find component".

- [ ] **Step 3: Tulis komponen**

`app/Livewire/Radiologi/AntreanOrder.php` menampilkan
`OrderRadiologi::with('kunjungan.pasien', 'detail.pemeriksaan')->where('status', $this->status)->latest('id')->paginate(15)`
dengan pemilih status, mengikuti `app/Livewire/Lab/AntreanOrder.php`.

`app/Livewire/Radiologi/LayarPelaksanaan.php` menerima `OrderRadiologi`, memanggil
`$this->authorize('kerjakan', $order)` di `mount()`, menyimpan properti `no_film`, dan
memanggil `PelaksanaanRadiologi::kerjakan()` di dalam `try/catch` yang memetakan
`ValidationException` ke kolomnya dan `RuntimeException` ke kunci `pelaksanaan`.
Viewnya menampilkan indikasi klinis dan instruksi persiapan pemeriksaan.

`app/Livewire/Radiologi/LayarEkspertise.php` menerima `OrderRadiologi`, memanggil
`$this->authorize('ekspertise', $order)` di `mount()`, menyimpan array `bacaan` berkunci
`order_radiologi_detail_id` dengan isian `temuan`, `kesan`, dan `saran`, lalu memanggil
`PenulisanEkspertise::tulis()` atau `koreksi()` sesuai status ordernya.

`app/Livewire/Master/DaftarPemeriksaanRadiologi.php` mengikuti pola `Master\DaftarPoli`.

- [ ] **Step 4: Tulis view**

`antrean-order.blade.php` menampilkan nomor order, pasien, pemeriksaan, modalitas, dan
tautan sesuai statusnya. `layar-pelaksanaan.blade.php` menampilkan indikasi klinis,
instruksi persiapan, isian nomor film, dan tombol Kerjakan. `layar-ekspertise.blade.php`
menampilkan nomor film, indikasi klinis, dan tiga area teks per pemeriksaan; bila
ordernya sudah selesai, tampilkan pula isian alasan koreksi.

- [ ] **Step 5: Sambungkan ke layar dokter**

Di `app/Livewire/Poli/FormSoap.php`, tambahkan properti `pemeriksaanRadiologiDipilih`
dan `indikasiRadiologi`, method `pesanRadiologi()` yang memanggil
`PemesananRadiologi::pesan()` lewat helper `jalankan()` yang sudah ada, serta kirim
`daftarPemeriksaanRadiologi` dan `orderRadiologi` dari `render()`.

Pada viewnya, tambahkan satu kartu pemesanan radiologi berisi pilihan pemeriksaan dan
isian indikasi klinis, serta tampilkan ekspertise yang **sudah ditulis saja** — order
yang belum ditampilkan sebagai "menunggu ekspertise radiologi".

- [ ] **Step 6: Daftarkan rute**

```php
Route::middleware('role:radiografer')->group(function () {
    Route::get('/radiologi/antrean', AntreanOrderRadiologi::class)->name('radiologi.antrean');
    Route::get('/radiologi/kerjakan/{order}', LayarPelaksanaan::class)->name('radiologi.kerjakan');
});

Route::middleware('role:dokter')->group(function () {
    Route::get('/radiologi/ekspertise/{order}', LayarEkspertise::class)->name('radiologi.ekspertise');
});
```

Impor `App\Livewire\Radiologi\AntreanOrder` dengan alias `AntreanOrderRadiologi` karena
namanya bertabrakan dengan `App\Livewire\Lab\AntreanOrder` yang sudah diimpor.
Tambahkan pula `master.pemeriksaan-radiologi` ke grup `role:admin`.

- [ ] **Step 7: Jalankan test sampai lulus, lalu seluruh suite**

Run: `php artisan test --filter=LayarRadiologiTest` → PASS, 8 test.
Run: `php artisan test` → seluruhnya hijau.

- [ ] **Step 8: Sapu layar di aplikasi yang benar-benar jalan**

```bash
php artisan serve --port=8127
```

Masuk sebagai radiografer dan dokter, lalu buka setiap rute radiologi. Semuanya harus
membalas 200. Cara ini yang menemukan bug 500 pada layar ubah pasien di Fase 1 —
bug yang lolos dari 150 test.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: tambah layar radiologi untuk radiografer dan penulisan ekspertise dokter"
```

---

### Task 6: Seeder radiologi dan verifikasi menyeluruh

**Files:**
- Create: `database/seeders/RadiologiSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`, `database/seeders/PenggunaSeeder.php`, `database/seeders/KunjunganDummySeeder.php`, `README.md`
- Test: `tests/Feature/AlurRadiologiTest.php`

**Interfaces:**
- Consumes: seluruh service Task 2–4
- Produces: `php artisan migrate:fresh --seed` menghasilkan radiologi siap demo, dan satu test alur menyeluruh.

- [ ] **Step 1: Tulis test alur menyeluruh**

Buat `tests/Feature/AlurRadiologiTest.php` dengan dua test yang ditulis utuh memakai
`tests/Feature/AlurLabTest.php` sebagai kerangka:

```php
    public function test_alur_lengkap_dari_dokter_memesan_sampai_kunjungan_ditutup(): void
    // daftar -> vital -> soap -> tindakan -> pesan radiologi -> coba tutup (ditolak)
    // -> radiografer kerjakan -> dokter tulis ekspertise -> tutup -> bayar
    // assert: tagihan 200.000, dua sumber, status order Selesai

    public function test_pekerjaan_radiografer_dan_dokter_tercatat_terpisah(): void
    // assert: dikerjakan_oleh = radiografer, ditulis_oleh = dokter, keduanya berbeda
```

- [ ] **Step 2: Jalankan dan perbaiki sampai lulus**

Run: `php artisan test --filter=AlurRadiologiTest` → PASS, 2 test.

- [ ] **Step 3: Tulis RadiologiSeeder**

`database/seeders/RadiologiSeeder.php` mengisi dua belas pemeriksaan beserta tarif kedua
penjamin (BPJS sekitar 70% tarif umum, dibulatkan ke ribuan):

| Kode | Nama | Modalitas | Tarif umum | Persiapan |
|---|---|---|---|---|
| RAD001 | Rontgen Toraks PA | rontgen | 150.000 | — |
| RAD002 | Rontgen Abdomen Polos | rontgen | 165.000 | — |
| RAD003 | Rontgen Ekstremitas | rontgen | 140.000 | — |
| RAD004 | Rontgen Panoramik Gigi | rontgen | 250.000 | Lepas perhiasan logam di kepala dan leher |
| RAD005 | USG Abdomen | usg | 220.000 | Puasa 6 jam sebelum pemeriksaan |
| RAD006 | USG Kandungan | usg | 200.000 | Menahan buang air kecil satu jam sebelumnya |
| RAD007 | USG Tiroid | usg | 210.000 | — |
| RAD008 | CT Scan Kepala | ct_scan | 950.000 | Puasa 4 jam bila memakai kontras |
| RAD009 | CT Scan Toraks | ct_scan | 1.100.000 | Puasa 4 jam bila memakai kontras |
| RAD010 | CT Scan Abdomen | ct_scan | 1.250.000 | Puasa 6 jam |
| RAD011 | MRI Kepala | mri | 2.100.000 | Lepas seluruh benda logam; beri tahu bila ada implan |
| RAD012 | Mammografi | mammografi | 450.000 | Tidak memakai deodoran atau bedak di area dada |

- [ ] **Step 4: Tambahkan pengguna radiografer**

Di `database/seeders/PenggunaSeeder.php`, tambahkan:

```php
            [Peran::Radiografer, 'Radiografer', 'radiografer@rs.test'],
```

- [ ] **Step 5: Sambungkan ke data dummy kunjungan**

Di `database/seeders/KunjunganDummySeeder.php`, untuk sebagian kunjungan yang akan
diselesaikan, pesan satu order radiologi lalu jalankan sampai ekspertise ditulis
**sebelum** kunjungan diselesaikan — aturan 50 menolak bila urutannya terbalik.

Sisakan beberapa order pada kunjungan yang masih terbuka: 4 berstatus `dipesan` dan
3 `dikerjakan`, supaya kedua layar radiologi punya isi.

Tambahkan `RadiologiSeeder::class` ke `DatabaseSeeder` sebelum `KunjunganDummySeeder::class`.

- [ ] **Step 6: Jalankan seluruh alur dari nol**

```bash
php artisan migrate:fresh --seed
mysql -u irvan -p1 simrs -e "
SELECT (SELECT COUNT(*) FROM pemeriksaan_radiologi) AS pemeriksaan,
       (SELECT COUNT(*) FROM tarif WHERE jenis_layanan='radiologi') AS tarif,
       (SELECT COUNT(*) FROM order_radiologi) AS \`order\`,
       (SELECT COUNT(*) FROM ekspertise_radiologi) AS ekspertise;"
mysql -u irvan -p1 simrs -e "SELECT status, COUNT(*) FROM order_radiologi GROUP BY status;"
mysql -u irvan -p1 simrs -e "SELECT sumber_tipe, COUNT(*), SUM(subtotal) FROM tagihan_detail GROUP BY sumber_tipe;"
```

Diharapkan: pemeriksaan 12, tarif 24, order > 7 tersebar pada tiga status, ekspertise > 0,
dan `tagihan_detail` memuat empat sumber berbeda.

- [ ] **Step 7: Jalankan seluruh test**

Run: `php artisan test` → seluruh berkas PASS, tanpa satu pun di-skip.

- [ ] **Step 8: Perbarui README**

Tambahkan modul Radiologi ke tabel cakupan, akun `radiografer@rs.test` ke tabel akun
demo, dan satu bagian tentang alur radiologi berikut pemisahan pekerjaan radiografer
dan dokter radiologi.

- [ ] **Step 9: Commit dan dorong**

```bash
git add -A
git commit -m "feat: tambah seeder radiologi dan test alur radiologi menyeluruh"
git push
```

---

## Ringkasan Cakupan

| Aturan (spec Fase 4 bagian 8) | Tugas |
|---|---|
| 47 Order wajib memuat pemeriksaan, tanpa duplikat | Task 2 |
| 48 Indikasi klinis wajib | Task 2 |
| 49 Tarif disalin saat order | Task 2 |
| 50 Kunjungan terkunci sampai ekspertise ada | Task 4 |
| 51 Pencitraan wajib bernomor film | Task 3 |
| 52 Ekspertise setelah dikerjakan | Task 1, 3 |
| 53 Temuan dan kesan wajib | Task 3 |
| 54 Hanya dokter yang menulis ekspertise | Task 4 |
| 55 Hasil terbaca setelah ekspertise | Task 2, 4 |
| 56 Koreksi ekspertise wajib beralasan | Task 3 |
| 57 Batal sebelum/sesudah dikerjakan | Task 4 |
| 58 Pembatalan wajib beralasan | Task 2 |
