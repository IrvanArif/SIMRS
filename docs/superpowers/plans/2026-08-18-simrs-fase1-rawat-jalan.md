# SIMRS Fase 1 (Fondasi + Rawat Jalan) — Rencana Implementasi

> **Untuk pekerja agentik:** SUB-SKILL WAJIB: gunakan superpowers:subagent-driven-development (disarankan) atau superpowers:executing-plans untuk mengerjakan rencana ini tugas demi tugas. Setiap langkah memakai checkbox (`- [ ]`) untuk penanda kemajuan.

**Goal:** Membangun alur rawat jalan yang utuh — pasien mendaftar, mendapat nomor antrian, diperiksa perawat lalu dokter, mendapat diagnosa dan resep, sampai tagihannya diselesaikan kasir — di atas fondasi Laravel 13 yang siap ditumpangi modul fase berikutnya.

**Architecture:** Monolit Laravel 13 dengan Livewire 3 sebagai lapisan layar. Komponen Livewire hanya mengurus tampilan dan interaksi; seluruh aturan bisnis yang punya konsekuensi (penomoran rekam medis, penomoran antrian harian, pencarian tarif, penyusunan tagihan) tinggal di kelas Service di `app/Services` supaya bisa diuji tanpa merender UI. Hak akses ditegakkan lewat Policy, dan seluruh perubahan data klinis dicatat Observer ke tabel audit.

**Tech Stack:** PHP 8.5, Laravel 13, Livewire 3, Blade, Tailwind (Vite), MySQL/MariaDB, `spatie/laravel-permission` v6, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-08-18-simrs-fase1-rawat-jalan-design.md`

## Global Constraints

Berlaku untuk **semua** tugas di bawah. Setiap tugas menganggap bagian ini bagian dari persyaratannya.

- **Bahasa:** seluruh nama tabel, kolom, rute, label UI, dan pesan validasi memakai bahasa Indonesia. Nama kelas dan method PHP juga bahasa Indonesia kecuali yang diwajibkan Laravel (`up`, `down`, `render`, `boot`, `handle`).
- **Nama test berbahasa Indonesia** dengan awalan `test_`, contoh: `test_nik_kurang_dari_16_digit_ditolak`. Gaya ini mengikuti proyek antrian sebelumnya.
- **TDD tanpa pengecualian:** test ditulis lebih dulu, dijalankan sampai terbukti GAGAL, baru implementasinya ditulis. Langkah "jalankan test dan pastikan gagal" tidak boleh dilewati.
- **Nominal uang** disimpan sebagai bilangan bulat rupiah (`unsignedBigInteger`), bukan desimal maupun float. Tidak ada satuan sen.
- **Penomoran tidak boleh memakai `max() + 1`.** Semua nomor diambil lewat `App\Services\PencatatNomor` di dalam transaksi dengan `lockForUpdate()`, dan tabelnya wajib punya unique constraint sebagai jaring pengaman terakhir.
- **Data klinis tidak dihapus keras.** `pasien` dan `pemeriksaan` memakai `SoftDeletes`.
- **Database:** aplikasi `simrs`, pengujian `simrs_test`, pengguna MySQL `irvan` sandi `1`.
- **Enum PHP** dipakai untuk semua kolom berstatus; tidak ada string mentah yang ditulis langsung di dalam komponen atau service.
- **Commit setiap selesai satu tugas**, dengan pesan berbahasa Indonesia berformat `feat: ...`, `test: ...`, atau `chore: ...`.
- **Aturan bisnis** yang dirujuk berupa nomor (mis. "aturan 14") mengacu ke bagian 8 spec.

---

## Struktur Berkas

Berkas dan tanggung jawabnya, dikelompokkan menurut apa yang berubah bersama — bukan menurut lapisan teknis.

**Aturan bisnis (Service) — inti yang paling banyak diuji**

| Berkas | Tanggung jawab |
|---|---|
| `app/Services/PencatatNomor.php` | Satu-satunya tempat nomor urut dikeluarkan, aman dari tabrakan |
| `app/Services/NomorRekamMedis.php` | Nomor RM 6 digit, sekuensial global, tak pernah dipakai ulang |
| `app/Services/NomorAntrian.php` | Nomor antrian per poli per hari, dimulai dari 1 tiap hari |
| `app/Services/NomorDokumen.php` | Nomor kunjungan, resep, tagihan, kuitansi berformat tanggal |
| `app/Services/PencariTarif.php` | Tarif tindakan menurut penjamin, dengan jatuh-tempo ke UMUM |
| `app/Services/PenyusunTagihan.php` | Menyusun tagihan dari tindakan kunjungan |

**Model** — satu berkas per entitas di `app/Models`, tanpa aturan bisnis di dalamnya selain relasi, cast, dan scope.

**Enum** — `app/Enums/{StatusKunjungan,StatusAntrian,StatusTagihan,JenisDiagnosa,JenisKelamin,MetodePembayaran,Peran}.php`

**Policy** — `app/Policies/{PasienPolicy,KunjunganPolicy,PemeriksaanPolicy,TagihanPolicy}.php`

**Observer** — `app/Observers/PencatatAudit.php`, dipasang ke seluruh model klinis.

**Layar (Livewire)** — dikelompokkan per peran di `app/Livewire/{Pendaftaran,Poli,Kasir,Master,Admin}`, satu kelas satu layar, tanpa aturan bisnis.

**Test** — `tests/Unit` untuk Service, `tests/Feature` untuk alur per peran.

---

### Task 1: Bootstrap proyek Laravel

**Files:**
- Create: seluruh kerangka Laravel di `/var/www/html/SIMRS`
- Modify: `.env`, `phpunit.xml`
- Test: `tests/Feature/SmokeTest.php`

**Interfaces:**
- Consumes: —
- Produces: kerangka Laravel 13 yang bisa dites, database `simrs` dan `simrs_test`, repositori git.

- [ ] **Step 1: Buat kedua database**

```bash
mysql -u irvan -p1 -e "CREATE DATABASE simrs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u irvan -p1 -e "CREATE DATABASE simrs_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u irvan -p1 -e "SHOW DATABASES;" | grep simrs
```

Diharapkan: `simrs` dan `simrs_test` muncul.

- [ ] **Step 2: Pasang kerangka Laravel tanpa menghilangkan folder docs**

`composer create-project` menolak folder yang tidak kosong, sedangkan `docs/` sudah berisi spec. Jadi folder itu disingkirkan sementara:

```bash
mv /var/www/html/SIMRS/docs /tmp/simrs-docs
composer create-project laravel/laravel /var/www/html/SIMRS
mv /tmp/simrs-docs /var/www/html/SIMRS/docs
php artisan --version
```

Diharapkan: versi Laravel 13.x tercetak, dan `docs/superpowers/specs/` masih ada.

- [ ] **Step 3: Setel `.env`**

Ubah baris-baris berikut di `.env` (buat bila belum ada):

```
APP_NAME="SIMRS"
APP_LOCALE=id
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=id_ID

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=simrs
DB_USERNAME=irvan
DB_PASSWORD=1
```

Salin perubahan yang sama ke `.env.example`, tapi kosongkan `DB_USERNAME` dan `DB_PASSWORD` di sana.

- [ ] **Step 4: Pasang Livewire dan spatie/laravel-permission**

```bash
cd /var/www/html/SIMRS
composer require livewire/livewire spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
npm install && npm run build
```

- [ ] **Step 5: Arahkan pengujian ke `simrs_test`**

Di `phpunit.xml`, di dalam blok `<php>`, dua baris `DB_CONNECTION`/`DB_DATABASE` bawaan Laravel ada dalam bentuk komentar. Ganti keduanya menjadi:

```xml
<env name="DB_CONNECTION" value="mysql"/>
<env name="DB_DATABASE" value="simrs_test"/>
```

- [ ] **Step 6: Tulis smoke test**

Buat `tests/Feature/SmokeTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_aplikasi_menyala_dan_terhubung_ke_database_pengujian(): void
    {
        $this->assertSame('simrs_test', config('database.connections.mysql.database'));
        $this->get('/')->assertSuccessful();
    }
}
```

- [ ] **Step 7: Jalankan test**

Run: `php artisan test --filter=SmokeTest`
Diharapkan: PASS. Bila gagal karena koneksi, periksa kembali kredensial di `.env` dan `phpunit.xml`.

- [ ] **Step 8: Inisialisasi git dan commit**

```bash
cd /var/www/html/SIMRS
git init
git add -A
git commit -m "chore: bootstrap Laravel 13 + Livewire + spatie permission untuk SIMRS Fase 1"
```

---

### Task 2: Enum status

**Files:**
- Create: `app/Enums/Peran.php`, `app/Enums/JenisKelamin.php`, `app/Enums/StatusKunjungan.php`, `app/Enums/StatusAntrian.php`, `app/Enums/StatusTagihan.php`, `app/Enums/JenisDiagnosa.php`, `app/Enums/MetodePembayaran.php`
- Test: `tests/Unit/EnumTest.php`

**Interfaces:**
- Consumes: —
- Produces: seluruh enum di atas. Semua tugas berikutnya memakai case enum ini, tidak pernah string mentah. `StatusKunjungan` menyediakan `aktif(): bool` yang bernilai true untuk semua status kecuali `Selesai` dan `Batal` (dipakai aturan 6).

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Unit/EnumTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Enums\StatusKunjungan;
use App\Enums\StatusTagihan;
use PHPUnit\Framework\TestCase;

class EnumTest extends TestCase
{
    public function test_status_kunjungan_terdaftar_dan_diperiksa_terhitung_aktif(): void
    {
        $this->assertTrue(StatusKunjungan::Terdaftar->aktif());
        $this->assertTrue(StatusKunjungan::DiperiksaPerawat->aktif());
        $this->assertTrue(StatusKunjungan::DiperiksaDokter->aktif());
    }

    public function test_status_kunjungan_selesai_dan_batal_tidak_aktif(): void
    {
        $this->assertFalse(StatusKunjungan::Selesai->aktif());
        $this->assertFalse(StatusKunjungan::Batal->aktif());
    }

    public function test_nilai_status_tagihan_sesuai_spec(): void
    {
        $this->assertSame('belum_bayar', StatusTagihan::BelumBayar->value);
        $this->assertSame('ditanggung_penjamin', StatusTagihan::DitanggungPenjamin->value);
    }
}
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=EnumTest`
Diharapkan: FAIL dengan "Class App\Enums\StatusKunjungan not found".

- [ ] **Step 3: Tulis enum**

`app/Enums/StatusKunjungan.php`:

```php
<?php

namespace App\Enums;

enum StatusKunjungan: string
{
    case Terdaftar = 'terdaftar';
    case DiperiksaPerawat = 'diperiksa_perawat';
    case DiperiksaDokter = 'diperiksa_dokter';
    case Selesai = 'selesai';
    case Batal = 'batal';

    public function aktif(): bool
    {
        return ! in_array($this, [self::Selesai, self::Batal], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Terdaftar => 'Terdaftar',
            self::DiperiksaPerawat => 'Diperiksa Perawat',
            self::DiperiksaDokter => 'Diperiksa Dokter',
            self::Selesai => 'Selesai',
            self::Batal => 'Batal',
        };
    }
}
```

`app/Enums/StatusAntrian.php`:

```php
<?php

namespace App\Enums;

enum StatusAntrian: string
{
    case Menunggu = 'menunggu';
    case Dipanggil = 'dipanggil';
    case Dilayani = 'dilayani';
    case Selesai = 'selesai';
    case Terlewat = 'terlewat';
}
```

`app/Enums/StatusTagihan.php`:

```php
<?php

namespace App\Enums;

enum StatusTagihan: string
{
    case BelumBayar = 'belum_bayar';
    case Lunas = 'lunas';
    case DitanggungPenjamin = 'ditanggung_penjamin';
    case Batal = 'batal';
}
```

`app/Enums/JenisDiagnosa.php`:

```php
<?php

namespace App\Enums;

enum JenisDiagnosa: string
{
    case Primer = 'primer';
    case Sekunder = 'sekunder';
}
```

`app/Enums/JenisKelamin.php`:

```php
<?php

namespace App\Enums;

enum JenisKelamin: string
{
    case LakiLaki = 'L';
    case Perempuan = 'P';

    public function label(): string
    {
        return $this === self::LakiLaki ? 'Laki-laki' : 'Perempuan';
    }
}
```

`app/Enums/MetodePembayaran.php`:

```php
<?php

namespace App\Enums;

enum MetodePembayaran: string
{
    case Tunai = 'tunai';
    case Debit = 'debit';
    case Qris = 'qris';

    public function butuhKembalian(): bool
    {
        return $this === self::Tunai;
    }
}
```

`app/Enums/Peran.php`:

```php
<?php

namespace App\Enums;

enum Peran: string
{
    case Admisi = 'admisi';
    case Perawat = 'perawat';
    case Dokter = 'dokter';
    case RekamMedis = 'rekam_medis';
    case Kasir = 'kasir';
    case Admin = 'admin';

    public static function semua(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

- [ ] **Step 4: Jalankan test sampai lulus**

Run: `php artisan test --filter=EnumTest`
Diharapkan: PASS, 3 test.

- [ ] **Step 5: Commit**

```bash
git add app/Enums tests/Unit/EnumTest.php
git commit -m "feat: tambah enum status kunjungan, antrian, tagihan, diagnosa, dan peran"
```

---

### Task 3: Tabel dan model master

**Files:**
- Create: migration `create_master_tables`, `app/Models/{Poli,Dokter,JadwalDokter,Penjamin,Tindakan,TarifTindakan,Icd10,Obat}.php`, factory untuk masing-masing
- Test: `tests/Feature/MasterDataTest.php`

**Interfaces:**
- Consumes: `App\Enums` (Task 2)
- Produces: model `Poli`, `Dokter`, `JadwalDokter`, `Penjamin`, `Tindakan`, `TarifTindakan`, `Icd10`, `Obat` beserta factory-nya. `Penjamin::$jenis` bernilai `'tunai'` atau `'penjamin'`; `Penjamin::ditanggung(): bool` bernilai true bila `jenis === 'penjamin'` (dipakai aturan 14). `TarifTindakan` punya kolom `tarif` (bilangan bulat rupiah) dan `berlaku_mulai` (date).

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/MasterDataTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Dokter;
use App\Models\Penjamin;
use App\Models\TarifTindakan;
use App\Models\Tindakan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_dokter_terhubung_ke_poli_tempatnya_bertugas(): void
    {
        $dokter = Dokter::factory()->create();

        $this->assertNotNull($dokter->poli);
        $this->assertSame($dokter->poli_id, $dokter->poli->id);
    }

    public function test_penjamin_berjenis_penjamin_dianggap_menanggung_biaya(): void
    {
        $bpjs = Penjamin::factory()->create(['kode' => 'BPJS', 'jenis' => 'penjamin']);
        $umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);

        $this->assertTrue($bpjs->ditanggung());
        $this->assertFalse($umum->ditanggung());
    }

    public function test_satu_tindakan_bisa_punya_tarif_berbeda_per_penjamin(): void
    {
        $tindakan = Tindakan::factory()->create();
        $umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $bpjs = Penjamin::factory()->create(['kode' => 'BPJS', 'jenis' => 'penjamin']);

        TarifTindakan::factory()->create([
            'tindakan_id' => $tindakan->id, 'penjamin_id' => $umum->id, 'tarif' => 50000,
        ]);
        TarifTindakan::factory()->create([
            'tindakan_id' => $tindakan->id, 'penjamin_id' => $bpjs->id, 'tarif' => 35000,
        ]);

        $this->assertSame(2, $tindakan->tarif()->count());
    }

    public function test_tarif_ganda_untuk_penjamin_dan_tanggal_berlaku_sama_ditolak_database(): void
    {
        $tindakan = Tindakan::factory()->create();
        $penjamin = Penjamin::factory()->create();

        TarifTindakan::factory()->create([
            'tindakan_id' => $tindakan->id,
            'penjamin_id' => $penjamin->id,
            'berlaku_mulai' => '2026-01-01',
        ]);

        $this->expectException(QueryException::class);

        TarifTindakan::factory()->create([
            'tindakan_id' => $tindakan->id,
            'penjamin_id' => $penjamin->id,
            'berlaku_mulai' => '2026-01-01',
        ]);
    }
}
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=MasterDataTest`
Diharapkan: FAIL dengan "Class App\Models\Dokter not found".

- [ ] **Step 3: Tulis migration**

```bash
php artisan make:migration create_master_tables
```

Isi method `up()`:

```php
Schema::create('poli', function (Blueprint $table) {
    $table->id();
    $table->string('kode', 10)->unique();
    $table->string('nama', 100);
    $table->string('lokasi', 100)->nullable();
    $table->boolean('aktif')->default(true);
    $table->timestamps();
});

Schema::create('dokter', function (Blueprint $table) {
    $table->id();
    $table->string('nip', 30)->unique();
    $table->string('nama', 100);
    $table->string('spesialisasi', 100)->nullable();
    $table->string('no_sip', 50)->nullable();
    $table->foreignId('poli_id')->constrained('poli');
    $table->boolean('aktif')->default(true);
    $table->timestamps();
});

Schema::create('jadwal_dokter', function (Blueprint $table) {
    $table->id();
    $table->foreignId('dokter_id')->constrained('dokter')->cascadeOnDelete();
    $table->unsignedTinyInteger('hari');
    $table->time('jam_mulai');
    $table->time('jam_selesai');
    $table->unsignedSmallInteger('kuota')->default(30);
    $table->timestamps();
    $table->unique(['dokter_id', 'hari', 'jam_mulai']);
});

Schema::create('penjamin', function (Blueprint $table) {
    $table->id();
    $table->string('kode', 20)->unique();
    $table->string('nama', 100);
    $table->enum('jenis', ['tunai', 'penjamin']);
    $table->boolean('aktif')->default(true);
    $table->timestamps();
});

Schema::create('tindakan', function (Blueprint $table) {
    $table->id();
    $table->string('kode', 20)->unique();
    $table->string('nama', 150);
    $table->enum('kategori', ['administrasi', 'konsultasi', 'tindakan_medis']);
    $table->boolean('aktif')->default(true);
    $table->timestamps();
});

Schema::create('tarif_tindakan', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tindakan_id')->constrained('tindakan')->cascadeOnDelete();
    $table->foreignId('penjamin_id')->constrained('penjamin');
    $table->unsignedBigInteger('tarif');
    $table->date('berlaku_mulai');
    $table->timestamps();
    $table->unique(['tindakan_id', 'penjamin_id', 'berlaku_mulai']);
});

Schema::create('icd10', function (Blueprint $table) {
    $table->id();
    $table->string('kode', 10)->unique();
    $table->string('nama_id', 255);
    $table->string('nama_en', 255)->nullable();
    $table->timestamps();
});

Schema::create('obat', function (Blueprint $table) {
    $table->id();
    $table->string('kode', 20)->unique();
    $table->string('nama', 150);
    $table->string('satuan', 20);
    $table->string('bentuk_sediaan', 50)->nullable();
    $table->boolean('aktif')->default(true);
    $table->timestamps();
});
```

Method `down()` menghapus tabel dengan urutan terbalik.

- [ ] **Step 4: Tulis model**

Contoh `app/Models/Penjamin.php` — model lain mengikuti pola yang sama (nama tabel eksplisit karena bentuk jamak Inggris tidak berlaku):

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Penjamin extends Model
{
    use HasFactory;

    protected $table = 'penjamin';

    protected $guarded = [];

    protected $casts = ['aktif' => 'boolean'];

    public function ditanggung(): bool
    {
        return $this->jenis === 'penjamin';
    }
}
```

`app/Models/Dokter.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dokter extends Model
{
    use HasFactory;

    protected $table = 'dokter';

    protected $guarded = [];

    protected $casts = ['aktif' => 'boolean'];

    public function poli(): BelongsTo
    {
        return $this->belongsTo(Poli::class);
    }

    public function jadwal(): HasMany
    {
        return $this->hasMany(JadwalDokter::class);
    }
}
```

`app/Models/Tindakan.php` menambahkan relasi tarif:

```php
public function tarif(): HasMany
{
    return $this->hasMany(TarifTindakan::class);
}
```

Model `Poli`, `JadwalDokter`, `TarifTindakan`, `Icd10`, `Obat` dibuat dengan pola yang sama: `protected $table` eksplisit, `protected $guarded = []`, `use HasFactory`, plus relasi `belongsTo` ke induknya.

- [ ] **Step 5: Tulis factory**

`database/factories/PenjaminFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Penjamin;
use Illuminate\Database\Eloquent\Factories\Factory;

class PenjaminFactory extends Factory
{
    protected $model = Penjamin::class;

    public function definition(): array
    {
        return [
            'kode' => strtoupper($this->faker->unique()->lexify('PJ???')),
            'nama' => $this->faker->company(),
            'jenis' => 'tunai',
            'aktif' => true,
        ];
    }
}
```

`database/factories/DokterFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Dokter;
use App\Models\Poli;
use Illuminate\Database\Eloquent\Factories\Factory;

class DokterFactory extends Factory
{
    protected $model = Dokter::class;

    public function definition(): array
    {
        return [
            'nip' => $this->faker->unique()->numerify('##########'),
            'nama' => 'dr. '.$this->faker->name(),
            'spesialisasi' => 'Umum',
            'no_sip' => $this->faker->numerify('SIP-####'),
            'poli_id' => Poli::factory(),
            'aktif' => true,
        ];
    }
}
```

`database/factories/TarifTindakanFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Penjamin;
use App\Models\TarifTindakan;
use App\Models\Tindakan;
use Illuminate\Database\Eloquent\Factories\Factory;

class TarifTindakanFactory extends Factory
{
    protected $model = TarifTindakan::class;

    public function definition(): array
    {
        return [
            'tindakan_id' => Tindakan::factory(),
            'penjamin_id' => Penjamin::factory(),
            'tarif' => $this->faker->numberBetween(10000, 500000),
            'berlaku_mulai' => '2026-01-01',
        ];
    }
}
```

Factory `PoliFactory`, `TindakanFactory`, `Icd10Factory`, `ObatFactory`, `JadwalDokterFactory` dibuat serupa dengan data acak yang memenuhi unique constraint (pakai `unique()` dari faker).

- [ ] **Step 6: Jalankan test sampai lulus**

Run: `php artisan test --filter=MasterDataTest`
Diharapkan: PASS, 4 test.

- [ ] **Step 7: Commit**

```bash
git add database/migrations app/Models database/factories tests/Feature/MasterDataTest.php
git commit -m "feat: tambah tabel dan model master data (poli, dokter, penjamin, tindakan, tarif, ICD-10, obat)"
```

---
### Task 4: Peran, pengguna, dan autentikasi

**Files:**
- Create: migration `tambah_dokter_id_ke_users`, `app/Http/Controllers/AutentikasiController.php`, `resources/views/auth/masuk.blade.php`, `resources/views/beranda.blade.php`, `resources/views/layouts/app.blade.php`, `database/seeders/PeranSeeder.php`
- Modify: `app/Models/User.php`, `routes/web.php`, `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/AutentikasiTest.php`

**Interfaces:**
- Consumes: `App\Enums\Peran` (Task 2), `App\Models\Dokter` (Task 3)
- Produces: `User` yang memakai trait `Spatie\Permission\Traits\HasRoles` dengan kolom `dokter_id` nullable dan relasi `dokter()`. Rute bernama `masuk`, `keluar`, `beranda`. Layout `layouts.app` dengan slot `$slot` yang dipakai seluruh layar berikutnya. Seeder `PeranSeeder` membuat 6 peran dari `Peran::semua()`.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/AutentikasiTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Dokter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AutentikasiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Peran::semua() as $peran) {
            Role::findOrCreate($peran);
        }
    }

    public function test_pengguna_terdaftar_bisa_masuk(): void
    {
        $user = User::factory()->create(['password' => bcrypt('rahasia123')]);

        $this->post('/masuk', ['email' => $user->email, 'password' => 'rahasia123'])
            ->assertRedirect('/beranda');

        $this->assertAuthenticatedAs($user);
    }

    public function test_sandi_salah_ditolak_dengan_pesan_bahasa_indonesia(): void
    {
        $user = User::factory()->create(['password' => bcrypt('rahasia123')]);

        $this->from('/masuk')
            ->post('/masuk', ['email' => $user->email, 'password' => 'salah'])
            ->assertRedirect('/masuk')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_tamu_tidak_bisa_membuka_beranda(): void
    {
        $this->get('/beranda')->assertRedirect('/masuk');
    }

    public function test_peran_melekat_pada_pengguna(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Peran::Dokter->value);

        $this->assertTrue($user->hasRole(Peran::Dokter->value));
        $this->assertFalse($user->hasRole(Peran::Kasir->value));
    }

    public function test_pengguna_berperan_dokter_terhubung_ke_data_dokter(): void
    {
        $dokter = Dokter::factory()->create();
        $user = User::factory()->create(['dokter_id' => $dokter->id]);

        $this->assertSame($dokter->id, $user->dokter->id);
    }
}
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=AutentikasiTest`
Diharapkan: FAIL — rute `/masuk` belum ada (404) dan kolom `dokter_id` belum ada.

- [ ] **Step 3: Tambah kolom `dokter_id` pada users**

```bash
php artisan make:migration tambah_dokter_id_ke_users
```

```php
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->foreignId('dokter_id')->nullable()->after('email')->constrained('dokter')->nullOnDelete();
        $table->boolean('aktif')->default(true)->after('dokter_id');
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropConstrainedForeignId('dokter_id');
        $table->dropColumn('aktif');
    });
}
```

- [ ] **Step 4: Perbarui model User**

Di `app/Models/User.php`, tambahkan trait dan relasi:

```php
use App\Models\Dokter;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = ['name', 'email', 'password', 'dokter_id', 'aktif'];

    public function dokter(): BelongsTo
    {
        return $this->belongsTo(Dokter::class);
    }
}
```

Pastikan `casts()` tetap memuat `'password' => 'hashed'` bawaan Laravel.

- [ ] **Step 5: Tulis controller autentikasi**

`app/Http/Controllers/AutentikasiController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AutentikasiController extends Controller
{
    public function formMasuk(): View
    {
        return view('auth.masuk');
    }

    public function masuk(Request $request): RedirectResponse
    {
        $kredensial = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak sah.',
            'password.required' => 'Kata sandi wajib diisi.',
        ]);

        if (! Auth::attempt($kredensial, $request->boolean('ingat'))) {
            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi tidak cocok.',
            ]);
        }

        if (! Auth::user()->aktif) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Akun ini sudah dinonaktifkan. Hubungi admin.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended('/beranda');
    }

    public function keluar(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('masuk');
    }
}
```

- [ ] **Step 6: Tulis view**

`resources/views/layouts/app.blade.php`:

```blade
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $judul ?? 'SIMRS' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-slate-100 text-slate-800">
    @auth
        <nav class="bg-white shadow px-6 py-3 flex items-center justify-between print:hidden">
            <a href="{{ route('beranda') }}" class="font-semibold">SIMRS</a>
            <div class="flex items-center gap-4 text-sm">
                <span>{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('keluar') }}">
                    @csrf
                    <button class="text-red-600">Keluar</button>
                </form>
            </div>
        </nav>
    @endauth

    <main class="p-6">{{ $slot }}</main>

    @livewireScripts
</body>
</html>
```

`resources/views/auth/masuk.blade.php`:

```blade
<x-layouts.app :judul="'Masuk — SIMRS'">
    <div class="max-w-sm mx-auto bg-white p-6 rounded shadow">
        <h1 class="text-lg font-semibold mb-4">Masuk SIMRS</h1>

        @if ($errors->any())
            <div class="mb-4 text-sm text-red-600">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="/masuk" class="space-y-3">
            @csrf
            <input name="email" type="email" placeholder="Email" value="{{ old('email') }}"
                   class="w-full border rounded px-3 py-2" required>
            <input name="password" type="password" placeholder="Kata sandi"
                   class="w-full border rounded px-3 py-2" required>
            <button class="w-full bg-blue-600 text-white rounded py-2">Masuk</button>
        </form>
    </div>
</x-layouts.app>
```

`resources/views/beranda.blade.php`:

```blade
<x-layouts.app :judul="'Beranda — SIMRS'">
    <h1 class="text-xl font-semibold">Selamat datang, {{ auth()->user()->name }}</h1>
    <p class="text-sm text-slate-600">Peran: {{ auth()->user()->getRoleNames()->implode(', ') }}</p>
</x-layouts.app>
```

- [ ] **Step 7: Daftarkan rute**

Di `routes/web.php`:

```php
use App\Http\Controllers\AutentikasiController;

Route::middleware('guest')->group(function () {
    Route::get('/masuk', [AutentikasiController::class, 'formMasuk'])->name('masuk');
    Route::post('/masuk', [AutentikasiController::class, 'masuk']);
});

Route::middleware('auth')->group(function () {
    Route::post('/keluar', [AutentikasiController::class, 'keluar'])->name('keluar');
    Route::view('/beranda', 'beranda')->name('beranda');
});
```

Agar tamu yang membuka halaman terlindungi diarahkan ke `/masuk` (bukan `/login` bawaan Laravel), tambahkan di `bootstrap/app.php` dalam `->withMiddleware(function (Middleware $middleware) { ... })`:

```php
$middleware->redirectGuestsTo(fn () => route('masuk'));
```

- [ ] **Step 8: Tulis seeder peran**

`database/seeders/PeranSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Enums\Peran;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class PeranSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Peran::semua() as $peran) {
            Role::findOrCreate($peran);
        }
    }
}
```

Panggil dari `DatabaseSeeder::run()` dengan `$this->call(PeranSeeder::class);`.

- [ ] **Step 9: Jalankan test sampai lulus**

Run: `php artisan test --filter=AutentikasiTest`
Diharapkan: PASS, 5 test.

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "feat: tambah autentikasi, 6 peran, dan kaitan pengguna ke data dokter"
```

---

### Task 5: Layanan penomoran

**Files:**
- Create: migration `create_nomor_counter_table`, `app/Services/PencatatNomor.php`, `app/Services/NomorRekamMedis.php`, `app/Services/NomorDokumen.php`
- Test: `tests/Unit/PencatatNomorTest.php`, `tests/Feature/PenomoranTest.php`

**Interfaces:**
- Consumes: —
- Produces:
  - `PencatatNomor::ambil(string $kunci, string $periode = 'global'): int` — mengembalikan nomor urut berikutnya, aman dari tabrakan.
  - `NomorRekamMedis::berikutnya(): string` — 6 digit dengan nol di depan, contoh `'000137'`.
  - `NomorDokumen::berikutnya(string $jenis, ?CarbonInterface $tanggal = null): string` — `$jenis` salah satu dari `'kunjungan'`, `'resep'`, `'tagihan'`, `'kuitansi'`; hasil berformat `KJ-20260818-0042`.

Kolom `periode` sengaja berupa string, bukan tanggal nullable: MySQL memperlakukan NULL sebagai nilai yang selalu berbeda, sehingga unique constraint tidak akan menjaga baris global. Nilai `'global'` dipakai untuk penghitung yang tidak direset harian.

- [ ] **Step 1: Tulis test unit yang gagal**

Buat `tests/Unit/PencatatNomorTest.php`:

```php
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
```

Catatan untuk pelaksana: test "sepuluh pengambilan" berjalan berurutan, jadi ia membuktikan penghitungnya benar, bukan membuktikan ketahanan terhadap paralelisme. Yang membuktikan ketahanan itu adalah test unique constraint di atas — bila logika penguncian gagal, baris kembar akan ditolak database, bukan menghasilkan nomor kembar yang lolos diam-diam.

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=PencatatNomorTest`
Diharapkan: FAIL dengan "Class App\Services\PencatatNomor not found".

- [ ] **Step 3: Tulis migration penghitung**

```bash
php artisan make:migration create_nomor_counter_table
```

```php
Schema::create('nomor_counter', function (Blueprint $table) {
    $table->id();
    $table->string('kunci', 50);
    $table->string('periode', 10)->default('global');
    $table->unsignedBigInteger('nilai')->default(0);
    $table->timestamps();
    $table->unique(['kunci', 'periode']);
});
```

- [ ] **Step 4: Tulis PencatatNomor**

`app/Services/PencatatNomor.php`:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PencatatNomor
{
    public function ambil(string $kunci, string $periode = 'global'): int
    {
        DB::table('nomor_counter')->insertOrIgnore([
            'kunci' => $kunci,
            'periode' => $periode,
            'nilai' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::transaction(function () use ($kunci, $periode) {
            $baris = DB::table('nomor_counter')
                ->where('kunci', $kunci)
                ->where('periode', $periode)
                ->lockForUpdate()
                ->first();

            $berikutnya = (int) $baris->nilai + 1;

            DB::table('nomor_counter')
                ->where('id', $baris->id)
                ->update(['nilai' => $berikutnya, 'updated_at' => now()]);

            return $berikutnya;
        });
    }
}
```

`insertOrIgnore` di luar transaksi membuat baris penghitung ada lebih dulu, sehingga `lockForUpdate()` selalu punya baris untuk dikunci. Tanpa itu, dua proses yang sama-sama tidak menemukan baris akan sama-sama mencoba menyisipkan dan salah satunya gagal.

- [ ] **Step 5: Jalankan test unit sampai lulus**

Run: `php artisan test --filter=PencatatNomorTest`
Diharapkan: PASS, 6 test.

- [ ] **Step 6: Tulis test untuk NomorRekamMedis dan NomorDokumen**

Buat `tests/Feature/PenomoranTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Services\NomorDokumen;
use App\Services\NomorRekamMedis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PenomoranTest extends TestCase
{
    use RefreshDatabase;

    public function test_nomor_rekam_medis_pertama_adalah_enam_digit_berisi_satu(): void
    {
        $this->assertSame('000001', app(NomorRekamMedis::class)->berikutnya());
    }

    public function test_nomor_rekam_medis_berurutan_tanpa_pengulangan(): void
    {
        $layanan = app(NomorRekamMedis::class);

        $this->assertSame('000001', $layanan->berikutnya());
        $this->assertSame('000002', $layanan->berikutnya());
        $this->assertSame('000003', $layanan->berikutnya());
    }

    public function test_nomor_kunjungan_memuat_tanggal_dan_urutan_harian(): void
    {
        $layanan = app(NomorDokumen::class);
        $tanggal = Carbon::parse('2026-08-18');

        $this->assertSame('KJ-20260818-0001', $layanan->berikutnya('kunjungan', $tanggal));
        $this->assertSame('KJ-20260818-0002', $layanan->berikutnya('kunjungan', $tanggal));
    }

    public function test_urutan_dokumen_mulai_dari_satu_lagi_pada_hari_berikutnya(): void
    {
        $layanan = app(NomorDokumen::class);

        $layanan->berikutnya('tagihan', Carbon::parse('2026-08-18'));

        $this->assertSame('TG-20260819-0001', $layanan->berikutnya('tagihan', Carbon::parse('2026-08-19')));
    }

    public function test_setiap_jenis_dokumen_punya_awalan_sendiri(): void
    {
        $layanan = app(NomorDokumen::class);
        $tanggal = Carbon::parse('2026-08-18');

        $this->assertStringStartsWith('RS-', $layanan->berikutnya('resep', $tanggal));
        $this->assertStringStartsWith('KW-', $layanan->berikutnya('kuitansi', $tanggal));
    }

    public function test_jenis_dokumen_tak_dikenal_ditolak(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(NomorDokumen::class)->berikutnya('faktur');
    }
}
```

- [ ] **Step 7: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=PenomoranTest`
Diharapkan: FAIL dengan "Class App\Services\NomorRekamMedis not found".

- [ ] **Step 8: Tulis kedua layanan**

`app/Services/NomorRekamMedis.php`:

```php
<?php

namespace App\Services;

class NomorRekamMedis
{
    public function __construct(private readonly PencatatNomor $pencatat) {}

    public function berikutnya(): string
    {
        return str_pad((string) $this->pencatat->ambil('rm'), 6, '0', STR_PAD_LEFT);
    }
}
```

`app/Services/NomorDokumen.php`:

```php
<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class NomorDokumen
{
    private const AWALAN = [
        'kunjungan' => 'KJ',
        'resep' => 'RS',
        'tagihan' => 'TG',
        'kuitansi' => 'KW',
    ];

    public function __construct(private readonly PencatatNomor $pencatat) {}

    public function berikutnya(string $jenis, ?CarbonInterface $tanggal = null): string
    {
        if (! array_key_exists($jenis, self::AWALAN)) {
            throw new InvalidArgumentException("Jenis dokumen tidak dikenal: {$jenis}");
        }

        $tanggal ??= Carbon::today();
        $periode = $tanggal->format('Y-m-d');
        $urutan = $this->pencatat->ambil("dokumen:{$jenis}", $periode);

        return sprintf(
            '%s-%s-%04d',
            self::AWALAN[$jenis],
            $tanggal->format('Ymd'),
            $urutan
        );
    }
}
```

- [ ] **Step 9: Jalankan test sampai lulus**

Run: `php artisan test --filter=PenomoranTest`
Diharapkan: PASS, 6 test.

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "feat: tambah layanan penomoran tahan tabrakan untuk rekam medis dan dokumen"
```

---
### Task 6: Pasien dan pendaftarannya

**Files:**
- Create: migration `create_pasien_table`, `app/Models/Pasien.php`, `database/factories/PasienFactory.php`, `app/Services/PendaftaranPasien.php`
- Test: `tests/Feature/PasienTest.php`

**Interfaces:**
- Consumes: `NomorRekamMedis` (Task 5), `JenisKelamin` (Task 2)
- Produces:
  - Model `Pasien` (tabel `pasien`, `SoftDeletes`, cast `jenis_kelamin` ke `JenisKelamin`, `tanggal_lahir` ke `date`).
  - `PendaftaranPasien::daftarkan(array $data): Pasien` — memvalidasi lalu memberi `no_rm`.
  - `PendaftaranPasien::perbarui(Pasien $pasien, array $data): Pasien` — aturan sama, kecuali NIK miliknya sendiri diizinkan.
  - `PendaftaranPasien::aturan(): array` — dipakai ulang komponen Livewire di Task 15 supaya aturan validasi hanya ditulis di satu tempat.

Memenuhi aturan 1, 2, dan 3.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/PasienTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Pasien;
use App\Services\PendaftaranPasien;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PasienTest extends TestCase
{
    use RefreshDatabase;

    private function layanan(): PendaftaranPasien
    {
        return app(PendaftaranPasien::class);
    }

    private function dataSah(array $ganti = []): array
    {
        return array_merge([
            'nik' => '3202011203900001',
            'nama' => 'Siti Aminah',
            'tempat_lahir' => 'Kabupaten Sampel',
            'tanggal_lahir' => '1990-03-12',
            'jenis_kelamin' => 'P',
            'alamat' => 'Jl. Melati No. 12',
            'kelurahan' => 'Sukamaju',
            'kecamatan' => 'Sukamaju',
            'kabupaten' => 'Kabupaten Sampel',
            'no_hp' => '081234567890',
        ], $ganti);
    }

    public function test_pendaftaran_pasien_baru_mendapat_nomor_rekam_medis_berurutan(): void
    {
        $pertama = $this->layanan()->daftarkan($this->dataSah());
        $kedua = $this->layanan()->daftarkan($this->dataSah(['nik' => '3202011203900002']));

        $this->assertSame('000001', $pertama->no_rm);
        $this->assertSame('000002', $kedua->no_rm);
    }

    public function test_nik_kurang_dari_16_digit_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        $this->layanan()->daftarkan($this->dataSah(['nik' => '320201120390']));
    }

    public function test_nik_berisi_huruf_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        $this->layanan()->daftarkan($this->dataSah(['nik' => '32020112039000AB']));
    }

    public function test_nik_yang_sudah_terdaftar_ditolak(): void
    {
        $this->layanan()->daftarkan($this->dataSah());

        $this->expectException(ValidationException::class);

        $this->layanan()->daftarkan($this->dataSah(['nama' => 'Orang Lain']));
    }

    public function test_tanggal_lahir_di_masa_depan_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        $this->layanan()->daftarkan($this->dataSah(['tanggal_lahir' => now()->addDay()->toDateString()]));
    }

    public function test_jenis_kelamin_selain_l_dan_p_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        $this->layanan()->daftarkan($this->dataSah(['jenis_kelamin' => 'X']));
    }

    public function test_pembaruan_boleh_memakai_nik_miliknya_sendiri(): void
    {
        $pasien = $this->layanan()->daftarkan($this->dataSah());

        $diperbarui = $this->layanan()->perbarui($pasien, $this->dataSah(['nama' => 'Siti Aminah binti Umar']));

        $this->assertSame('Siti Aminah binti Umar', $diperbarui->nama);
        $this->assertSame('000001', $diperbarui->no_rm);
    }

    public function test_nomor_rekam_medis_tidak_dipakai_ulang_setelah_pasien_dihapus(): void
    {
        $pertama = $this->layanan()->daftarkan($this->dataSah());
        $pertama->delete();

        $kedua = $this->layanan()->daftarkan($this->dataSah(['nik' => '3202011203900002']));

        $this->assertSame('000002', $kedua->no_rm);
        $this->assertSoftDeleted('pasien', ['id' => $pertama->id]);
    }

    public function test_pasien_bisa_dicari_berdasarkan_nik_nama_atau_nomor_rm(): void
    {
        $this->layanan()->daftarkan($this->dataSah());

        $this->assertSame(1, Pasien::cari('aminah')->count());
        $this->assertSame(1, Pasien::cari('3202011203900001')->count());
        $this->assertSame(1, Pasien::cari('000001')->count());
        $this->assertSame(0, Pasien::cari('tidak ada')->count());
    }
}
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=PasienTest`
Diharapkan: FAIL dengan "Class App\Services\PendaftaranPasien not found".

- [ ] **Step 3: Tulis migration**

```bash
php artisan make:migration create_pasien_table
```

```php
Schema::create('pasien', function (Blueprint $table) {
    $table->id();
    $table->string('no_rm', 10)->unique();
    $table->string('nik', 16)->unique();
    $table->string('nama', 100);
    $table->string('tempat_lahir', 60)->nullable();
    $table->date('tanggal_lahir');
    $table->enum('jenis_kelamin', ['L', 'P']);
    $table->string('alamat', 255);
    $table->string('rt', 3)->nullable();
    $table->string('rw', 3)->nullable();
    $table->string('kelurahan', 60)->nullable();
    $table->string('kecamatan', 60)->nullable();
    $table->string('kabupaten', 60)->nullable();
    $table->string('no_hp', 20)->nullable();
    $table->string('pekerjaan', 60)->nullable();
    $table->string('agama', 20)->nullable();
    $table->string('status_perkawinan', 20)->nullable();
    $table->string('nama_penanggung_jawab', 100)->nullable();
    $table->string('hubungan_penanggung_jawab', 30)->nullable();
    $table->timestamps();
    $table->softDeletes();
    $table->index('nama');
});
```

- [ ] **Step 4: Tulis model Pasien**

`app/Models/Pasien.php`:

```php
<?php

namespace App\Models;

use App\Enums\JenisKelamin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pasien extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pasien';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
            'jenis_kelamin' => JenisKelamin::class,
        ];
    }

    public function kunjungan(): HasMany
    {
        return $this->hasMany(Kunjungan::class);
    }

    public function scopeCari(Builder $query, string $kata): Builder
    {
        return $query->where(function (Builder $q) use ($kata) {
            $q->where('nama', 'like', "%{$kata}%")
                ->orWhere('nik', $kata)
                ->orWhere('no_rm', $kata);
        });
    }

    public function umur(): int
    {
        return $this->tanggal_lahir->age;
    }
}
```

Relasi `kunjungan()` menunjuk model yang baru dibuat di Task 8; sampai tugas itu selesai, relasi ini belum dipanggil test mana pun.

- [ ] **Step 5: Tulis factory**

`database/factories/PasienFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Pasien;
use App\Services\NomorRekamMedis;
use Illuminate\Database\Eloquent\Factories\Factory;

class PasienFactory extends Factory
{
    protected $model = Pasien::class;

    public function definition(): array
    {
        return [
            'no_rm' => app(NomorRekamMedis::class)->berikutnya(),
            'nik' => $this->faker->unique()->numerify('################'),
            'nama' => $this->faker->name(),
            'tempat_lahir' => $this->faker->city(),
            'tanggal_lahir' => $this->faker->dateTimeBetween('-80 years', '-1 year')->format('Y-m-d'),
            'jenis_kelamin' => $this->faker->randomElement(['L', 'P']),
            'alamat' => $this->faker->streetAddress(),
            'kelurahan' => $this->faker->citySuffix(),
            'kecamatan' => 'Sukamaju',
            'kabupaten' => 'Kabupaten Sampel',
            'no_hp' => $this->faker->numerify('08##########'),
        ];
    }
}
```

- [ ] **Step 6: Tulis PendaftaranPasien**

`app/Services/PendaftaranPasien.php`:

```php
<?php

namespace App\Services;

use App\Models\Pasien;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PendaftaranPasien
{
    public function __construct(private readonly NomorRekamMedis $nomorRekamMedis) {}

    public function daftarkan(array $data): Pasien
    {
        $tervalidasi = Validator::make($data, $this->aturan(), $this->pesan())->validate();

        return DB::transaction(function () use ($tervalidasi) {
            $tervalidasi['no_rm'] = $this->nomorRekamMedis->berikutnya();

            return Pasien::create($tervalidasi);
        });
    }

    public function perbarui(Pasien $pasien, array $data): Pasien
    {
        $aturan = $this->aturan();
        $aturan['nik'] = ['required', 'digits:16', Rule::unique('pasien', 'nik')->ignore($pasien->id)];

        $tervalidasi = Validator::make($data, $aturan, $this->pesan())->validate();

        $pasien->update($tervalidasi);

        return $pasien->refresh();
    }

    public function aturan(): array
    {
        return [
            'nik' => ['required', 'digits:16', 'unique:pasien,nik'],
            'nama' => ['required', 'string', 'max:100'],
            'tempat_lahir' => ['nullable', 'string', 'max:60'],
            'tanggal_lahir' => ['required', 'date', 'before_or_equal:today'],
            'jenis_kelamin' => ['required', 'in:L,P'],
            'alamat' => ['required', 'string', 'max:255'],
            'rt' => ['nullable', 'string', 'max:3'],
            'rw' => ['nullable', 'string', 'max:3'],
            'kelurahan' => ['nullable', 'string', 'max:60'],
            'kecamatan' => ['nullable', 'string', 'max:60'],
            'kabupaten' => ['nullable', 'string', 'max:60'],
            'no_hp' => ['nullable', 'string', 'max:20'],
            'pekerjaan' => ['nullable', 'string', 'max:60'],
            'agama' => ['nullable', 'string', 'max:20'],
            'status_perkawinan' => ['nullable', 'string', 'max:20'],
            'nama_penanggung_jawab' => ['nullable', 'string', 'max:100'],
            'hubungan_penanggung_jawab' => ['nullable', 'string', 'max:30'],
        ];
    }

    private function pesan(): array
    {
        return [
            'nik.required' => 'NIK wajib diisi.',
            'nik.digits' => 'NIK harus terdiri dari 16 angka.',
            'nik.unique' => 'NIK ini sudah terdaftar atas nama pasien lain.',
            'nama.required' => 'Nama pasien wajib diisi.',
            'tanggal_lahir.required' => 'Tanggal lahir wajib diisi.',
            'tanggal_lahir.before_or_equal' => 'Tanggal lahir tidak boleh melewati hari ini.',
            'jenis_kelamin.in' => 'Jenis kelamin hanya boleh L atau P.',
            'alamat.required' => 'Alamat wajib diisi.',
        ];
    }
}
```

- [ ] **Step 7: Jalankan test sampai lulus**

Run: `php artisan test --filter=PasienTest`
Diharapkan: PASS, 9 test.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: tambah data pasien dengan validasi NIK dan penomoran rekam medis"
```

---

### Task 7: Jejak audit

**Files:**
- Create: migration `create_audit_logs_table`, `app/Models/AuditLog.php`, `app/Observers/PencatatAudit.php`, `app/Support/KonteksAudit.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/AuditTest.php`

**Interfaces:**
- Consumes: `Pasien` (Task 6)
- Produces:
  - Model `AuditLog` dengan kolom `user_id`, `aksi`, `model_tipe`, `model_id`, `perubahan` (array), `alasan`, `ip`, `user_agent`.
  - `KonteksAudit::dengan(string $alasan, Closure $aksi): mixed` — menjalankan `$aksi` sambil menandai alasan yang ikut tercatat di audit.
  - `PencatatAudit` observer, dipasang di `AppServiceProvider` ke seluruh model klinis. Setiap model baru yang dibuat di tugas berikutnya (`Kunjungan`, `Pemeriksaan`, `Diagnosa`, `Tagihan`) wajib ditambahkan ke daftar itu.

Memenuhi aturan 19.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/AuditTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Pasien;
use App\Models\User;
use App\Support\KonteksAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_pembuatan_pasien_tercatat_di_audit_log(): void
    {
        $pasien = Pasien::factory()->create();

        $this->assertDatabaseHas('audit_logs', [
            'aksi' => 'create',
            'model_tipe' => Pasien::class,
            'model_id' => $pasien->id,
        ]);
    }

    public function test_perubahan_data_pasien_mencatat_nilai_sebelum_dan_sesudah(): void
    {
        $pasien = Pasien::factory()->create(['nama' => 'Nama Lama']);
        $pasien->update(['nama' => 'Nama Baru']);

        $catatan = AuditLog::where('aksi', 'update')->latest('id')->first();

        $this->assertSame('Nama Lama', $catatan->perubahan['sebelum']['nama']);
        $this->assertSame('Nama Baru', $catatan->perubahan['sesudah']['nama']);
    }

    public function test_audit_mencatat_pengguna_yang_melakukan(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $pasien = Pasien::factory()->create();

        $this->assertSame($user->id, AuditLog::where('model_id', $pasien->id)->first()->user_id);
    }

    public function test_alasan_perubahan_ikut_tercatat(): void
    {
        $pasien = Pasien::factory()->create();

        KonteksAudit::dengan('Salah ketik nama saat pendaftaran', function () use ($pasien) {
            $pasien->update(['nama' => 'Nama Terkoreksi']);
        });

        $catatan = AuditLog::where('aksi', 'update')->latest('id')->first();

        $this->assertSame('Salah ketik nama saat pendaftaran', $catatan->alasan);
    }

    public function test_alasan_kembali_kosong_setelah_konteks_selesai(): void
    {
        $pasien = Pasien::factory()->create();

        KonteksAudit::dengan('Alasan pertama', fn () => $pasien->update(['nama' => 'Satu']));
        $pasien->update(['nama' => 'Dua']);

        $catatan = AuditLog::where('aksi', 'update')->latest('id')->first();

        $this->assertNull($catatan->alasan);
    }

    public function test_penghapusan_pasien_tercatat_sebagai_delete(): void
    {
        $pasien = Pasien::factory()->create();
        $pasien->delete();

        $this->assertDatabaseHas('audit_logs', [
            'aksi' => 'delete',
            'model_id' => $pasien->id,
        ]);
    }
}
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=AuditTest`
Diharapkan: FAIL — tabel `audit_logs` belum ada.

- [ ] **Step 3: Tulis migration**

```bash
php artisan make:migration create_audit_logs_table
```

```php
Schema::create('audit_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->string('aksi', 20);
    $table->string('model_tipe', 100);
    $table->unsignedBigInteger('model_id');
    $table->json('perubahan')->nullable();
    $table->string('alasan', 255)->nullable();
    $table->string('ip', 45)->nullable();
    $table->string('user_agent', 255)->nullable();
    $table->timestamp('created_at')->useCurrent();
    $table->index(['model_tipe', 'model_id']);
});
```

- [ ] **Step 4: Tulis model AuditLog**

`app/Models/AuditLog.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['perubahan' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 5: Tulis KonteksAudit**

`app/Support/KonteksAudit.php`:

```php
<?php

namespace App\Support;

use Closure;

class KonteksAudit
{
    private static ?string $alasan = null;

    public static function dengan(string $alasan, Closure $aksi): mixed
    {
        $sebelumnya = self::$alasan;
        self::$alasan = $alasan;

        try {
            return $aksi();
        } finally {
            self::$alasan = $sebelumnya;
        }
    }

    public static function alasan(): ?string
    {
        return self::$alasan;
    }
}
```

`finally` memastikan alasan dikembalikan meskipun aksinya melempar exception — tanpa itu, satu kegagalan akan membuat semua perubahan berikutnya tercatat dengan alasan yang salah.

- [ ] **Step 6: Tulis observer**

`app/Observers/PencatatAudit.php`:

```php
<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Support\KonteksAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class PencatatAudit
{
    public function created(Model $model): void
    {
        $this->catat('create', $model, ['sesudah' => $model->getAttributes()]);
    }

    public function updated(Model $model): void
    {
        $sesudah = $model->getChanges();
        unset($sesudah['updated_at']);

        if ($sesudah === []) {
            return;
        }

        $this->catat('update', $model, [
            'sebelum' => array_intersect_key($model->getOriginal(), $sesudah),
            'sesudah' => $sesudah,
        ]);
    }

    public function deleted(Model $model): void
    {
        $this->catat('delete', $model, null);
    }

    public function restored(Model $model): void
    {
        $this->catat('restore', $model, null);
    }

    private function catat(string $aksi, Model $model, ?array $perubahan): void
    {
        AuditLog::create([
            'user_id' => Auth::id(),
            'aksi' => $aksi,
            'model_tipe' => $model::class,
            'model_id' => $model->getKey(),
            'perubahan' => $perubahan,
            'alasan' => KonteksAudit::alasan(),
            'ip' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}
```

- [ ] **Step 7: Pasang observer**

Di `app/Providers/AppServiceProvider.php` method `boot()`:

```php
use App\Models\Pasien;
use App\Observers\PencatatAudit;

public function boot(): void
{
    foreach ($this->modelTerauditkan() as $model) {
        $model::observe(PencatatAudit::class);
    }
}

/**
 * Model yang setiap perubahannya wajib berjejak (aturan 19).
 * Tambahkan model klinis baru ke daftar ini saat dibuat.
 */
private function modelTerauditkan(): array
{
    return [Pasien::class];
}
```

- [ ] **Step 8: Jalankan test sampai lulus**

Run: `php artisan test --filter=AuditTest`
Diharapkan: PASS, 6 test.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: tambah jejak audit otomatis untuk perubahan data klinis"
```

---
### Task 8: Kunjungan dan antrian

**Files:**
- Create: migration `create_kunjungan_dan_antrian_tables`, `app/Models/Kunjungan.php`, `app/Models/Antrian.php`, factory keduanya, `app/Services/NomorAntrian.php`, `app/Services/PendaftaranKunjungan.php`
- Modify: `app/Providers/AppServiceProvider.php` (daftar model terauditkan)
- Test: `tests/Feature/AntrianTest.php`, `tests/Feature/KunjunganTest.php`

**Interfaces:**
- Consumes: `PencatatNomor`, `NomorDokumen` (Task 5), `Pasien` (Task 6), `Poli`/`Dokter`/`Penjamin` (Task 3), `StatusKunjungan`/`StatusAntrian` (Task 2)
- Produces:
  - `NomorAntrian::berikutnya(int $poliId, CarbonInterface $tanggal): int`
  - `PendaftaranKunjungan::daftarkan(array $data, ?User $petugas = null): Kunjungan` — membuat kunjungan beserta antriannya dalam satu transaksi.
  - `PendaftaranKunjungan::batalkan(Kunjungan $kunjungan): void`
  - Model `Kunjungan` (relasi `pasien`, `poli`, `dokter`, `penjamin`, `antrian`, `pemeriksaan`, `diagnosa`, `tindakan`, `resep`, `tagihan`; scope `aktif()`), model `Antrian` dengan `kode(): string` berformat `UMU-001`.

Memenuhi aturan 4, 5, 6, 7, dan 17.

- [ ] **Step 1: Tulis test antrian yang gagal**

Buat `tests/Feature/AntrianTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Antrian;
use App\Models\Dokter;
use App\Models\Pasien;
use App\Models\Penjamin;
use App\Models\Poli;
use App\Services\PendaftaranKunjungan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AntrianTest extends TestCase
{
    use RefreshDatabase;

    private Poli $poli;
    private Dokter $dokter;
    private Penjamin $umum;

    protected function setUp(): void
    {
        parent::setUp();

        $this->poli = Poli::factory()->create(['kode' => 'UMU', 'nama' => 'Poli Umum']);
        $this->dokter = Dokter::factory()->create(['poli_id' => $this->poli->id]);
        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
    }

    private function daftarkan(array $ganti = []): \App\Models\Kunjungan
    {
        return app(PendaftaranKunjungan::class)->daftarkan(array_merge([
            'pasien_id' => Pasien::factory()->create()->id,
            'poli_id' => $this->poli->id,
            'dokter_id' => $this->dokter->id,
            'penjamin_id' => $this->umum->id,
            'tanggal' => '2026-08-18',
        ], $ganti));
    }

    public function test_antrian_pertama_pada_hari_itu_mendapat_nomor_1(): void
    {
        $this->assertSame(1, $this->daftarkan()->antrian->nomor);
    }

    public function test_nomor_antrian_berurutan_untuk_pasien_berikutnya(): void
    {
        $this->daftarkan();

        $this->assertSame(2, $this->daftarkan()->antrian->nomor);
    }

    public function test_sepuluh_pendaftaran_menghasilkan_sepuluh_nomor_antrian_berbeda(): void
    {
        $nomor = [];

        for ($i = 0; $i < 10; $i++) {
            $nomor[] = $this->daftarkan()->antrian->nomor;
        }

        $this->assertCount(10, array_unique($nomor));
    }

    public function test_nomor_antrian_mulai_dari_1_lagi_pada_hari_berikutnya(): void
    {
        $this->daftarkan(['tanggal' => '2026-08-18']);

        $this->assertSame(1, $this->daftarkan(['tanggal' => '2026-08-19'])->antrian->nomor);
    }

    public function test_setiap_poli_punya_urutan_antrian_sendiri(): void
    {
        $this->daftarkan();

        $poliGigi = Poli::factory()->create(['kode' => 'GIG']);
        $dokterGigi = Dokter::factory()->create(['poli_id' => $poliGigi->id]);

        $kunjungan = $this->daftarkan(['poli_id' => $poliGigi->id, 'dokter_id' => $dokterGigi->id]);

        $this->assertSame(1, $kunjungan->antrian->nomor);
    }

    public function test_database_menolak_dua_antrian_dengan_nomor_sama_pada_poli_dan_tanggal_sama(): void
    {
        $kunjungan = $this->daftarkan();

        $this->expectException(QueryException::class);

        Antrian::create([
            'kunjungan_id' => $kunjungan->id,
            'poli_id' => $this->poli->id,
            'tanggal' => '2026-08-18',
            'nomor' => 1,
        ]);
    }

    public function test_kode_antrian_memakai_kode_poli_dan_tiga_digit(): void
    {
        $this->assertSame('UMU-001', $this->daftarkan()->antrian->kode());
    }

    public function test_antrian_kemarin_tidak_ikut_tampil_di_daftar_hari_ini(): void
    {
        $this->daftarkan(['tanggal' => '2026-08-17']);
        $this->daftarkan(['tanggal' => '2026-08-18']);

        $hariIni = Antrian::whereDate('tanggal', '2026-08-18')->get();

        $this->assertCount(1, $hariIni);
    }
}
```

- [ ] **Step 2: Tulis test kunjungan yang gagal**

Buat `tests/Feature/KunjunganTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\StatusKunjungan;
use App\Models\Dokter;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\Penjamin;
use App\Models\Poli;
use App\Services\PendaftaranKunjungan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class KunjunganTest extends TestCase
{
    use RefreshDatabase;

    private Poli $poli;
    private Dokter $dokter;
    private Penjamin $umum;
    private Penjamin $bpjs;
    private Pasien $pasien;

    protected function setUp(): void
    {
        parent::setUp();

        $this->poli = Poli::factory()->create(['kode' => 'UMU']);
        $this->dokter = Dokter::factory()->create(['poli_id' => $this->poli->id]);
        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $this->bpjs = Penjamin::factory()->create(['kode' => 'BPJS', 'jenis' => 'penjamin']);
        $this->pasien = Pasien::factory()->create();
    }

    private function daftarkan(array $ganti = []): Kunjungan
    {
        return app(PendaftaranKunjungan::class)->daftarkan(array_merge([
            'pasien_id' => $this->pasien->id,
            'poli_id' => $this->poli->id,
            'dokter_id' => $this->dokter->id,
            'penjamin_id' => $this->umum->id,
            'tanggal' => '2026-08-18',
        ], $ganti));
    }

    public function test_kunjungan_baru_bernomor_dan_berstatus_terdaftar(): void
    {
        $kunjungan = $this->daftarkan();

        $this->assertSame('KJ-20260818-0001', $kunjungan->no_kunjungan);
        $this->assertSame(StatusKunjungan::Terdaftar, $kunjungan->status);
    }

    public function test_kunjungan_pertama_pasien_ditandai_baru_dan_berikutnya_lama(): void
    {
        $pertama = $this->daftarkan();
        $this->assertSame('baru', $pertama->jenis_kunjungan);

        $pertama->update(['status' => StatusKunjungan::Selesai]);

        $this->assertSame('lama', $this->daftarkan(['tanggal' => '2026-08-19'])->jenis_kunjungan);
    }

    public function test_pasien_tidak_bisa_punya_dua_kunjungan_aktif_di_poli_yang_sama(): void
    {
        $this->daftarkan();

        $this->expectException(ValidationException::class);

        $this->daftarkan();
    }

    public function test_pasien_bisa_mendaftar_di_poli_lain_pada_hari_yang_sama(): void
    {
        $this->daftarkan();

        $poliGigi = Poli::factory()->create(['kode' => 'GIG']);
        $dokterGigi = Dokter::factory()->create(['poli_id' => $poliGigi->id]);

        $kedua = $this->daftarkan(['poli_id' => $poliGigi->id, 'dokter_id' => $dokterGigi->id]);

        $this->assertSame(StatusKunjungan::Terdaftar, $kedua->status);
    }

    public function test_pasien_bisa_mendaftar_lagi_setelah_kunjungan_sebelumnya_selesai(): void
    {
        $pertama = $this->daftarkan();
        $pertama->update(['status' => StatusKunjungan::Selesai]);

        $this->assertNotNull($this->daftarkan()->id);
    }

    public function test_kunjungan_bpjs_tanpa_nomor_kartu_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        $this->daftarkan(['penjamin_id' => $this->bpjs->id]);
    }

    public function test_kunjungan_bpjs_dengan_nomor_kartu_diterima(): void
    {
        $kunjungan = $this->daftarkan([
            'penjamin_id' => $this->bpjs->id,
            'no_kartu_penjamin' => '0001234567890',
        ]);

        $this->assertSame('0001234567890', $kunjungan->no_kartu_penjamin);
    }

    public function test_kunjungan_umum_tidak_wajib_nomor_kartu(): void
    {
        $this->assertNull($this->daftarkan()->no_kartu_penjamin);
    }

    public function test_dokter_harus_bertugas_di_poli_yang_dipilih(): void
    {
        $poliLain = Poli::factory()->create(['kode' => 'ANK']);

        $this->expectException(ValidationException::class);

        $this->daftarkan(['poli_id' => $poliLain->id]);
    }

    public function test_kunjungan_bisa_dibatalkan_selama_masih_terdaftar(): void
    {
        $kunjungan = $this->daftarkan();

        app(PendaftaranKunjungan::class)->batalkan($kunjungan);

        $this->assertSame(StatusKunjungan::Batal, $kunjungan->refresh()->status);
    }

    public function test_kunjungan_yang_sudah_diperiksa_tidak_bisa_dibatalkan(): void
    {
        $kunjungan = $this->daftarkan();
        $kunjungan->update(['status' => StatusKunjungan::DiperiksaPerawat]);

        $this->expectException(RuntimeException::class);

        app(PendaftaranKunjungan::class)->batalkan($kunjungan);
    }
}
```

- [ ] **Step 3: Jalankan kedua test untuk memastikan gagal**

Run: `php artisan test --filter="AntrianTest|KunjunganTest"`
Diharapkan: FAIL dengan "Class App\Services\PendaftaranKunjungan not found".

- [ ] **Step 4: Tulis migration**

```bash
php artisan make:migration create_kunjungan_dan_antrian_tables
```

```php
Schema::create('kunjungan', function (Blueprint $table) {
    $table->id();
    $table->string('no_kunjungan', 20)->unique();
    $table->foreignId('pasien_id')->constrained('pasien');
    $table->foreignId('poli_id')->constrained('poli');
    $table->foreignId('dokter_id')->constrained('dokter');
    $table->foreignId('penjamin_id')->constrained('penjamin');
    $table->string('no_kartu_penjamin', 30)->nullable();
    $table->enum('jenis_kunjungan', ['baru', 'lama']);
    $table->date('tanggal');
    $table->string('status', 20)->default('terdaftar');
    $table->timestamp('waktu_daftar')->nullable();
    $table->timestamp('waktu_selesai')->nullable();
    $table->foreignId('didaftarkan_oleh')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->softDeletes();
    $table->index(['tanggal', 'poli_id', 'status']);
});

Schema::create('antrian', function (Blueprint $table) {
    $table->id();
    $table->foreignId('kunjungan_id')->constrained('kunjungan')->cascadeOnDelete();
    $table->foreignId('poli_id')->constrained('poli');
    $table->date('tanggal');
    $table->unsignedSmallInteger('nomor');
    $table->string('status', 20)->default('menunggu');
    $table->timestamp('waktu_panggil')->nullable();
    $table->timestamps();
    $table->unique(['poli_id', 'tanggal', 'nomor']);
});
```

- [ ] **Step 5: Tulis model**

`app/Models/Kunjungan.php`:

```php
<?php

namespace App\Models;

use App\Enums\StatusKunjungan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kunjungan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'kunjungan';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'status' => StatusKunjungan::class,
            'waktu_daftar' => 'datetime',
            'waktu_selesai' => 'datetime',
        ];
    }

    public function pasien(): BelongsTo { return $this->belongsTo(Pasien::class); }
    public function poli(): BelongsTo { return $this->belongsTo(Poli::class); }
    public function dokter(): BelongsTo { return $this->belongsTo(Dokter::class); }
    public function penjamin(): BelongsTo { return $this->belongsTo(Penjamin::class); }
    public function antrian(): HasOne { return $this->hasOne(Antrian::class); }
    public function pemeriksaan(): HasOne { return $this->hasOne(Pemeriksaan::class); }
    public function diagnosa(): HasMany { return $this->hasMany(Diagnosa::class); }
    public function tindakan(): HasMany { return $this->hasMany(TindakanKunjungan::class); }
    public function resep(): HasOne { return $this->hasOne(Resep::class); }
    public function tagihan(): HasOne { return $this->hasOne(Tagihan::class); }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            StatusKunjungan::Selesai->value,
            StatusKunjungan::Batal->value,
        ]);
    }
}
```

Relasi `pemeriksaan`, `diagnosa`, `tindakan`, `resep`, dan `tagihan` menunjuk model yang dibuat di Task 9–13. Tulis sekarang agar tidak perlu menyunting model ini berulang kali; belum ada test yang memanggilnya sampai tugas tersebut dikerjakan.

`app/Models/Antrian.php`:

```php
<?php

namespace App\Models;

use App\Enums\StatusAntrian;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Antrian extends Model
{
    use HasFactory;

    protected $table = 'antrian';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'status' => StatusAntrian::class,
            'waktu_panggil' => 'datetime',
        ];
    }

    public function kunjungan(): BelongsTo { return $this->belongsTo(Kunjungan::class); }
    public function poli(): BelongsTo { return $this->belongsTo(Poli::class); }

    public function kode(): string
    {
        return $this->poli->kode.'-'.str_pad((string) $this->nomor, 3, '0', STR_PAD_LEFT);
    }
}
```

- [ ] **Step 6: Tulis NomorAntrian**

`app/Services/NomorAntrian.php`:

```php
<?php

namespace App\Services;

use Carbon\CarbonInterface;

class NomorAntrian
{
    public function __construct(private readonly PencatatNomor $pencatat) {}

    public function berikutnya(int $poliId, CarbonInterface $tanggal): int
    {
        return $this->pencatat->ambil("antrian:{$poliId}", $tanggal->format('Y-m-d'));
    }
}
```

- [ ] **Step 7: Tulis PendaftaranKunjungan**

`app/Services/PendaftaranKunjungan.php`:

```php
<?php

namespace App\Services;

use App\Enums\StatusAntrian;
use App\Enums\StatusKunjungan;
use App\Models\Antrian;
use App\Models\Dokter;
use App\Models\Kunjungan;
use App\Models\Penjamin;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PendaftaranKunjungan
{
    public function __construct(
        private readonly NomorDokumen $nomorDokumen,
        private readonly NomorAntrian $nomorAntrian,
    ) {}

    public function daftarkan(array $data, ?User $petugas = null): Kunjungan
    {
        $data['tanggal'] ??= Carbon::today()->toDateString();

        $tervalidasi = Validator::make($data, [
            'pasien_id' => ['required', 'exists:pasien,id'],
            'poli_id' => ['required', 'exists:poli,id'],
            'dokter_id' => ['required', 'exists:dokter,id'],
            'penjamin_id' => ['required', 'exists:penjamin,id'],
            'tanggal' => ['required', 'date'],
            'no_kartu_penjamin' => [
                Rule::requiredIf(fn () => Penjamin::find($data['penjamin_id'] ?? null)?->ditanggung() === true),
                'nullable', 'string', 'max:30',
            ],
        ], [
            'pasien_id.required' => 'Pasien wajib dipilih.',
            'poli_id.required' => 'Poli wajib dipilih.',
            'dokter_id.required' => 'Dokter wajib dipilih.',
            'penjamin_id.required' => 'Penjamin wajib dipilih.',
            'no_kartu_penjamin.required' => 'Nomor kartu penjamin wajib diisi untuk pasien dengan penjamin.',
        ])->validate();

        $this->pastikanDokterBertugasDiPoli($tervalidasi);
        $this->pastikanTidakAdaKunjunganAktif($tervalidasi);

        $tanggal = Carbon::parse($tervalidasi['tanggal']);

        return DB::transaction(function () use ($tervalidasi, $tanggal, $petugas) {
            $kunjungan = Kunjungan::create([
                ...$tervalidasi,
                'no_kunjungan' => $this->nomorDokumen->berikutnya('kunjungan', $tanggal),
                'jenis_kunjungan' => $this->jenisKunjungan((int) $tervalidasi['pasien_id']),
                'status' => StatusKunjungan::Terdaftar,
                'waktu_daftar' => now(),
                'didaftarkan_oleh' => $petugas?->id,
            ]);

            Antrian::create([
                'kunjungan_id' => $kunjungan->id,
                'poli_id' => $kunjungan->poli_id,
                'tanggal' => $tanggal->toDateString(),
                'nomor' => $this->nomorAntrian->berikutnya($kunjungan->poli_id, $tanggal),
                'status' => StatusAntrian::Menunggu,
            ]);

            return $kunjungan->load('antrian');
        });
    }

    public function batalkan(Kunjungan $kunjungan): void
    {
        DB::transaction(function () use ($kunjungan) {
            $terkunci = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->first();

            if ($terkunci->status !== StatusKunjungan::Terdaftar) {
                throw new RuntimeException(
                    'Kunjungan hanya bisa dibatalkan selama statusnya masih terdaftar.'
                );
            }

            $terkunci->update(['status' => StatusKunjungan::Batal]);
            $terkunci->antrian?->update(['status' => StatusAntrian::Terlewat]);
        });

        $kunjungan->refresh();
    }

    private function pastikanDokterBertugasDiPoli(array $data): void
    {
        $dokter = Dokter::find($data['dokter_id']);

        if ((int) $dokter->poli_id !== (int) $data['poli_id']) {
            throw ValidationException::withMessages([
                'dokter_id' => 'Dokter yang dipilih tidak bertugas di poli tersebut.',
            ]);
        }
    }

    private function pastikanTidakAdaKunjunganAktif(array $data): void
    {
        $ada = Kunjungan::aktif()
            ->where('pasien_id', $data['pasien_id'])
            ->where('poli_id', $data['poli_id'])
            ->whereDate('tanggal', $data['tanggal'])
            ->exists();

        if ($ada) {
            throw ValidationException::withMessages([
                'pasien_id' => 'Pasien ini masih punya kunjungan aktif di poli yang sama hari ini.',
            ]);
        }
    }

    private function jenisKunjungan(int $pasienId): string
    {
        return Kunjungan::where('pasien_id', $pasienId)->exists() ? 'lama' : 'baru';
    }
}
```

- [ ] **Step 8: Tulis factory Kunjungan dan Antrian**

`database/factories/KunjunganFactory.php` memakai `Pasien::factory()`, `Poli::factory()`, `Dokter::factory()`, `Penjamin::factory()` sebagai nilai bawaan, `jenis_kunjungan` `'baru'`, `status` `'terdaftar'`, `tanggal` hari ini, dan `no_kunjungan` dari `app(NomorDokumen::class)->berikutnya('kunjungan')`. `AntrianFactory` memakai `Kunjungan::factory()` dan nomor dari `app(NomorAntrian::class)`.

- [ ] **Step 9: Tambahkan Kunjungan ke daftar model terauditkan**

Di `AppServiceProvider::modelTerauditkan()`, ubah menjadi:

```php
return [Pasien::class, Kunjungan::class];
```

- [ ] **Step 10: Jalankan test sampai lulus**

Run: `php artisan test --filter="AntrianTest|KunjunganTest"`
Diharapkan: PASS, 19 test.

- [ ] **Step 11: Commit**

```bash
git add -A
git commit -m "feat: tambah pendaftaran kunjungan dan penomoran antrian per poli per hari"
```

---

### Task 9: Pemeriksaan tanda vital oleh perawat

**Files:**
- Create: migration `create_pemeriksaan_table`, `app/Models/Pemeriksaan.php`, `database/factories/PemeriksaanFactory.php`, `app/Services/PemeriksaanKlinis.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/PemeriksaanVitalTest.php`

**Interfaces:**
- Consumes: `Kunjungan` (Task 8), `StatusKunjungan` (Task 2)
- Produces:
  - Model `Pemeriksaan` (satu baris per kunjungan, `SoftDeletes`).
  - `PemeriksaanKlinis::catatVital(Kunjungan $kunjungan, array $data, User $perawat): Pemeriksaan` — mengisi tanda vital dan mengubah status kunjungan menjadi `DiperiksaPerawat`. Method `catatSoap()` dan `selesaikan()` ditambahkan pada kelas yang sama di Task 10.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/PemeriksaanVitalTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\StatusKunjungan;
use App\Models\Kunjungan;
use App\Models\User;
use App\Services\PemeriksaanKlinis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class PemeriksaanVitalTest extends TestCase
{
    use RefreshDatabase;

    private function vital(array $ganti = []): array
    {
        return array_merge([
            'sistolik' => 120,
            'diastolik' => 80,
            'nadi' => 78,
            'suhu' => 36.7,
            'respirasi' => 18,
            'berat_badan' => 62.5,
            'tinggi_badan' => 165,
            'keluhan_awal' => 'Batuk sejak tiga hari',
        ], $ganti);
    }

    public function test_perawat_mencatat_tanda_vital_dan_status_kunjungan_berubah(): void
    {
        $kunjungan = Kunjungan::factory()->create();
        $perawat = User::factory()->create();

        $pemeriksaan = app(PemeriksaanKlinis::class)->catatVital($kunjungan, $this->vital(), $perawat);

        $this->assertSame(120, $pemeriksaan->sistolik);
        $this->assertSame($perawat->id, $pemeriksaan->dicatat_perawat_id);
        $this->assertNotNull($pemeriksaan->waktu_perawat);
        $this->assertSame(StatusKunjungan::DiperiksaPerawat, $kunjungan->refresh()->status);
    }

    public function test_pencatatan_kedua_memperbarui_baris_yang_sama_bukan_membuat_baru(): void
    {
        $kunjungan = Kunjungan::factory()->create();
        $perawat = User::factory()->create();
        $layanan = app(PemeriksaanKlinis::class);

        $layanan->catatVital($kunjungan, $this->vital(), $perawat);
        $layanan->catatVital($kunjungan, $this->vital(['nadi' => 88]), $perawat);

        $this->assertSame(1, $kunjungan->refresh()->pemeriksaan()->count());
        $this->assertSame(88, $kunjungan->pemeriksaan->nadi);
    }

    public function test_suhu_di_luar_rentang_wajar_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        app(PemeriksaanKlinis::class)->catatVital(
            Kunjungan::factory()->create(),
            $this->vital(['suhu' => 55]),
            User::factory()->create()
        );
    }

    public function test_tekanan_darah_wajib_berupa_angka(): void
    {
        $this->expectException(ValidationException::class);

        app(PemeriksaanKlinis::class)->catatVital(
            Kunjungan::factory()->create(),
            $this->vital(['sistolik' => 'seratus']),
            User::factory()->create()
        );
    }

    public function test_kunjungan_yang_sudah_selesai_tidak_bisa_diisi_vital(): void
    {
        $kunjungan = Kunjungan::factory()->create(['status' => StatusKunjungan::Selesai]);

        $this->expectException(RuntimeException::class);

        app(PemeriksaanKlinis::class)->catatVital($kunjungan, $this->vital(), User::factory()->create());
    }

    public function test_kunjungan_yang_dibatalkan_tidak_bisa_diisi_vital(): void
    {
        $kunjungan = Kunjungan::factory()->create(['status' => StatusKunjungan::Batal]);

        $this->expectException(RuntimeException::class);

        app(PemeriksaanKlinis::class)->catatVital($kunjungan, $this->vital(), User::factory()->create());
    }
}
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=PemeriksaanVitalTest`
Diharapkan: FAIL dengan "Class App\Services\PemeriksaanKlinis not found".

- [ ] **Step 3: Tulis migration**

```bash
php artisan make:migration create_pemeriksaan_table
```

```php
Schema::create('pemeriksaan', function (Blueprint $table) {
    $table->id();
    $table->foreignId('kunjungan_id')->unique()->constrained('kunjungan')->cascadeOnDelete();
    $table->unsignedSmallInteger('sistolik')->nullable();
    $table->unsignedSmallInteger('diastolik')->nullable();
    $table->unsignedSmallInteger('nadi')->nullable();
    $table->decimal('suhu', 4, 1)->nullable();
    $table->unsignedSmallInteger('respirasi')->nullable();
    $table->decimal('berat_badan', 5, 1)->nullable();
    $table->unsignedSmallInteger('tinggi_badan')->nullable();
    $table->text('keluhan_awal')->nullable();
    $table->string('alergi', 255)->nullable();
    $table->text('subjective')->nullable();
    $table->text('objective')->nullable();
    $table->text('assessment')->nullable();
    $table->text('plan')->nullable();
    $table->foreignId('dicatat_perawat_id')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('dicatat_dokter_id')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('waktu_perawat')->nullable();
    $table->timestamp('waktu_dokter')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

- [ ] **Step 4: Tulis model dan factory**

`app/Models/Pemeriksaan.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pemeriksaan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pemeriksaan';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'suhu' => 'float',
            'berat_badan' => 'float',
            'waktu_perawat' => 'datetime',
            'waktu_dokter' => 'datetime',
        ];
    }

    public function kunjungan(): BelongsTo { return $this->belongsTo(Kunjungan::class); }

    public function soapLengkap(): bool
    {
        return filled($this->subjective)
            && filled($this->objective)
            && filled($this->assessment)
            && filled($this->plan);
    }
}
```

`PemeriksaanFactory` cukup mengisi `kunjungan_id` dari `Kunjungan::factory()` dan tanda vital acak dalam rentang wajar.

- [ ] **Step 5: Tulis PemeriksaanKlinis**

`app/Services/PemeriksaanKlinis.php`:

```php
<?php

namespace App\Services;

use App\Enums\StatusKunjungan;
use App\Models\Kunjungan;
use App\Models\Pemeriksaan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class PemeriksaanKlinis
{
    public function catatVital(Kunjungan $kunjungan, array $data, User $perawat): Pemeriksaan
    {
        $this->pastikanKunjunganMasihBerjalan($kunjungan);

        $tervalidasi = Validator::make($data, [
            'sistolik' => ['required', 'integer', 'between:50,300'],
            'diastolik' => ['required', 'integer', 'between:30,200'],
            'nadi' => ['required', 'integer', 'between:20,250'],
            'suhu' => ['required', 'numeric', 'between:30,45'],
            'respirasi' => ['required', 'integer', 'between:5,80'],
            'berat_badan' => ['required', 'numeric', 'between:0.5,400'],
            'tinggi_badan' => ['required', 'integer', 'between:20,250'],
            'keluhan_awal' => ['required', 'string'],
            'alergi' => ['nullable', 'string', 'max:255'],
        ], [
            'suhu.between' => 'Suhu tubuh di luar rentang wajar (30–45 °C).',
            'sistolik.integer' => 'Tekanan darah sistolik harus berupa angka.',
            'keluhan_awal.required' => 'Keluhan awal wajib diisi.',
        ])->validate();

        return DB::transaction(function () use ($kunjungan, $tervalidasi, $perawat) {
            $pemeriksaan = Pemeriksaan::updateOrCreate(
                ['kunjungan_id' => $kunjungan->id],
                [...$tervalidasi, 'dicatat_perawat_id' => $perawat->id, 'waktu_perawat' => now()]
            );

            $kunjungan->update(['status' => StatusKunjungan::DiperiksaPerawat]);

            return $pemeriksaan;
        });
    }

    private function pastikanKunjunganMasihBerjalan(Kunjungan $kunjungan): void
    {
        if (! $kunjungan->status->aktif()) {
            throw new RuntimeException(
                'Kunjungan yang sudah selesai atau dibatalkan tidak bisa diubah lagi.'
            );
        }
    }
}
```

- [ ] **Step 6: Tambahkan Pemeriksaan ke daftar model terauditkan**

```php
return [Pasien::class, Kunjungan::class, Pemeriksaan::class];
```

- [ ] **Step 7: Jalankan test sampai lulus**

Run: `php artisan test --filter=PemeriksaanVitalTest`
Diharapkan: PASS, 6 test.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: tambah pencatatan tanda vital oleh perawat"
```

---
### Task 10: SOAP, diagnosa, penyelesaian kunjungan, dan penguncian data klinis

**Files:**
- Create: migration `create_diagnosa_table`, `app/Models/Diagnosa.php`, `app/Policies/KunjunganPolicy.php`, `app/Policies/PemeriksaanPolicy.php`
- Modify: `app/Services/PemeriksaanKlinis.php`, `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/RekamMedisTest.php`, `tests/Feature/HakAksesKlinisTest.php`

**Interfaces:**
- Consumes: `PemeriksaanKlinis` (Task 9), `Icd10` (Task 3), `Peran` (Task 2)
- Produces:
  - `PemeriksaanKlinis::catatSoap(Kunjungan $kunjungan, array $data, User $dokter): Pemeriksaan`
  - `PemeriksaanKlinis::tambahDiagnosa(Kunjungan $kunjungan, int $icd10Id, JenisDiagnosa $jenis, ?string $catatan = null): Diagnosa`
  - `PemeriksaanKlinis::selesaikan(Kunjungan $kunjungan, User $dokter): Kunjungan`
  - `PemeriksaanKlinis::koreksi(Kunjungan $kunjungan, array $data, User $dokter, string $alasan): Pemeriksaan`
  - `KunjunganPolicy::periksa(User $user, Kunjungan $kunjungan): bool`, `PemeriksaanPolicy::ubah(User $user, Pemeriksaan $pemeriksaan): bool`

Memenuhi aturan 8, 9, 10, 11, dan 18.

- [ ] **Step 1: Tulis test rekam medis yang gagal**

Buat `tests/Feature/RekamMedisTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\JenisDiagnosa;
use App\Enums\StatusKunjungan;
use App\Models\AuditLog;
use App\Models\Dokter;
use App\Models\Icd10;
use App\Models\Kunjungan;
use App\Models\User;
use App\Services\PemeriksaanKlinis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class RekamMedisTest extends TestCase
{
    use RefreshDatabase;

    private Kunjungan $kunjungan;
    private User $dokter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kunjungan = Kunjungan::factory()->create();
        $this->dokter = User::factory()->create(['dokter_id' => $this->kunjungan->dokter_id]);
    }

    private function layanan(): PemeriksaanKlinis
    {
        return app(PemeriksaanKlinis::class);
    }

    private function soap(array $ganti = []): array
    {
        return array_merge([
            'subjective' => 'Batuk berdahak tiga hari, tidak demam',
            'objective' => 'Faring hiperemis, paru vesikuler',
            'assessment' => 'ISPA',
            'plan' => 'Antibiotik dan obat batuk, kontrol bila memberat',
        ], $ganti);
    }

    private function lengkapiSoapDanDiagnosa(): void
    {
        $this->layanan()->catatSoap($this->kunjungan, $this->soap(), $this->dokter);
        $this->layanan()->tambahDiagnosa(
            $this->kunjungan,
            Icd10::factory()->create()->id,
            JenisDiagnosa::Primer
        );
    }

    public function test_dokter_mencatat_soap_dan_status_kunjungan_berubah(): void
    {
        $pemeriksaan = $this->layanan()->catatSoap($this->kunjungan, $this->soap(), $this->dokter);

        $this->assertSame('ISPA', $pemeriksaan->assessment);
        $this->assertSame($this->dokter->id, $pemeriksaan->dicatat_dokter_id);
        $this->assertSame(StatusKunjungan::DiperiksaDokter, $this->kunjungan->refresh()->status);
    }

    public function test_soap_yang_tidak_lengkap_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        $this->layanan()->catatSoap($this->kunjungan, $this->soap(['plan' => '']), $this->dokter);
    }

    public function test_kunjungan_hanya_boleh_punya_satu_diagnosa_primer(): void
    {
        $this->layanan()->tambahDiagnosa($this->kunjungan, Icd10::factory()->create()->id, JenisDiagnosa::Primer);

        $this->expectException(ValidationException::class);

        $this->layanan()->tambahDiagnosa($this->kunjungan, Icd10::factory()->create()->id, JenisDiagnosa::Primer);
    }

    public function test_diagnosa_sekunder_boleh_lebih_dari_satu(): void
    {
        $this->layanan()->tambahDiagnosa($this->kunjungan, Icd10::factory()->create()->id, JenisDiagnosa::Primer);
        $this->layanan()->tambahDiagnosa($this->kunjungan, Icd10::factory()->create()->id, JenisDiagnosa::Sekunder);
        $this->layanan()->tambahDiagnosa($this->kunjungan, Icd10::factory()->create()->id, JenisDiagnosa::Sekunder);

        $this->assertSame(3, $this->kunjungan->diagnosa()->count());
    }

    public function test_kode_diagnosa_yang_sama_tidak_bisa_ditambahkan_dua_kali(): void
    {
        $icd = Icd10::factory()->create();
        $this->layanan()->tambahDiagnosa($this->kunjungan, $icd->id, JenisDiagnosa::Primer);

        $this->expectException(ValidationException::class);

        $this->layanan()->tambahDiagnosa($this->kunjungan, $icd->id, JenisDiagnosa::Sekunder);
    }

    public function test_kunjungan_tanpa_soap_tidak_bisa_diselesaikan(): void
    {
        $this->layanan()->tambahDiagnosa($this->kunjungan, Icd10::factory()->create()->id, JenisDiagnosa::Primer);

        $this->expectException(RuntimeException::class);

        $this->layanan()->selesaikan($this->kunjungan, $this->dokter);
    }

    public function test_kunjungan_tanpa_diagnosa_primer_tidak_bisa_diselesaikan(): void
    {
        $this->layanan()->catatSoap($this->kunjungan, $this->soap(), $this->dokter);

        $this->expectException(RuntimeException::class);

        $this->layanan()->selesaikan($this->kunjungan, $this->dokter);
    }

    public function test_kunjungan_dengan_soap_dan_diagnosa_lengkap_bisa_diselesaikan(): void
    {
        $this->lengkapiSoapDanDiagnosa();

        $selesai = $this->layanan()->selesaikan($this->kunjungan, $this->dokter);

        $this->assertSame(StatusKunjungan::Selesai, $selesai->status);
        $this->assertNotNull($selesai->waktu_selesai);
    }

    public function test_data_klinis_terkunci_setelah_kunjungan_selesai(): void
    {
        $this->lengkapiSoapDanDiagnosa();
        $this->layanan()->selesaikan($this->kunjungan, $this->dokter);

        $this->expectException(RuntimeException::class);

        $this->layanan()->catatSoap($this->kunjungan, $this->soap(['plan' => 'Diubah diam-diam']), $this->dokter);
    }

    public function test_koreksi_setelah_selesai_wajib_menyertakan_alasan_dan_tercatat_di_audit(): void
    {
        $this->lengkapiSoapDanDiagnosa();
        $this->layanan()->selesaikan($this->kunjungan, $this->dokter);

        $this->layanan()->koreksi(
            $this->kunjungan,
            $this->soap(['assessment' => 'ISPA dengan faringitis akut']),
            $this->dokter,
            'Assessment kurang spesifik saat pemeriksaan'
        );

        $catatan = AuditLog::where('aksi', 'update')->latest('id')->first();

        $this->assertSame('Assessment kurang spesifik saat pemeriksaan', $catatan->alasan);
        $this->assertSame('ISPA dengan faringitis akut', $this->kunjungan->refresh()->pemeriksaan->assessment);
    }

    public function test_koreksi_tanpa_alasan_ditolak(): void
    {
        $this->lengkapiSoapDanDiagnosa();
        $this->layanan()->selesaikan($this->kunjungan, $this->dokter);

        $this->expectException(ValidationException::class);

        $this->layanan()->koreksi($this->kunjungan, $this->soap(), $this->dokter, '   ');
    }

    public function test_koreksi_oleh_dokter_lain_ditolak(): void
    {
        $this->lengkapiSoapDanDiagnosa();
        $this->layanan()->selesaikan($this->kunjungan, $this->dokter);

        $dokterLain = User::factory()->create(['dokter_id' => Dokter::factory()->create()->id]);

        $this->expectException(RuntimeException::class);

        $this->layanan()->koreksi($this->kunjungan, $this->soap(), $dokterLain, 'Iseng mengubah');
    }
}
```

- [ ] **Step 2: Tulis test hak akses klinis yang gagal**

Buat `tests/Feature/HakAksesKlinisTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Dokter;
use App\Models\Kunjungan;
use App\Models\Pemeriksaan;
use App\Models\Poli;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HakAksesKlinisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Peran::semua() as $peran) {
            Role::findOrCreate($peran);
        }
    }

    private function penggunaBerperan(string $peran, array $atribut = []): User
    {
        $user = User::factory()->create($atribut);
        $user->assignRole($peran);

        return $user;
    }

    public function test_dokter_boleh_memeriksa_kunjungan_di_polinya(): void
    {
        $kunjungan = Kunjungan::factory()->create();
        $dokter = $this->penggunaBerperan(Peran::Dokter->value, ['dokter_id' => $kunjungan->dokter_id]);

        $this->assertTrue(Gate::forUser($dokter)->allows('periksa', $kunjungan));
    }

    public function test_dokter_tidak_bisa_memeriksa_kunjungan_poli_lain(): void
    {
        $kunjungan = Kunjungan::factory()->create();
        $poliLain = Poli::factory()->create(['kode' => 'GIG']);
        $dokterLain = Dokter::factory()->create(['poli_id' => $poliLain->id]);
        $user = $this->penggunaBerperan(Peran::Dokter->value, ['dokter_id' => $dokterLain->id]);

        $this->assertFalse(Gate::forUser($user)->allows('periksa', $kunjungan));
    }

    public function test_kasir_tidak_bisa_memeriksa_kunjungan(): void
    {
        $kunjungan = Kunjungan::factory()->create();
        $kasir = $this->penggunaBerperan(Peran::Kasir->value);

        $this->assertFalse(Gate::forUser($kasir)->allows('periksa', $kunjungan));
    }

    public function test_admin_tidak_bisa_mengubah_rekam_medis(): void
    {
        $pemeriksaan = Pemeriksaan::factory()->create();
        $admin = $this->penggunaBerperan(Peran::Admin->value);

        $this->assertFalse(Gate::forUser($admin)->allows('ubah', $pemeriksaan));
    }

    public function test_perawat_boleh_mengisi_pemeriksaan_yang_belum_selesai(): void
    {
        $pemeriksaan = Pemeriksaan::factory()->create();
        $perawat = $this->penggunaBerperan(Peran::Perawat->value);

        $this->assertTrue(Gate::forUser($perawat)->allows('ubah', $pemeriksaan));
    }
}
```

- [ ] **Step 3: Jalankan kedua test untuk memastikan gagal**

Run: `php artisan test --filter="RekamMedisTest|HakAksesKlinisTest"`
Diharapkan: FAIL — method `catatSoap` dan policy belum ada.

- [ ] **Step 4: Tulis migration diagnosa**

```bash
php artisan make:migration create_diagnosa_table
```

```php
Schema::create('diagnosa', function (Blueprint $table) {
    $table->id();
    $table->foreignId('kunjungan_id')->constrained('kunjungan')->cascadeOnDelete();
    $table->foreignId('icd10_id')->constrained('icd10');
    $table->enum('jenis', ['primer', 'sekunder']);
    $table->string('catatan', 255)->nullable();
    $table->timestamps();
    $table->unique(['kunjungan_id', 'icd10_id']);
});
```

- [ ] **Step 5: Tulis model Diagnosa**

`app/Models/Diagnosa.php`:

```php
<?php

namespace App\Models;

use App\Enums\JenisDiagnosa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Diagnosa extends Model
{
    use HasFactory;

    protected $table = 'diagnosa';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['jenis' => JenisDiagnosa::class];
    }

    public function kunjungan(): BelongsTo { return $this->belongsTo(Kunjungan::class); }
    public function icd10(): BelongsTo { return $this->belongsTo(Icd10::class); }
}
```

- [ ] **Step 6: Lengkapi PemeriksaanKlinis**

Tambahkan method berikut ke `app/Services/PemeriksaanKlinis.php` (import `App\Enums\JenisDiagnosa`, `App\Models\Diagnosa`, `Illuminate\Validation\ValidationException`):

```php
public function catatSoap(Kunjungan $kunjungan, array $data, User $dokter): Pemeriksaan
{
    $this->pastikanKunjunganMasihBerjalan($kunjungan);

    $tervalidasi = Validator::make($data, [
        'subjective' => ['required', 'string'],
        'objective' => ['required', 'string'],
        'assessment' => ['required', 'string'],
        'plan' => ['required', 'string'],
    ], [
        'subjective.required' => 'Bagian Subjective wajib diisi.',
        'objective.required' => 'Bagian Objective wajib diisi.',
        'assessment.required' => 'Bagian Assessment wajib diisi.',
        'plan.required' => 'Bagian Plan wajib diisi.',
    ])->validate();

    return DB::transaction(function () use ($kunjungan, $tervalidasi, $dokter) {
        $pemeriksaan = Pemeriksaan::updateOrCreate(
            ['kunjungan_id' => $kunjungan->id],
            [...$tervalidasi, 'dicatat_dokter_id' => $dokter->id, 'waktu_dokter' => now()]
        );

        $kunjungan->update(['status' => StatusKunjungan::DiperiksaDokter]);

        return $pemeriksaan;
    });
}

public function tambahDiagnosa(
    Kunjungan $kunjungan,
    int $icd10Id,
    JenisDiagnosa $jenis,
    ?string $catatan = null
): Diagnosa {
    $this->pastikanKunjunganMasihBerjalan($kunjungan);

    if ($kunjungan->diagnosa()->where('icd10_id', $icd10Id)->exists()) {
        throw ValidationException::withMessages([
            'icd10_id' => 'Kode diagnosa ini sudah tercatat pada kunjungan tersebut.',
        ]);
    }

    if ($jenis === JenisDiagnosa::Primer
        && $kunjungan->diagnosa()->where('jenis', JenisDiagnosa::Primer->value)->exists()) {
        throw ValidationException::withMessages([
            'jenis' => 'Kunjungan ini sudah punya diagnosa primer. Hapus dulu yang lama bila ingin mengganti.',
        ]);
    }

    return $kunjungan->diagnosa()->create([
        'icd10_id' => $icd10Id,
        'jenis' => $jenis,
        'catatan' => $catatan,
    ]);
}

public function selesaikan(Kunjungan $kunjungan, User $dokter): Kunjungan
{
    $this->pastikanKunjunganMasihBerjalan($kunjungan);

    $pemeriksaan = $kunjungan->pemeriksaan;

    if ($pemeriksaan === null || ! $pemeriksaan->soapLengkap()) {
        throw new RuntimeException(
            'Kunjungan belum bisa diselesaikan: SOAP harus terisi lengkap.'
        );
    }

    if (! $kunjungan->diagnosa()->where('jenis', JenisDiagnosa::Primer->value)->exists()) {
        throw new RuntimeException(
            'Kunjungan belum bisa diselesaikan: diagnosa primer belum ditetapkan.'
        );
    }

    return DB::transaction(function () use ($kunjungan) {
        $kunjungan->update([
            'status' => StatusKunjungan::Selesai,
            'waktu_selesai' => now(),
        ]);

        return $kunjungan->refresh();
    });
}

public function koreksi(Kunjungan $kunjungan, array $data, User $dokter, string $alasan): Pemeriksaan
{
    if (trim($alasan) === '') {
        throw ValidationException::withMessages([
            'alasan' => 'Alasan koreksi wajib diisi.',
        ]);
    }

    $pemeriksaan = $kunjungan->pemeriksaan;

    if ($pemeriksaan->dicatat_dokter_id !== $dokter->id) {
        throw new RuntimeException(
            'Koreksi rekam medis hanya boleh dilakukan oleh dokter yang mencatatnya.'
        );
    }

    $tervalidasi = Validator::make($data, [
        'subjective' => ['required', 'string'],
        'objective' => ['required', 'string'],
        'assessment' => ['required', 'string'],
        'plan' => ['required', 'string'],
    ])->validate();

    return KonteksAudit::dengan($alasan, function () use ($pemeriksaan, $tervalidasi) {
        $pemeriksaan->update($tervalidasi);

        return $pemeriksaan->refresh();
    });
}
```

Tambahkan `use App\Support\KonteksAudit;` di bagian import.

- [ ] **Step 7: Tulis policy**

`app/Policies/KunjunganPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Models\Kunjungan;
use App\Models\User;

class KunjunganPolicy
{
    public function periksa(User $user, Kunjungan $kunjungan): bool
    {
        if (! $user->hasRole(Peran::Dokter->value) || $user->dokter === null) {
            return false;
        }

        return (int) $user->dokter->poli_id === (int) $kunjungan->poli_id;
    }

    public function daftarkan(User $user): bool
    {
        return $user->hasRole(Peran::Admisi->value);
    }

    public function batalkan(User $user, Kunjungan $kunjungan): bool
    {
        return $user->hasRole(Peran::Admisi->value)
            && $kunjungan->status === \App\Enums\StatusKunjungan::Terdaftar;
    }
}
```

`app/Policies/PemeriksaanPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Models\Pemeriksaan;
use App\Models\User;

class PemeriksaanPolicy
{
    public function ubah(User $user, Pemeriksaan $pemeriksaan): bool
    {
        if ($user->hasRole(Peran::Perawat->value)) {
            return $pemeriksaan->kunjungan->status->aktif();
        }

        if (! $user->hasRole(Peran::Dokter->value) || $user->dokter === null) {
            return false;
        }

        if ($pemeriksaan->kunjungan->status->aktif()) {
            return (int) $user->dokter->poli_id === (int) $pemeriksaan->kunjungan->poli_id;
        }

        return $pemeriksaan->dicatat_dokter_id === $user->id;
    }
}
```

Laravel menemukan policy ini otomatis lewat kesamaan nama (`App\Models\Kunjungan` → `App\Policies\KunjunganPolicy`), jadi tidak perlu pendaftaran manual.

- [ ] **Step 8: Tambahkan Diagnosa ke daftar model terauditkan**

```php
return [Pasien::class, Kunjungan::class, Pemeriksaan::class, Diagnosa::class];
```

- [ ] **Step 9: Jalankan test sampai lulus**

Run: `php artisan test --filter="RekamMedisTest|HakAksesKlinisTest"`
Diharapkan: PASS, 17 test.

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "feat: tambah SOAP, diagnosa ICD-10, penyelesaian kunjungan, dan penguncian rekam medis"
```

---

### Task 11: Tarif dan tindakan kunjungan

**Files:**
- Create: migration `create_tindakan_kunjungan_table`, `app/Models/TindakanKunjungan.php`, `app/Services/PencariTarif.php`, `app/Services/TindakanPelayanan.php`
- Test: `tests/Feature/TarifTest.php`

**Interfaces:**
- Consumes: `Tindakan`, `TarifTindakan`, `Penjamin` (Task 3), `Kunjungan` (Task 8)
- Produces:
  - `PencariTarif::untuk(int $tindakanId, int $penjaminId, ?CarbonInterface $tanggal = null): int` — rupiah bulat; jatuh tempo ke penjamin `UMUM` bila tarif khusus tidak ada, sambil mencatat peringatan ke log.
  - `TindakanPelayanan::tambah(Kunjungan $kunjungan, int $tindakanId, int $jumlah, User $petugas): TindakanKunjungan` — menyalin tarif ke `tarif_satuan`.

Memenuhi aturan 12 (bagian penyalinan tarif) dan 13.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/TarifTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Kunjungan;
use App\Models\Penjamin;
use App\Models\TarifTindakan;
use App\Models\Tindakan;
use App\Models\User;
use App\Services\PencariTarif;
use App\Services\TindakanPelayanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class TarifTest extends TestCase
{
    use RefreshDatabase;

    private Tindakan $tindakan;
    private Penjamin $umum;
    private Penjamin $bpjs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tindakan = Tindakan::factory()->create(['nama' => 'Konsultasi Dokter Umum']);
        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $this->bpjs = Penjamin::factory()->create(['kode' => 'BPJS', 'jenis' => 'penjamin']);
    }

    private function tarif(Penjamin $penjamin, int $nilai, string $berlakuMulai = '2026-01-01'): void
    {
        TarifTindakan::factory()->create([
            'tindakan_id' => $this->tindakan->id,
            'penjamin_id' => $penjamin->id,
            'tarif' => $nilai,
            'berlaku_mulai' => $berlakuMulai,
        ]);
    }

    public function test_tarif_diambil_sesuai_penjamin_kunjungan(): void
    {
        $this->tarif($this->umum, 50000);
        $this->tarif($this->bpjs, 35000);

        $this->assertSame(35000, app(PencariTarif::class)->untuk($this->tindakan->id, $this->bpjs->id));
    }

    public function test_tarif_jatuh_tempo_ke_umum_bila_penjamin_belum_punya_tarif(): void
    {
        $this->tarif($this->umum, 50000);

        $this->assertSame(50000, app(PencariTarif::class)->untuk($this->tindakan->id, $this->bpjs->id));
    }

    public function test_ketiadaan_tarif_penjamin_dicatat_sebagai_peringatan(): void
    {
        $this->tarif($this->umum, 50000);
        Log::spy();

        app(PencariTarif::class)->untuk($this->tindakan->id, $this->bpjs->id);

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_tarif_terbaru_yang_sudah_berlaku_yang_dipakai(): void
    {
        $this->tarif($this->umum, 50000, '2026-01-01');
        $this->tarif($this->umum, 60000, '2026-06-01');

        $pencari = app(PencariTarif::class);

        $this->assertSame(50000, $pencari->untuk($this->tindakan->id, $this->umum->id, Carbon::parse('2026-03-01')));
        $this->assertSame(60000, $pencari->untuk($this->tindakan->id, $this->umum->id, Carbon::parse('2026-08-18')));
    }

    public function test_tanpa_tarif_sama_sekali_maka_gagal_dengan_pesan_jelas(): void
    {
        $this->expectException(RuntimeException::class);

        app(PencariTarif::class)->untuk($this->tindakan->id, $this->bpjs->id);
    }

    public function test_tarif_disalin_ke_tindakan_kunjungan(): void
    {
        $this->tarif($this->umum, 50000);
        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]);

        $baris = app(TindakanPelayanan::class)
            ->tambah($kunjungan, $this->tindakan->id, 1, User::factory()->create());

        $this->assertSame(50000, (int) $baris->tarif_satuan);
    }

    public function test_perubahan_master_tarif_tidak_mengubah_tindakan_yang_sudah_dicatat(): void
    {
        $this->tarif($this->umum, 50000);
        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]);

        $baris = app(TindakanPelayanan::class)
            ->tambah($kunjungan, $this->tindakan->id, 1, User::factory()->create());

        TarifTindakan::where('tindakan_id', $this->tindakan->id)->update(['tarif' => 99000]);

        $this->assertSame(50000, (int) $baris->refresh()->tarif_satuan);
    }

    public function test_jumlah_tindakan_minimal_satu(): void
    {
        $this->tarif($this->umum, 50000);
        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(TindakanPelayanan::class)->tambah($kunjungan, $this->tindakan->id, 0, User::factory()->create());
    }
}
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=TarifTest`
Diharapkan: FAIL dengan "Class App\Services\PencariTarif not found".

- [ ] **Step 3: Tulis migration**

```bash
php artisan make:migration create_tindakan_kunjungan_table
```

```php
Schema::create('tindakan_kunjungan', function (Blueprint $table) {
    $table->id();
    $table->foreignId('kunjungan_id')->constrained('kunjungan')->cascadeOnDelete();
    $table->foreignId('tindakan_id')->constrained('tindakan');
    $table->unsignedSmallInteger('jumlah')->default(1);
    $table->unsignedBigInteger('tarif_satuan');
    $table->foreignId('dilakukan_oleh')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
});
```

- [ ] **Step 4: Tulis model TindakanKunjungan**

`app/Models/TindakanKunjungan.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TindakanKunjungan extends Model
{
    use HasFactory;

    protected $table = 'tindakan_kunjungan';

    protected $guarded = [];

    public function kunjungan(): BelongsTo { return $this->belongsTo(Kunjungan::class); }
    public function tindakan(): BelongsTo { return $this->belongsTo(Tindakan::class); }

    public function subtotal(): int
    {
        return (int) $this->jumlah * (int) $this->tarif_satuan;
    }
}
```

- [ ] **Step 5: Tulis PencariTarif**

`app/Services/PencariTarif.php`:

```php
<?php

namespace App\Services;

use App\Models\Penjamin;
use App\Models\TarifTindakan;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PencariTarif
{
    public function untuk(int $tindakanId, int $penjaminId, ?CarbonInterface $tanggal = null): int
    {
        $tanggal ??= Carbon::today();

        $tarif = $this->cari($tindakanId, $penjaminId, $tanggal);

        if ($tarif !== null) {
            return $tarif;
        }

        $umum = Penjamin::where('kode', 'UMUM')->first();

        Log::warning('Tarif khusus penjamin tidak ditemukan, memakai tarif UMUM.', [
            'tindakan_id' => $tindakanId,
            'penjamin_id' => $penjaminId,
            'tanggal' => $tanggal->toDateString(),
        ]);

        $tarifUmum = $umum ? $this->cari($tindakanId, $umum->id, $tanggal) : null;

        if ($tarifUmum === null) {
            throw new RuntimeException(
                "Tarif untuk tindakan #{$tindakanId} belum diisi, termasuk tarif UMUM. Hubungi admin master data."
            );
        }

        return $tarifUmum;
    }

    private function cari(int $tindakanId, int $penjaminId, CarbonInterface $tanggal): ?int
    {
        $baris = TarifTindakan::where('tindakan_id', $tindakanId)
            ->where('penjamin_id', $penjaminId)
            ->whereDate('berlaku_mulai', '<=', $tanggal->toDateString())
            ->orderByDesc('berlaku_mulai')
            ->first();

        return $baris ? (int) $baris->tarif : null;
    }
}
```

- [ ] **Step 6: Tulis TindakanPelayanan**

`app/Services/TindakanPelayanan.php`:

```php
<?php

namespace App\Services;

use App\Models\Kunjungan;
use App\Models\TindakanKunjungan;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class TindakanPelayanan
{
    public function __construct(private readonly PencariTarif $pencariTarif) {}

    public function tambah(Kunjungan $kunjungan, int $tindakanId, int $jumlah, User $petugas): TindakanKunjungan
    {
        if (! $kunjungan->status->aktif()) {
            throw new RuntimeException('Tindakan tidak bisa ditambahkan pada kunjungan yang sudah selesai.');
        }

        Validator::make(
            ['tindakan_id' => $tindakanId, 'jumlah' => $jumlah],
            [
                'tindakan_id' => ['required', 'exists:tindakan,id'],
                'jumlah' => ['required', 'integer', 'min:1'],
            ],
            ['jumlah.min' => 'Jumlah tindakan minimal 1.']
        )->validate();

        return $kunjungan->tindakan()->create([
            'tindakan_id' => $tindakanId,
            'jumlah' => $jumlah,
            'tarif_satuan' => $this->pencariTarif->untuk($tindakanId, $kunjungan->penjamin_id, $kunjungan->tanggal),
            'dilakukan_oleh' => $petugas->id,
        ]);
    }
}
```

- [ ] **Step 7: Jalankan test sampai lulus**

Run: `php artisan test --filter=TarifTest`
Diharapkan: PASS, 8 test.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: tambah pencarian tarif per penjamin dan pencatatan tindakan kunjungan"
```

---
### Task 12: Resep

**Files:**
- Create: migration `create_resep_tables`, `app/Models/Resep.php`, `app/Models/ResepDetail.php`, `app/Services/PenulisanResep.php`
- Test: `tests/Feature/ResepTest.php`

**Interfaces:**
- Consumes: `Kunjungan` (Task 8), `Obat` (Task 3), `NomorDokumen` (Task 5)
- Produces: `PenulisanResep::tulis(Kunjungan $kunjungan, array $item, User $dokter): Resep`, dengan `$item` berupa array dari `['obat_id' => int, 'jumlah' => int, 'aturan_pakai' => string, 'catatan' => ?string]`. Satu kunjungan punya paling banyak satu resep; penulisan ulang mengganti seluruh rinciannya.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/ResepTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\StatusKunjungan;
use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\User;
use App\Services\PenulisanResep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class ResepTest extends TestCase
{
    use RefreshDatabase;

    private function item(array $ganti = []): array
    {
        return [array_merge([
            'obat_id' => Obat::factory()->create()->id,
            'jumlah' => 10,
            'aturan_pakai' => '3x1 sesudah makan',
        ], $ganti)];
    }

    public function test_resep_bernomor_dan_terhubung_ke_kunjungan(): void
    {
        $kunjungan = Kunjungan::factory()->create();

        $resep = app(PenulisanResep::class)->tulis($kunjungan, $this->item(), User::factory()->create());

        $this->assertStringStartsWith('RS-', $resep->no_resep);
        $this->assertSame($kunjungan->id, $resep->kunjungan_id);
        $this->assertSame(1, $resep->detail()->count());
    }

    public function test_resep_wajib_memuat_minimal_satu_obat(): void
    {
        $this->expectException(ValidationException::class);

        app(PenulisanResep::class)->tulis(Kunjungan::factory()->create(), [], User::factory()->create());
    }

    public function test_aturan_pakai_wajib_diisi(): void
    {
        $this->expectException(ValidationException::class);

        app(PenulisanResep::class)->tulis(
            Kunjungan::factory()->create(),
            $this->item(['aturan_pakai' => '']),
            User::factory()->create()
        );
    }

    public function test_jumlah_obat_minimal_satu(): void
    {
        $this->expectException(ValidationException::class);

        app(PenulisanResep::class)->tulis(
            Kunjungan::factory()->create(),
            $this->item(['jumlah' => 0]),
            User::factory()->create()
        );
    }

    public function test_obat_yang_sama_tidak_boleh_muncul_dua_baris(): void
    {
        $obat = Obat::factory()->create();

        $this->expectException(ValidationException::class);

        app(PenulisanResep::class)->tulis(Kunjungan::factory()->create(), [
            ['obat_id' => $obat->id, 'jumlah' => 5, 'aturan_pakai' => '3x1'],
            ['obat_id' => $obat->id, 'jumlah' => 3, 'aturan_pakai' => '2x1'],
        ], User::factory()->create());
    }

    public function test_penulisan_ulang_mengganti_seluruh_rincian(): void
    {
        $kunjungan = Kunjungan::factory()->create();
        $dokter = User::factory()->create();
        $layanan = app(PenulisanResep::class);

        $layanan->tulis($kunjungan, $this->item(), $dokter);
        $resep = $layanan->tulis($kunjungan, [
            ['obat_id' => Obat::factory()->create()->id, 'jumlah' => 6, 'aturan_pakai' => '2x1'],
            ['obat_id' => Obat::factory()->create()->id, 'jumlah' => 4, 'aturan_pakai' => '1x1'],
        ], $dokter);

        $this->assertSame(1, $kunjungan->refresh()->resep()->count());
        $this->assertSame(2, $resep->detail()->count());
    }

    public function test_resep_tidak_bisa_ditulis_pada_kunjungan_yang_sudah_selesai(): void
    {
        $kunjungan = Kunjungan::factory()->create(['status' => StatusKunjungan::Selesai]);

        $this->expectException(RuntimeException::class);

        app(PenulisanResep::class)->tulis($kunjungan, $this->item(), User::factory()->create());
    }
}
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=ResepTest`
Diharapkan: FAIL dengan "Class App\Services\PenulisanResep not found".

- [ ] **Step 3: Tulis migration**

```bash
php artisan make:migration create_resep_tables
```

```php
Schema::create('resep', function (Blueprint $table) {
    $table->id();
    $table->string('no_resep', 20)->unique();
    $table->foreignId('kunjungan_id')->unique()->constrained('kunjungan')->cascadeOnDelete();
    $table->foreignId('dokter_id')->nullable()->constrained('users')->nullOnDelete();
    $table->string('status', 20)->default('dibuat');
    $table->timestamps();
});

Schema::create('resep_detail', function (Blueprint $table) {
    $table->id();
    $table->foreignId('resep_id')->constrained('resep')->cascadeOnDelete();
    $table->foreignId('obat_id')->constrained('obat');
    $table->unsignedSmallInteger('jumlah');
    $table->string('aturan_pakai', 100);
    $table->string('catatan', 255)->nullable();
    $table->timestamps();
    $table->unique(['resep_id', 'obat_id']);
});
```

- [ ] **Step 4: Tulis model**

`app/Models/Resep.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Resep extends Model
{
    use HasFactory;

    protected $table = 'resep';

    protected $guarded = [];

    public function kunjungan(): BelongsTo { return $this->belongsTo(Kunjungan::class); }
    public function detail(): HasMany { return $this->hasMany(ResepDetail::class); }
}
```

`app/Models/ResepDetail.php` mengikuti pola sama dengan relasi `resep()` dan `obat()`.

- [ ] **Step 5: Tulis PenulisanResep**

`app/Services/PenulisanResep.php`:

```php
<?php

namespace App\Services;

use App\Models\Kunjungan;
use App\Models\Resep;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PenulisanResep
{
    public function __construct(private readonly NomorDokumen $nomorDokumen) {}

    public function tulis(Kunjungan $kunjungan, array $item, User $dokter): Resep
    {
        if (! $kunjungan->status->aktif()) {
            throw new RuntimeException('Resep tidak bisa ditulis untuk kunjungan yang sudah selesai atau dibatalkan.');
        }

        Validator::make(['item' => $item], [
            'item' => ['required', 'array', 'min:1'],
            'item.*.obat_id' => ['required', 'exists:obat,id'],
            'item.*.jumlah' => ['required', 'integer', 'min:1'],
            'item.*.aturan_pakai' => ['required', 'string', 'max:100'],
            'item.*.catatan' => ['nullable', 'string', 'max:255'],
        ], [
            'item.min' => 'Resep harus memuat minimal satu obat.',
            'item.required' => 'Resep harus memuat minimal satu obat.',
            'item.*.aturan_pakai.required' => 'Aturan pakai wajib diisi untuk setiap obat.',
            'item.*.jumlah.min' => 'Jumlah obat minimal 1.',
        ])->validate();

        $obatId = array_column($item, 'obat_id');

        if (count($obatId) !== count(array_unique($obatId))) {
            throw ValidationException::withMessages([
                'item' => 'Satu obat hanya boleh muncul satu baris dalam satu resep. Gabungkan jumlahnya.',
            ]);
        }

        return DB::transaction(function () use ($kunjungan, $item, $dokter) {
            $resep = Resep::firstOrNew(['kunjungan_id' => $kunjungan->id]);
            $resep->no_resep ??= $this->nomorDokumen->berikutnya('resep', $kunjungan->tanggal);
            $resep->dokter_id = $dokter->id;
            $resep->status = 'dibuat';
            $resep->save();

            $resep->detail()->delete();
            $resep->detail()->createMany($item);

            return $resep->refresh();
        });
    }
}
```

- [ ] **Step 6: Jalankan test sampai lulus**

Run: `php artisan test --filter=ResepTest`
Diharapkan: PASS, 7 test.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: tambah penulisan resep beserta rinciannya"
```

---

### Task 13: Penyusunan tagihan

**Files:**
- Create: migration `create_tagihan_tables`, `app/Models/Tagihan.php`, `app/Models/TagihanDetail.php`, `app/Services/PenyusunTagihan.php`
- Modify: `app/Services/PemeriksaanKlinis.php` (method `selesaikan` memanggil penyusun tagihan), `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/TagihanTest.php`

**Interfaces:**
- Consumes: `TindakanKunjungan` (Task 11), `NomorDokumen` (Task 5), `Penjamin::ditanggung()` (Task 3)
- Produces: `PenyusunTagihan::susun(Kunjungan $kunjungan): Tagihan` — bersifat idempoten: pemanggilan kedua mengembalikan tagihan yang sudah ada tanpa membuat yang baru. Model `Tagihan` dengan `total`, `ditanggung_penjamin`, `ditagihkan_ke_pasien` (semua rupiah bulat) dan `status` bertipe `StatusTagihan`.

Memenuhi aturan 12 dan 14.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/TagihanTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\JenisDiagnosa;
use App\Enums\StatusTagihan;
use App\Models\Icd10;
use App\Models\Kunjungan;
use App\Models\Penjamin;
use App\Models\TarifTindakan;
use App\Models\Tindakan;
use App\Models\User;
use App\Services\PemeriksaanKlinis;
use App\Services\PenyusunTagihan;
use App\Services\TindakanPelayanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagihanTest extends TestCase
{
    use RefreshDatabase;

    private Penjamin $umum;
    private Penjamin $bpjs;
    private Tindakan $konsultasi;
    private Tindakan $suntik;

    protected function setUp(): void
    {
        parent::setUp();

        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $this->bpjs = Penjamin::factory()->create(['kode' => 'BPJS', 'jenis' => 'penjamin']);
        $this->konsultasi = Tindakan::factory()->create(['nama' => 'Konsultasi Dokter Umum']);
        $this->suntik = Tindakan::factory()->create(['nama' => 'Injeksi Intramuskular']);

        foreach ([[$this->konsultasi, 50000, 35000], [$this->suntik, 25000, 15000]] as [$tindakan, $tarifUmum, $tarifBpjs]) {
            TarifTindakan::factory()->create([
                'tindakan_id' => $tindakan->id, 'penjamin_id' => $this->umum->id,
                'tarif' => $tarifUmum, 'berlaku_mulai' => '2026-01-01',
            ]);
            TarifTindakan::factory()->create([
                'tindakan_id' => $tindakan->id, 'penjamin_id' => $this->bpjs->id,
                'tarif' => $tarifBpjs, 'berlaku_mulai' => '2026-01-01',
            ]);
        }
    }

    private function kunjunganDenganTindakan(Penjamin $penjamin): Kunjungan
    {
        $kunjungan = Kunjungan::factory()->create([
            'penjamin_id' => $penjamin->id,
            'tanggal' => '2026-08-18',
            'no_kartu_penjamin' => $penjamin->ditanggung() ? '0001234567890' : null,
        ]);

        $petugas = User::factory()->create();
        app(TindakanPelayanan::class)->tambah($kunjungan, $this->konsultasi->id, 1, $petugas);
        app(TindakanPelayanan::class)->tambah($kunjungan, $this->suntik->id, 2, $petugas);

        return $kunjungan->refresh();
    }

    public function test_tagihan_disusun_dari_tindakan_dikali_tarif_sesuai_penjamin(): void
    {
        $tagihan = app(PenyusunTagihan::class)->susun($this->kunjunganDenganTindakan($this->umum));

        $this->assertSame(100000, (int) $tagihan->total);
        $this->assertSame(100000, (int) $tagihan->ditagihkan_ke_pasien);
        $this->assertSame(StatusTagihan::BelumBayar, $tagihan->status);
    }

    public function test_tagihan_pasien_bpjs_ditagihkan_nol_tapi_total_tetap_tercatat_penuh(): void
    {
        $tagihan = app(PenyusunTagihan::class)->susun($this->kunjunganDenganTindakan($this->bpjs));

        $this->assertSame(65000, (int) $tagihan->total);
        $this->assertSame(65000, (int) $tagihan->ditanggung_penjamin);
        $this->assertSame(0, (int) $tagihan->ditagihkan_ke_pasien);
        $this->assertSame(StatusTagihan::DitanggungPenjamin, $tagihan->status);
    }

    public function test_rincian_tagihan_memuat_setiap_tindakan_dengan_subtotalnya(): void
    {
        $tagihan = app(PenyusunTagihan::class)->susun($this->kunjunganDenganTindakan($this->umum));

        $this->assertSame(2, $tagihan->detail()->count());
        $this->assertDatabaseHas('tagihan_detail', [
            'tagihan_id' => $tagihan->id,
            'deskripsi' => 'Injeksi Intramuskular',
            'jumlah' => 2,
            'tarif_satuan' => 25000,
            'subtotal' => 50000,
        ]);
    }

    public function test_nomor_tagihan_berformat_tanggal_kunjungan(): void
    {
        $tagihan = app(PenyusunTagihan::class)->susun($this->kunjunganDenganTindakan($this->umum));

        $this->assertSame('TG-20260818-0001', $tagihan->no_tagihan);
    }

    public function test_tagihan_hanya_disusun_sekali(): void
    {
        $kunjungan = $this->kunjunganDenganTindakan($this->umum);
        $layanan = app(PenyusunTagihan::class);

        $pertama = $layanan->susun($kunjungan);
        $kedua = $layanan->susun($kunjungan->refresh());

        $this->assertSame($pertama->id, $kedua->id);
        $this->assertSame(1, $kunjungan->refresh()->tagihan()->count());
    }

    public function test_kunjungan_tanpa_tindakan_menghasilkan_tagihan_nol(): void
    {
        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]);

        $tagihan = app(PenyusunTagihan::class)->susun($kunjungan);

        $this->assertSame(0, (int) $tagihan->total);
    }

    public function test_tagihan_terbentuk_otomatis_saat_kunjungan_diselesaikan(): void
    {
        $kunjungan = $this->kunjunganDenganTindakan($this->umum);
        $dokter = User::factory()->create(['dokter_id' => $kunjungan->dokter_id]);
        $klinis = app(PemeriksaanKlinis::class);

        $klinis->catatSoap($kunjungan, [
            'subjective' => 'Demam dua hari', 'objective' => 'Suhu 38,5 °C',
            'assessment' => 'Demam tifoid', 'plan' => 'Antibiotik',
        ], $dokter);
        $klinis->tambahDiagnosa($kunjungan, Icd10::factory()->create()->id, JenisDiagnosa::Primer);
        $klinis->selesaikan($kunjungan, $dokter);

        $this->assertSame(100000, (int) $kunjungan->refresh()->tagihan->total);
    }
}
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=TagihanTest`
Diharapkan: FAIL dengan "Class App\Services\PenyusunTagihan not found".

- [ ] **Step 3: Tulis migration**

```bash
php artisan make:migration create_tagihan_tables
```

```php
Schema::create('tagihan', function (Blueprint $table) {
    $table->id();
    $table->string('no_tagihan', 20)->unique();
    $table->foreignId('kunjungan_id')->unique()->constrained('kunjungan')->cascadeOnDelete();
    $table->foreignId('penjamin_id')->constrained('penjamin');
    $table->unsignedBigInteger('total')->default(0);
    $table->unsignedBigInteger('ditanggung_penjamin')->default(0);
    $table->unsignedBigInteger('ditagihkan_ke_pasien')->default(0);
    $table->string('status', 25)->default('belum_bayar');
    $table->timestamp('disusun_pada')->nullable();
    $table->timestamps();
});

Schema::create('tagihan_detail', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tagihan_id')->constrained('tagihan')->cascadeOnDelete();
    $table->foreignId('tindakan_kunjungan_id')->nullable()->constrained('tindakan_kunjungan')->nullOnDelete();
    $table->string('deskripsi', 150);
    $table->unsignedSmallInteger('jumlah');
    $table->unsignedBigInteger('tarif_satuan');
    $table->unsignedBigInteger('subtotal');
    $table->timestamps();
});
```

- [ ] **Step 4: Tulis model**

`app/Models/Tagihan.php`:

```php
<?php

namespace App\Models;

use App\Enums\StatusTagihan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tagihan extends Model
{
    use HasFactory;

    protected $table = 'tagihan';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => StatusTagihan::class, 'disusun_pada' => 'datetime'];
    }

    public function kunjungan(): BelongsTo { return $this->belongsTo(Kunjungan::class); }
    public function penjamin(): BelongsTo { return $this->belongsTo(Penjamin::class); }
    public function detail(): HasMany { return $this->hasMany(TagihanDetail::class); }
    public function pembayaran(): HasMany { return $this->hasMany(Pembayaran::class); }
}
```

`app/Models/TagihanDetail.php` mengikuti pola sama dengan relasi `tagihan()`.

Buat juga `database/factories/TagihanFactory.php` — Task 14 memakainya:

```php
<?php

namespace Database\Factories;

use App\Enums\StatusTagihan;
use App\Models\Kunjungan;
use App\Models\Tagihan;
use App\Services\NomorDokumen;
use Illuminate\Database\Eloquent\Factories\Factory;

class TagihanFactory extends Factory
{
    protected $model = Tagihan::class;

    public function definition(): array
    {
        $kunjungan = Kunjungan::factory()->create();

        return [
            'no_tagihan' => app(NomorDokumen::class)->berikutnya('tagihan'),
            'kunjungan_id' => $kunjungan->id,
            'penjamin_id' => $kunjungan->penjamin_id,
            'total' => 0,
            'ditanggung_penjamin' => 0,
            'ditagihkan_ke_pasien' => 0,
            'status' => StatusTagihan::BelumBayar,
            'disusun_pada' => now(),
        ];
    }
}
```

- [ ] **Step 5: Tulis PenyusunTagihan**

`app/Services/PenyusunTagihan.php`:

```php
<?php

namespace App\Services;

use App\Enums\StatusTagihan;
use App\Models\Kunjungan;
use App\Models\Tagihan;
use Illuminate\Support\Facades\DB;

class PenyusunTagihan
{
    public function __construct(private readonly NomorDokumen $nomorDokumen) {}

    public function susun(Kunjungan $kunjungan): Tagihan
    {
        if ($kunjungan->tagihan !== null) {
            return $kunjungan->tagihan;
        }

        return DB::transaction(function () use ($kunjungan) {
            $baris = $kunjungan->tindakan()->with('tindakan')->get();
            $total = $baris->sum(fn ($item) => $item->subtotal());
            $ditanggung = $kunjungan->penjamin->ditanggung();

            $tagihan = Tagihan::create([
                'no_tagihan' => $this->nomorDokumen->berikutnya('tagihan', $kunjungan->tanggal),
                'kunjungan_id' => $kunjungan->id,
                'penjamin_id' => $kunjungan->penjamin_id,
                'total' => $total,
                'ditanggung_penjamin' => $ditanggung ? $total : 0,
                'ditagihkan_ke_pasien' => $ditanggung ? 0 : $total,
                'status' => $ditanggung ? StatusTagihan::DitanggungPenjamin : StatusTagihan::BelumBayar,
                'disusun_pada' => now(),
            ]);

            foreach ($baris as $item) {
                $tagihan->detail()->create([
                    'tindakan_kunjungan_id' => $item->id,
                    'deskripsi' => $item->tindakan->nama,
                    'jumlah' => $item->jumlah,
                    'tarif_satuan' => $item->tarif_satuan,
                    'subtotal' => $item->subtotal(),
                ]);
            }

            return $tagihan;
        });
    }
}
```

- [ ] **Step 6: Sambungkan ke penyelesaian kunjungan**

Di `app/Services/PemeriksaanKlinis.php`, tambahkan constructor dan panggil penyusun tagihan di dalam transaksi `selesaikan()`:

```php
public function __construct(private readonly PenyusunTagihan $penyusunTagihan) {}
```

Lalu di `selesaikan()`, ganti isi closure transaksi menjadi:

```php
return DB::transaction(function () use ($kunjungan) {
    $kunjungan->update([
        'status' => StatusKunjungan::Selesai,
        'waktu_selesai' => now(),
    ]);

    $this->penyusunTagihan->susun($kunjungan->refresh());

    return $kunjungan->refresh();
});
```

- [ ] **Step 7: Tambahkan Tagihan ke daftar model terauditkan**

```php
return [Pasien::class, Kunjungan::class, Pemeriksaan::class, Diagnosa::class, Tagihan::class];
```

- [ ] **Step 8: Jalankan test sampai lulus**

Run: `php artisan test --filter=TagihanTest`
Diharapkan: PASS, 7 test.

- [ ] **Step 9: Jalankan seluruh test untuk memastikan tidak ada yang rusak**

Run: `php artisan test`
Diharapkan: seluruh test dari Task 1–13 PASS. Bila `RekamMedisTest` gagal karena constructor `PemeriksaanKlinis` berubah, pastikan test memakai `app(PemeriksaanKlinis::class)` dan bukan `new PemeriksaanKlinis()`.

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "feat: susun tagihan otomatis saat kunjungan selesai, termasuk penanganan penjamin"
```

---

### Task 14: Pembayaran di kasir

**Files:**
- Create: migration `create_pembayaran_table`, `app/Models/Pembayaran.php`, `app/Services/ProsesPembayaran.php`, `app/Policies/TagihanPolicy.php`
- Test: `tests/Feature/PembayaranTest.php`

**Interfaces:**
- Consumes: `Tagihan` (Task 13), `MetodePembayaran` (Task 2), `NomorDokumen` (Task 5)
- Produces: `ProsesPembayaran::bayar(Tagihan $tagihan, MetodePembayaran $metode, int $nominal, User $kasir): Pembayaran`, `TagihanPolicy::proses(User $user, Tagihan $tagihan): bool`

Memenuhi aturan 15 dan 16.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/PembayaranTest.php`:

```php
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
```

`TagihanFactory` yang dipakai test ini mengambil `kunjungan_id` dari `Kunjungan::factory()`, `penjamin_id` dari kunjungan tersebut, dan `no_tagihan` dari `app(NomorDokumen::class)->berikutnya('tagihan')`.

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=PembayaranTest`
Diharapkan: FAIL dengan "Class App\Services\ProsesPembayaran not found".

- [ ] **Step 3: Tulis migration**

```bash
php artisan make:migration create_pembayaran_table
```

```php
Schema::create('pembayaran', function (Blueprint $table) {
    $table->id();
    $table->string('no_kuitansi', 20)->unique();
    $table->foreignId('tagihan_id')->constrained('tagihan')->cascadeOnDelete();
    $table->string('metode', 20);
    $table->unsignedBigInteger('nominal');
    $table->unsignedBigInteger('kembalian')->default(0);
    $table->foreignId('kasir_id')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('waktu_bayar');
    $table->timestamps();
});
```

- [ ] **Step 4: Tulis model Pembayaran**

`app/Models/Pembayaran.php`:

```php
<?php

namespace App\Models;

use App\Enums\MetodePembayaran;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pembayaran extends Model
{
    use HasFactory;

    protected $table = 'pembayaran';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['metode' => MetodePembayaran::class, 'waktu_bayar' => 'datetime'];
    }

    public function tagihan(): BelongsTo { return $this->belongsTo(Tagihan::class); }
    public function kasir(): BelongsTo { return $this->belongsTo(User::class, 'kasir_id'); }
}
```

- [ ] **Step 5: Tulis ProsesPembayaran**

`app/Services/ProsesPembayaran.php`:

```php
<?php

namespace App\Services;

use App\Enums\MetodePembayaran;
use App\Enums\StatusTagihan;
use App\Models\Pembayaran;
use App\Models\Tagihan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProsesPembayaran
{
    public function __construct(private readonly NomorDokumen $nomorDokumen) {}

    public function bayar(Tagihan $tagihan, MetodePembayaran $metode, int $nominal, User $kasir): Pembayaran
    {
        return DB::transaction(function () use ($tagihan, $metode, $nominal, $kasir) {
            $terkunci = Tagihan::whereKey($tagihan->id)->lockForUpdate()->first();

            if ($terkunci->status === StatusTagihan::Lunas) {
                throw new RuntimeException('Tagihan ini sudah lunas dan tidak bisa dibayar ulang.');
            }

            if ($terkunci->status !== StatusTagihan::BelumBayar) {
                throw new RuntimeException(
                    'Tagihan ini tidak ditagihkan ke pasien karena ditanggung penjamin.'
                );
            }

            $ditagihkan = (int) $terkunci->ditagihkan_ke_pasien;

            if ($nominal < $ditagihkan) {
                throw new RuntimeException('Nominal pembayaran kurang dari jumlah yang ditagihkan.');
            }

            if (! $metode->butuhKembalian() && $nominal !== $ditagihkan) {
                throw new RuntimeException(
                    'Pembayaran nontunai harus persis sejumlah tagihan.'
                );
            }

            $pembayaran = Pembayaran::create([
                'no_kuitansi' => $this->nomorDokumen->berikutnya('kuitansi'),
                'tagihan_id' => $terkunci->id,
                'metode' => $metode,
                'nominal' => $nominal,
                'kembalian' => $nominal - $ditagihkan,
                'kasir_id' => $kasir->id,
                'waktu_bayar' => now(),
            ]);

            $terkunci->update(['status' => StatusTagihan::Lunas]);

            return $pembayaran;
        });
    }
}
```

- [ ] **Step 6: Tulis TagihanPolicy**

`app/Policies/TagihanPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Models\Tagihan;
use App\Models\User;

class TagihanPolicy
{
    public function lihat(User $user, Tagihan $tagihan): bool
    {
        return $user->hasAnyRole([Peran::Kasir->value, Peran::Admin->value, Peran::RekamMedis->value]);
    }

    public function proses(User $user, Tagihan $tagihan): bool
    {
        return $user->hasRole(Peran::Kasir->value);
    }
}
```

- [ ] **Step 7: Jalankan test sampai lulus**

Run: `php artisan test --filter=PembayaranTest`
Diharapkan: PASS, 7 test.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: tambah proses pembayaran kasir beserta kuitansi dan penguncian tagihan"
```

---
### Task 15: Layar pendaftaran (admisi)

**Files:**
- Create: `app/Livewire/Pendaftaran/CariPasien.php`, `app/Livewire/Pendaftaran/FormPasien.php`, `app/Livewire/Pendaftaran/FormKunjungan.php`, `app/Livewire/Pendaftaran/PapanAntrian.php`, view masing-masing di `resources/views/livewire/pendaftaran/`, `resources/views/cetak/karcis.blade.php`
- Modify: `routes/web.php`, `bootstrap/app.php` (alias middleware `role`)
- Test: `tests/Feature/LayarPendaftaranTest.php`

**Interfaces:**
- Consumes: `PendaftaranPasien` (Task 6), `PendaftaranKunjungan` (Task 8), `Peran` (Task 2)
- Produces: rute bernama `pendaftaran.pasien`, `pendaftaran.pasien.baru`, `pendaftaran.pasien.ubah`, `pendaftaran.kunjungan`, `pendaftaran.antrian`, `cetak.karcis`. Semua di belakang middleware `role:admisi`.

- [ ] **Step 1: Daftarkan alias middleware peran**

Di `bootstrap/app.php`, dalam `->withMiddleware(...)`:

```php
$middleware->alias([
    'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
]);
```

- [ ] **Step 2: Tulis test yang gagal**

Buat `tests/Feature/LayarPendaftaranTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Livewire\Pendaftaran\FormKunjungan;
use App\Livewire\Pendaftaran\FormPasien;
use App\Models\Dokter;
use App\Models\Pasien;
use App\Models\Penjamin;
use App\Models\Poli;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LayarPendaftaranTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Peran::semua() as $peran) {
            Role::findOrCreate($peran);
        }
    }

    private function admisi(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Peran::Admisi->value);

        return $user;
    }

    public function test_petugas_admisi_bisa_membuka_layar_pencarian_pasien(): void
    {
        $this->actingAs($this->admisi())
            ->get(route('pendaftaran.pasien'))
            ->assertSuccessful();
    }

    public function test_dokter_tidak_bisa_membuka_layar_pendaftaran(): void
    {
        $dokter = User::factory()->create();
        $dokter->assignRole(Peran::Dokter->value);

        $this->actingAs($dokter)
            ->get(route('pendaftaran.pasien'))
            ->assertForbidden();
    }

    public function test_pendaftaran_pasien_baru_lewat_layar_membuat_pasien_bernomor_rm(): void
    {
        Livewire::actingAs($this->admisi())
            ->test(FormPasien::class)
            ->set('nik', '3202011203900001')
            ->set('nama', 'Siti Aminah')
            ->set('tempat_lahir', 'Kabupaten Sampel')
            ->set('tanggal_lahir', '1990-03-12')
            ->set('jenis_kelamin', 'P')
            ->set('alamat', 'Jl. Melati No. 12')
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('pasien', ['nik' => '3202011203900001', 'no_rm' => '000001']);
    }

    public function test_nik_tidak_sah_menampilkan_pesan_validasi(): void
    {
        Livewire::actingAs($this->admisi())
            ->test(FormPasien::class)
            ->set('nik', '123')
            ->set('nama', 'Siti Aminah')
            ->set('tanggal_lahir', '1990-03-12')
            ->set('jenis_kelamin', 'P')
            ->set('alamat', 'Jl. Melati No. 12')
            ->call('simpan')
            ->assertHasErrors('nik');
    }

    public function test_membuat_kunjungan_lewat_layar_menghasilkan_nomor_antrian(): void
    {
        $pasien = Pasien::factory()->create();
        $poli = Poli::factory()->create(['kode' => 'UMU']);
        $dokter = Dokter::factory()->create(['poli_id' => $poli->id]);
        $umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);

        Livewire::actingAs($this->admisi())
            ->test(FormKunjungan::class, ['pasien' => $pasien])
            ->set('poli_id', $poli->id)
            ->set('dokter_id', $dokter->id)
            ->set('penjamin_id', $umum->id)
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('antrian', ['poli_id' => $poli->id, 'nomor' => 1]);
    }

    public function test_pasien_bpjs_tanpa_nomor_kartu_menampilkan_pesan_validasi(): void
    {
        $pasien = Pasien::factory()->create();
        $poli = Poli::factory()->create(['kode' => 'UMU']);
        $dokter = Dokter::factory()->create(['poli_id' => $poli->id]);
        $bpjs = Penjamin::factory()->create(['kode' => 'BPJS', 'jenis' => 'penjamin']);

        Livewire::actingAs($this->admisi())
            ->test(FormKunjungan::class, ['pasien' => $pasien])
            ->set('poli_id', $poli->id)
            ->set('dokter_id', $dokter->id)
            ->set('penjamin_id', $bpjs->id)
            ->call('simpan')
            ->assertHasErrors('no_kartu_penjamin');
    }

    public function test_pencarian_pasien_bersifat_partial_dan_tidak_peduli_huruf_besar_kecil(): void
    {
        Pasien::factory()->create(['nama' => 'Siti Aminah']);

        Livewire::actingAs($this->admisi())
            ->test(\App\Livewire\Pendaftaran\CariPasien::class)
            ->set('kata', 'AMINAH')
            ->assertSee('Siti Aminah');
    }
}
```

- [ ] **Step 3: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=LayarPendaftaranTest`
Diharapkan: FAIL — rute dan komponen belum ada.

- [ ] **Step 4: Tulis komponen FormPasien**

`app/Livewire/Pendaftaran/FormPasien.php`:

```php
<?php

namespace App\Livewire\Pendaftaran;

use App\Models\Pasien;
use App\Services\PendaftaranPasien;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class FormPasien extends Component
{
    public ?Pasien $pasien = null;

    public string $nik = '';
    public string $nama = '';
    public string $tempat_lahir = '';
    public string $tanggal_lahir = '';
    public string $jenis_kelamin = '';
    public string $alamat = '';
    public string $rt = '';
    public string $rw = '';
    public string $kelurahan = '';
    public string $kecamatan = '';
    public string $kabupaten = '';
    public string $no_hp = '';

    public function mount(?Pasien $pasien = null): void
    {
        if ($pasien?->exists) {
            $this->pasien = $pasien;
            $this->fill($pasien->only([
                'nik', 'nama', 'tempat_lahir', 'jenis_kelamin', 'alamat',
                'rt', 'rw', 'kelurahan', 'kecamatan', 'kabupaten', 'no_hp',
            ]));
            $this->tanggal_lahir = $pasien->tanggal_lahir->toDateString();
        }
    }

    public function simpan(PendaftaranPasien $layanan)
    {
        $data = $this->only([
            'nik', 'nama', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin',
            'alamat', 'rt', 'rw', 'kelurahan', 'kecamatan', 'kabupaten', 'no_hp',
        ]);

        try {
            $pasien = $this->pasien
                ? $layanan->perbarui($this->pasien, $data)
                : $layanan->daftarkan($data);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kolom => $pesan) {
                $this->addError($kolom, $pesan[0]);
            }

            return null;
        }

        session()->flash('sukses', "Pasien tersimpan dengan nomor RM {$pasien->no_rm}.");

        return $this->redirectRoute('pendaftaran.kunjungan', ['pasien' => $pasien->id], navigate: true);
    }

    public function render()
    {
        return view('livewire.pendaftaran.form-pasien');
    }
}
```

Pola `try/catch` di atas dipakai di seluruh komponen Livewire pada tugas-tugas berikutnya: aturan validasi hanya hidup di Service, dan komponen sekadar memindahkan pesannya ke layar.

- [ ] **Step 5: Tulis komponen CariPasien, FormKunjungan, dan PapanAntrian**

`app/Livewire/Pendaftaran/CariPasien.php`:

```php
<?php

namespace App\Livewire\Pendaftaran;

use App\Models\Pasien;
use Livewire\Component;
use Livewire\WithPagination;

class CariPasien extends Component
{
    use WithPagination;

    public string $kata = '';

    public function updatingKata(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $pasien = trim($this->kata) === ''
            ? Pasien::query()->latest('id')->paginate(10)
            : Pasien::cari(trim($this->kata))->paginate(10);

        return view('livewire.pendaftaran.cari-pasien', ['daftarPasien' => $pasien]);
    }
}
```

`app/Livewire/Pendaftaran/FormKunjungan.php`:

```php
<?php

namespace App\Livewire\Pendaftaran;

use App\Models\Dokter;
use App\Models\Pasien;
use App\Models\Penjamin;
use App\Models\Poli;
use App\Services\PendaftaranKunjungan;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class FormKunjungan extends Component
{
    public Pasien $pasien;

    public ?int $poli_id = null;
    public ?int $dokter_id = null;
    public ?int $penjamin_id = null;
    public string $no_kartu_penjamin = '';

    public function mount(Pasien $pasien): void
    {
        $this->pasien = $pasien;
    }

    public function simpan(PendaftaranKunjungan $layanan)
    {
        try {
            $kunjungan = $layanan->daftarkan([
                'pasien_id' => $this->pasien->id,
                'poli_id' => $this->poli_id,
                'dokter_id' => $this->dokter_id,
                'penjamin_id' => $this->penjamin_id,
                'no_kartu_penjamin' => $this->no_kartu_penjamin ?: null,
                'tanggal' => now()->toDateString(),
            ], auth()->user());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kolom => $pesan) {
                $this->addError($kolom, $pesan[0]);
            }

            return null;
        }

        return $this->redirectRoute('cetak.karcis', ['antrian' => $kunjungan->antrian->id]);
    }

    public function render()
    {
        return view('livewire.pendaftaran.form-kunjungan', [
            'daftarPoli' => Poli::where('aktif', true)->orderBy('nama')->get(),
            'daftarDokter' => $this->poli_id
                ? Dokter::where('poli_id', $this->poli_id)->where('aktif', true)->get()
                : collect(),
            'daftarPenjamin' => Penjamin::where('aktif', true)->get(),
        ]);
    }
}
```

`app/Livewire/Pendaftaran/PapanAntrian.php` menampilkan `Antrian::with('kunjungan.pasien', 'poli')->whereDate('tanggal', today())->orderBy('poli_id')->orderBy('nomor')->get()`.

- [ ] **Step 6: Tulis view**

`resources/views/livewire/pendaftaran/form-pasien.blade.php` memuat form dengan `wire:model` untuk setiap properti, menampilkan `@error('nik') ... @enderror` di bawah tiap isian, dan tombol `wire:click="simpan"`. `cari-pasien.blade.php` memuat kotak `wire:model.live.debounce.300ms="kata"` dan tabel hasil berisi kolom No. RM, NIK, Nama, Tanggal Lahir, serta tombol "Buat Kunjungan" menuju `route('pendaftaran.kunjungan', $pasien)`. `form-kunjungan.blade.php` memuat tiga `select` (poli, dokter, penjamin) plus isian nomor kartu yang muncul ketika penjamin terpilih berjenis `penjamin`.

`resources/views/cetak/karcis.blade.php`:

```blade
<x-layouts.app :judul="'Karcis Antrian'">
    <div class="max-w-xs mx-auto bg-white p-4 text-center border">
        <p class="text-sm">{{ config('app.name') }}</p>
        <p class="text-sm">{{ $antrian->poli->nama }}</p>
        <p class="text-5xl font-bold my-3">{{ $antrian->kode() }}</p>
        <p class="text-sm">{{ $antrian->kunjungan->pasien->nama }}</p>
        <p class="text-xs">No. RM {{ $antrian->kunjungan->pasien->no_rm }}</p>
        <p class="text-xs">{{ $antrian->tanggal->format('d/m/Y') }} — {{ $antrian->kunjungan->dokter->nama }}</p>
    </div>
    <button onclick="window.print()" class="mx-auto mt-4 block bg-blue-600 text-white px-4 py-2 rounded print:hidden">
        Cetak
    </button>
</x-layouts.app>
```

- [ ] **Step 7: Daftarkan rute**

Di `routes/web.php`, di dalam grup `auth`:

```php
Route::middleware('role:admisi')->group(function () {
    Route::get('/pendaftaran/pasien', CariPasien::class)->name('pendaftaran.pasien');
    Route::get('/pendaftaran/pasien/baru', FormPasien::class)->name('pendaftaran.pasien.baru');
    Route::get('/pendaftaran/pasien/{pasien}/ubah', FormPasien::class)->name('pendaftaran.pasien.ubah');
    Route::get('/pendaftaran/kunjungan/{pasien}', FormKunjungan::class)->name('pendaftaran.kunjungan');
    Route::get('/pendaftaran/antrian', PapanAntrian::class)->name('pendaftaran.antrian');
    Route::get('/cetak/karcis/{antrian}', fn (\App\Models\Antrian $antrian) => view('cetak.karcis', compact('antrian')))
        ->name('cetak.karcis');
});
```

- [ ] **Step 8: Jalankan test sampai lulus**

Run: `php artisan test --filter=LayarPendaftaranTest`
Diharapkan: PASS, 7 test.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: tambah layar pendaftaran pasien, kunjungan, papan antrian, dan cetak karcis"
```

---

### Task 16: Layar poli (perawat dan dokter)

**Files:**
- Create: `app/Livewire/Poli/AntrianPoli.php`, `app/Livewire/Poli/FormVital.php`, `app/Livewire/Poli/FormSoap.php`, `app/Livewire/Poli/FormResep.php`, view masing-masing
- Modify: `routes/web.php`
- Test: `tests/Feature/LayarPoliTest.php`

**Interfaces:**
- Consumes: `PemeriksaanKlinis` (Task 9/10), `TindakanPelayanan` (Task 11), `PenulisanResep` (Task 12), `KunjunganPolicy` (Task 10)
- Produces: rute `poli.antrian` (perawat & dokter), `poli.vital` (perawat), `poli.soap` (dokter), `poli.resep` (dokter).

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/LayarPoliTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Enums\StatusKunjungan;
use App\Livewire\Poli\FormSoap;
use App\Livewire\Poli\FormVital;
use App\Models\Dokter;
use App\Models\Icd10;
use App\Models\Kunjungan;
use App\Models\Penjamin;
use App\Models\Poli;
use App\Models\TarifTindakan;
use App\Models\Tindakan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LayarPoliTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Peran::semua() as $peran) {
            Role::findOrCreate($peran);
        }
    }

    private function penggunaBerperan(string $peran, array $atribut = []): User
    {
        $user = User::factory()->create($atribut);
        $user->assignRole($peran);

        return $user;
    }

    private function kunjunganSiapPeriksa(): Kunjungan
    {
        $umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $poli = Poli::factory()->create(['kode' => 'UMU']);
        $dokter = Dokter::factory()->create(['poli_id' => $poli->id]);

        return Kunjungan::factory()->create([
            'poli_id' => $poli->id,
            'dokter_id' => $dokter->id,
            'penjamin_id' => $umum->id,
            'tanggal' => now()->toDateString(),
        ]);
    }

    private function tindakanBertarif(Kunjungan $kunjungan, int $tarif = 50000): Tindakan
    {
        $tindakan = Tindakan::factory()->create(['nama' => 'Konsultasi Dokter Umum']);

        TarifTindakan::factory()->create([
            'tindakan_id' => $tindakan->id,
            'penjamin_id' => $kunjungan->penjamin_id,
            'tarif' => $tarif,
            'berlaku_mulai' => '2026-01-01',
        ]);

        return $tindakan;
    }

    public function test_perawat_mengisi_vital_lewat_layar(): void
    {
        $kunjungan = $this->kunjunganSiapPeriksa();
        $perawat = $this->penggunaBerperan(Peran::Perawat->value);

        Livewire::actingAs($perawat)
            ->test(FormVital::class, ['kunjungan' => $kunjungan])
            ->set('sistolik', 120)->set('diastolik', 80)->set('nadi', 78)
            ->set('suhu', 36.7)->set('respirasi', 18)
            ->set('berat_badan', 62.5)->set('tinggi_badan', 165)
            ->set('keluhan_awal', 'Batuk tiga hari')
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertSame(StatusKunjungan::DiperiksaPerawat, $kunjungan->refresh()->status);
    }

    public function test_suhu_tidak_wajar_menampilkan_pesan_validasi_di_layar(): void
    {
        $kunjungan = $this->kunjunganSiapPeriksa();

        Livewire::actingAs($this->penggunaBerperan(Peran::Perawat->value))
            ->test(FormVital::class, ['kunjungan' => $kunjungan])
            ->set('sistolik', 120)->set('diastolik', 80)->set('nadi', 78)
            ->set('suhu', 55)->set('respirasi', 18)
            ->set('berat_badan', 62.5)->set('tinggi_badan', 165)
            ->set('keluhan_awal', 'Batuk tiga hari')
            ->call('simpan')
            ->assertHasErrors('suhu');
    }

    public function test_kasir_tidak_bisa_membuka_form_soap(): void
    {
        $kunjungan = $this->kunjunganSiapPeriksa();

        $this->actingAs($this->penggunaBerperan(Peran::Kasir->value))
            ->get(route('poli.soap', $kunjungan))
            ->assertForbidden();
    }

    public function test_dokter_poli_lain_tidak_bisa_membuka_form_soap(): void
    {
        $kunjungan = $this->kunjunganSiapPeriksa();
        $poliLain = Poli::factory()->create(['kode' => 'GIG']);
        $dokterLain = Dokter::factory()->create(['poli_id' => $poliLain->id]);
        $user = $this->penggunaBerperan(Peran::Dokter->value, ['dokter_id' => $dokterLain->id]);

        $this->actingAs($user)->get(route('poli.soap', $kunjungan))->assertForbidden();
    }

    public function test_dokter_menyelesaikan_kunjungan_lewat_layar_dan_tagihan_terbentuk(): void
    {
        $kunjungan = $this->kunjunganSiapPeriksa();
        $tindakan = $this->tindakanBertarif($kunjungan);
        $icd = Icd10::factory()->create();
        $dokter = $this->penggunaBerperan(Peran::Dokter->value, ['dokter_id' => $kunjungan->dokter_id]);

        Livewire::actingAs($dokter)
            ->test(FormSoap::class, ['kunjungan' => $kunjungan])
            ->set('subjective', 'Batuk tiga hari')->set('objective', 'Faring hiperemis')
            ->set('assessment', 'ISPA')->set('plan', 'Antibiotik')
            ->call('simpanSoap')
            ->set('icd10_id', $icd->id)
            ->call('tambahDiagnosaPrimer')
            ->set('tindakan_id', $tindakan->id)
            ->set('jumlah_tindakan', 1)
            ->call('tambahTindakan')
            ->call('selesaikan')
            ->assertHasNoErrors();

        $kunjungan->refresh();

        $this->assertSame(StatusKunjungan::Selesai, $kunjungan->status);
        $this->assertSame(50000, (int) $kunjungan->tagihan->total);
    }

    public function test_menyelesaikan_tanpa_diagnosa_menampilkan_pesan_kesalahan(): void
    {
        $kunjungan = $this->kunjunganSiapPeriksa();
        $dokter = $this->penggunaBerperan(Peran::Dokter->value, ['dokter_id' => $kunjungan->dokter_id]);

        Livewire::actingAs($dokter)
            ->test(FormSoap::class, ['kunjungan' => $kunjungan])
            ->set('subjective', 'Batuk')->set('objective', 'Faring hiperemis')
            ->set('assessment', 'ISPA')->set('plan', 'Antibiotik')
            ->call('simpanSoap')
            ->call('selesaikan')
            ->assertHasErrors('penyelesaian');

        $this->assertSame(StatusKunjungan::DiperiksaDokter, $kunjungan->refresh()->status);
    }
}
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=LayarPoliTest`
Diharapkan: FAIL — komponen dan rute belum ada.

- [ ] **Step 3: Tulis FormVital**

`app/Livewire/Poli/FormVital.php` menyimpan properti `sistolik`, `diastolik`, `nadi`, `suhu`, `respirasi`, `berat_badan`, `tinggi_badan`, `keluhan_awal`, `alergi`, dan memanggil `PemeriksaanKlinis::catatVital()` di dalam `simpan()` dengan pola `try/catch (ValidationException)` yang sama seperti `FormPasien`.

- [ ] **Step 4: Tulis FormSoap**

`app/Livewire/Poli/FormSoap.php`:

```php
<?php

namespace App\Livewire\Poli;

use App\Enums\JenisDiagnosa;
use App\Models\Icd10;
use App\Models\Kunjungan;
use App\Models\Tindakan;
use App\Services\PemeriksaanKlinis;
use App\Services\TindakanPelayanan;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use RuntimeException;

class FormSoap extends Component
{
    public Kunjungan $kunjungan;

    public string $subjective = '';
    public string $objective = '';
    public string $assessment = '';
    public string $plan = '';
    public ?int $icd10_id = null;
    public ?int $tindakan_id = null;
    public int $jumlah_tindakan = 1;

    public function mount(Kunjungan $kunjungan): void
    {
        $this->authorize('periksa', $kunjungan);

        $this->kunjungan = $kunjungan;

        if ($pemeriksaan = $kunjungan->pemeriksaan) {
            $this->fill($pemeriksaan->only(['subjective', 'objective', 'assessment', 'plan']));
        }
    }

    public function simpanSoap(PemeriksaanKlinis $layanan): void
    {
        $this->jalankan(fn () => $layanan->catatSoap(
            $this->kunjungan,
            $this->only(['subjective', 'objective', 'assessment', 'plan']),
            auth()->user()
        ));
    }

    public function tambahDiagnosaPrimer(PemeriksaanKlinis $layanan): void
    {
        $this->jalankan(fn () => $layanan->tambahDiagnosa(
            $this->kunjungan, (int) $this->icd10_id, JenisDiagnosa::Primer
        ));
    }

    public function tambahDiagnosaSekunder(PemeriksaanKlinis $layanan): void
    {
        $this->jalankan(fn () => $layanan->tambahDiagnosa(
            $this->kunjungan, (int) $this->icd10_id, JenisDiagnosa::Sekunder
        ));
    }

    public function tambahTindakan(TindakanPelayanan $layanan): void
    {
        $this->jalankan(fn () => $layanan->tambah(
            $this->kunjungan, (int) $this->tindakan_id, $this->jumlah_tindakan, auth()->user()
        ));
    }

    public function selesaikan(PemeriksaanKlinis $layanan): void
    {
        $this->jalankan(fn () => $layanan->selesaikan($this->kunjungan, auth()->user()));
    }

    private function jalankan(callable $aksi): void
    {
        try {
            $aksi();
            $this->kunjungan->refresh();
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kolom => $pesan) {
                $this->addError($kolom, $pesan[0]);
            }
        } catch (RuntimeException $e) {
            $this->addError('penyelesaian', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.poli.form-soap', [
            'daftarIcd' => Icd10::orderBy('kode')->limit(50)->get(),
            'daftarTindakan' => Tindakan::where('aktif', true)->orderBy('nama')->get(),
            'riwayat' => $this->kunjungan->pasien->kunjungan()
                ->where('id', '!=', $this->kunjungan->id)
                ->with('pemeriksaan', 'diagnosa.icd10')
                ->latest('tanggal')->limit(5)->get(),
        ]);
    }
}
```

`RuntimeException` dipetakan ke kunci error `penyelesaian` supaya pesan "diagnosa primer belum ditetapkan" tampil di dekat tombol Selesaikan, bukan hilang sebagai error 500.

Setiap komponen Livewire yang memanggil `$this->authorize(...)` — `FormSoap`, `FormResep`, dan `Kasir\ProsesPembayaran` — wajib memakai trait berikut, karena `Livewire\Component` tidak menyertakannya sendiri:

```php
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class FormSoap extends Component
{
    use AuthorizesRequests;
    // ...
}
```

- [ ] **Step 5: Tulis AntrianPoli dan FormResep**

`app/Livewire/Poli/AntrianPoli.php` menampilkan antrian hari ini pada poli pengguna (`auth()->user()->dokter?->poli_id`, atau pilihan poli untuk perawat), dengan method `panggil(int $antrianId)` yang mengubah status antrian menjadi `Dipanggil` dan mengisi `waktu_panggil`. `app/Livewire/Poli/FormResep.php` mengelola array `item` dan memanggil `PenulisanResep::tulis()`.

- [ ] **Step 6: Daftarkan rute**

```php
Route::middleware('role:perawat|dokter')->group(function () {
    Route::get('/poli/antrian', AntrianPoli::class)->name('poli.antrian');
});

Route::middleware('role:perawat')->group(function () {
    Route::get('/poli/vital/{kunjungan}', FormVital::class)->name('poli.vital');
});

Route::middleware('role:dokter')->group(function () {
    Route::get('/poli/soap/{kunjungan}', FormSoap::class)->name('poli.soap');
    Route::get('/poli/resep/{kunjungan}', FormResep::class)->name('poli.resep');
});
```

- [ ] **Step 7: Jalankan test sampai lulus**

Run: `php artisan test --filter=LayarPoliTest`
Diharapkan: PASS, 6 test.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: tambah layar poli untuk perawat dan dokter"
```

---

### Task 17: Layar kasir

**Files:**
- Create: `app/Livewire/Kasir/DaftarTagihan.php`, `app/Livewire/Kasir/ProsesPembayaran.php`, view masing-masing, `resources/views/cetak/kuitansi.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/LayarKasirTest.php`

**Interfaces:**
- Consumes: `ProsesPembayaran` service (Task 14), `TagihanPolicy` (Task 14)
- Produces: rute `kasir.tagihan`, `kasir.bayar`, `cetak.kuitansi`.

Nama kelas komponen Livewire `App\Livewire\Kasir\ProsesPembayaran` sengaja sama dengan nama service `App\Services\ProsesPembayaran` tetapi berbeda namespace; di dalam komponen, service disuntikkan lewat parameter method agar tidak perlu alias.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/LayarKasirTest.php`:

```php
public function test_kasir_melihat_daftar_tagihan_belum_lunas(): void
{
    $tagihan = Tagihan::factory()->create([
        'status' => StatusTagihan::BelumBayar, 'ditagihkan_ke_pasien' => 100000,
    ]);

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
    $tagihan = Tagihan::factory()->create([
        'status' => StatusTagihan::BelumBayar, 'total' => 100000, 'ditagihkan_ke_pasien' => 100000,
    ]);

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
    $tagihan = Tagihan::factory()->create([
        'status' => StatusTagihan::BelumBayar, 'total' => 100000, 'ditagihkan_ke_pasien' => 100000,
    ]);

    Livewire::actingAs($this->kasir())
        ->test(ProsesPembayaranLayar::class, ['tagihan' => $tagihan])
        ->set('metode', 'tunai')->set('nominal', 90000)
        ->call('bayar')
        ->assertHasErrors('nominal');
}

public function test_perawat_tidak_bisa_membuka_layar_kasir(): void
{
    $this->actingAs($this->penggunaBerperan(Peran::Perawat->value))
        ->get(route('kasir.tagihan'))
        ->assertForbidden();
}
```

Di bagian `use`, impor komponen dengan alias supaya tidak bentrok dengan service:

```php
use App\Livewire\Kasir\ProsesPembayaran as ProsesPembayaranLayar;
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=LayarKasirTest`
Diharapkan: FAIL — komponen belum ada.

- [ ] **Step 3: Tulis komponen**

`app/Livewire/Kasir/DaftarTagihan.php` menampilkan `Tagihan::with('kunjungan.pasien')->where('status', StatusTagihan::BelumBayar)->latest('id')->paginate(15)`.

`app/Livewire/Kasir/ProsesPembayaran.php`:

```php
<?php

namespace App\Livewire\Kasir;

use App\Enums\MetodePembayaran;
use App\Models\Tagihan;
use App\Services\ProsesPembayaran as LayananPembayaran;
use Livewire\Component;
use RuntimeException;

class ProsesPembayaran extends Component
{
    public Tagihan $tagihan;

    public string $metode = 'tunai';
    public int $nominal = 0;

    public function mount(Tagihan $tagihan): void
    {
        $this->authorize('proses', $tagihan);

        $this->tagihan = $tagihan;
        $this->nominal = (int) $tagihan->ditagihkan_ke_pasien;
    }

    public function bayar(LayananPembayaran $layanan)
    {
        try {
            $pembayaran = $layanan->bayar(
                $this->tagihan,
                MetodePembayaran::from($this->metode),
                $this->nominal,
                auth()->user()
            );
        } catch (RuntimeException $e) {
            $this->addError('nominal', $e->getMessage());

            return null;
        }

        return $this->redirectRoute('cetak.kuitansi', ['pembayaran' => $pembayaran->id]);
    }

    public function render()
    {
        return view('livewire.kasir.proses-pembayaran');
    }
}
```

- [ ] **Step 4: Tulis view kuitansi**

`resources/views/cetak/kuitansi.blade.php` menampilkan nama RS, nomor kuitansi, nomor RM & nama pasien, tanggal, rincian tagihan (`$pembayaran->tagihan->detail`), total, nominal dibayar, kembalian, nama kasir, dan tombol cetak yang disembunyikan saat pencetakan (`print:hidden`).

- [ ] **Step 5: Daftarkan rute**

```php
Route::middleware('role:kasir')->group(function () {
    Route::get('/kasir/tagihan', DaftarTagihan::class)->name('kasir.tagihan');
    Route::get('/kasir/bayar/{tagihan}', ProsesPembayaran::class)->name('kasir.bayar');
    Route::get('/cetak/kuitansi/{pembayaran}', fn (\App\Models\Pembayaran $pembayaran) => view('cetak.kuitansi', compact('pembayaran')))
        ->name('cetak.kuitansi');
});
```

- [ ] **Step 6: Jalankan test sampai lulus**

Run: `php artisan test --filter=LayarKasirTest`
Diharapkan: PASS, 5 test.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: tambah layar kasir untuk tagihan, pembayaran, dan kuitansi"
```

---
### Task 18: Layar master data, admin, dan rekam medis

**Files:**
- Create: `app/Livewire/Master/DaftarPoli.php`, `app/Livewire/Master/DaftarDokter.php`, `app/Livewire/Master/DaftarTindakan.php`, `app/Livewire/Master/DaftarTarif.php`, `app/Livewire/Admin/KelolaUser.php`, `app/Livewire/Admin/PenampilAuditLog.php`, `app/Livewire/RekamMedis/PenelusuranRekamMedis.php`, `app/Livewire/RekamMedis/KoreksiPasien.php`, `app/Livewire/RekamMedis/RekapKunjunganHarian.php`, view masing-masing
- Modify: `routes/web.php`
- Test: `tests/Feature/LayarAdminTest.php`, `tests/Feature/LayarRekamMedisTest.php`

**Interfaces:**
- Consumes: model master (Task 3), `AuditLog` (Task 7), `Peran` (Task 2)
- Produces: rute `master.poli`, `master.dokter`, `master.tindakan`, `master.tarif`, `admin.user`, `admin.audit` di belakang `role:admin`; serta `rekam-medis.telusur`, `rekam-medis.koreksi`, `rekam-medis.rekap` di belakang `role:rekam_medis`.

Bagian rekam medis memenuhi kewenangan peran `rekam_medis` pada spec bagian 4 dan layar peran tersebut pada spec bagian 2a — satu-satunya peran yang layarnya belum tersentuh Task 15–17.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/LayarAdminTest.php`:

```php
public function test_admin_bisa_menambah_poli(): void
{
    Livewire::actingAs($this->admin())
        ->test(DaftarPoli::class)
        ->set('kode', 'ANK')->set('nama', 'Poli Anak')->set('lokasi', 'Lantai 2')
        ->call('simpan')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('poli', ['kode' => 'ANK', 'nama' => 'Poli Anak']);
}

public function test_kode_poli_ganda_ditolak(): void
{
    Poli::factory()->create(['kode' => 'ANK']);

    Livewire::actingAs($this->admin())
        ->test(DaftarPoli::class)
        ->set('kode', 'ANK')->set('nama', 'Poli Anak Duplikat')
        ->call('simpan')
        ->assertHasErrors('kode');
}

public function test_admin_bisa_membuat_pengguna_dengan_peran(): void
{
    Livewire::actingAs($this->admin())
        ->test(KelolaUser::class)
        ->set('name', 'Kasir Satu')->set('email', 'kasir1@rs.test')
        ->set('password', 'rahasia123')->set('peran', Peran::Kasir->value)
        ->call('simpan')
        ->assertHasNoErrors();

    $this->assertTrue(User::where('email', 'kasir1@rs.test')->first()->hasRole(Peran::Kasir->value));
}

public function test_penampil_audit_log_menunjukkan_perubahan_beserta_pelakunya(): void
{
    $petugas = $this->penggunaBerperan(Peran::Admisi->value, ['name' => 'Petugas Admisi']);
    $this->actingAs($petugas);
    $pasien = Pasien::factory()->create(['nama' => 'Pasien Uji']);
    $pasien->update(['nama' => 'Pasien Uji Diperbarui']);

    Livewire::actingAs($this->admin())
        ->test(PenampilAuditLog::class)
        ->assertSee('Petugas Admisi')
        ->assertSee('update');
}

public function test_kasir_tidak_bisa_membuka_audit_log(): void
{
    $this->actingAs($this->penggunaBerperan(Peran::Kasir->value))
        ->get(route('admin.audit'))
        ->assertForbidden();
}
```

Lengkapi dengan `setUp()` pembuat peran dan helper `admin()`/`penggunaBerperan()` seperti pada test layar sebelumnya.

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=LayarAdminTest`
Diharapkan: FAIL — komponen belum ada.

- [ ] **Step 3: Tulis komponen master data**

`app/Livewire/Master/DaftarPoli.php`:

```php
<?php

namespace App\Livewire\Master;

use App\Models\Poli;
use Livewire\Component;
use Livewire\WithPagination;

class DaftarPoli extends Component
{
    use WithPagination;

    public ?int $poliId = null;
    public string $kode = '';
    public string $nama = '';
    public string $lokasi = '';
    public bool $aktif = true;

    public function simpan(): void
    {
        $data = $this->validate([
            'kode' => ['required', 'string', 'max:10', 'unique:poli,kode,'.($this->poliId ?? 'NULL').',id'],
            'nama' => ['required', 'string', 'max:100'],
            'lokasi' => ['nullable', 'string', 'max:100'],
            'aktif' => ['boolean'],
        ], [
            'kode.required' => 'Kode poli wajib diisi.',
            'kode.unique' => 'Kode poli ini sudah dipakai.',
            'nama.required' => 'Nama poli wajib diisi.',
        ]);

        Poli::updateOrCreate(['id' => $this->poliId], $data);

        $this->reset(['poliId', 'kode', 'nama', 'lokasi']);
        session()->flash('sukses', 'Data poli tersimpan.');
    }

    public function sunting(int $id): void
    {
        $poli = Poli::findOrFail($id);
        $this->poliId = $poli->id;
        $this->fill($poli->only(['kode', 'nama', 'lokasi', 'aktif']));
    }

    public function render()
    {
        return view('livewire.master.daftar-poli', [
            'daftarPoli' => Poli::orderBy('kode')->paginate(10),
        ]);
    }
}
```

`DaftarDokter`, `DaftarTindakan`, dan `DaftarTarif` mengikuti pola yang sama persis: properti isian, `simpan()` dengan `$this->validate()` berpesan bahasa Indonesia, `sunting()`, dan `render()` yang mengirim daftar terpaginasi. Untuk `DaftarTarif`, isiannya adalah `tindakan_id`, `penjamin_id`, `tarif`, dan `berlaku_mulai`, dengan aturan unik gabungan ketiganya.

- [ ] **Step 4: Tulis KelolaUser dan PenampilAuditLog**

`app/Livewire/Admin/KelolaUser.php` menyimpan `name`, `email`, `password`, `peran`, `dokter_id`, memvalidasi (`email` unik, `password` minimal 8 karakter, `peran` harus salah satu dari `Peran::semua()`), membuat user, lalu memanggil `syncRoles([$this->peran])`.

`app/Livewire/Admin/PenampilAuditLog.php`:

```php
<?php

namespace App\Livewire\Admin;

use App\Models\AuditLog;
use Livewire\Component;
use Livewire\WithPagination;

class PenampilAuditLog extends Component
{
    use WithPagination;

    public string $filterModel = '';
    public string $filterAksi = '';

    public function render()
    {
        $catatan = AuditLog::with('user')
            ->when($this->filterModel !== '', fn ($q) => $q->where('model_tipe', $this->filterModel))
            ->when($this->filterAksi !== '', fn ($q) => $q->where('aksi', $this->filterAksi))
            ->latest('id')
            ->paginate(25);

        return view('livewire.admin.penampil-audit-log', ['catatan' => $catatan]);
    }
}
```

Viewnya menampilkan kolom Waktu, Pelaku (`$baris->user?->name ?? 'Sistem'`), Aksi, Model, ID, Alasan, dan ringkasan perubahan.

- [ ] **Step 5: Tulis test layar rekam medis yang gagal**

Buat `tests/Feature/LayarRekamMedisTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Livewire\RekamMedis\KoreksiPasien;
use App\Livewire\RekamMedis\PenelusuranRekamMedis;
use App\Livewire\RekamMedis\RekapKunjunganHarian;
use App\Models\AuditLog;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LayarRekamMedisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Peran::semua() as $peran) {
            Role::findOrCreate($peran);
        }
    }

    private function petugasRekamMedis(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Peran::RekamMedis->value);

        return $user;
    }

    public function test_petugas_bisa_menelusuri_rekam_medis_berdasarkan_nomor_rm(): void
    {
        $pasien = Pasien::factory()->create(['nama' => 'Siti Aminah']);

        Livewire::actingAs($this->petugasRekamMedis())
            ->test(PenelusuranRekamMedis::class)
            ->set('kata', $pasien->no_rm)
            ->assertSee('Siti Aminah');
    }

    public function test_koreksi_data_pasien_wajib_menyertakan_alasan(): void
    {
        $pasien = Pasien::factory()->create();

        Livewire::actingAs($this->petugasRekamMedis())
            ->test(KoreksiPasien::class, ['pasien' => $pasien])
            ->set('nama', 'Nama Terkoreksi')
            ->set('alasan', '')
            ->call('simpan')
            ->assertHasErrors('alasan');
    }

    public function test_koreksi_data_pasien_tercatat_beserta_alasannya(): void
    {
        $pasien = Pasien::factory()->create(['nama' => 'Nama Salah Ketik']);

        Livewire::actingAs($this->petugasRekamMedis())
            ->test(KoreksiPasien::class, ['pasien' => $pasien])
            ->set('nama', 'Nama Benar')
            ->set('alasan', 'Salah ketik saat pendaftaran')
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertSame('Nama Benar', $pasien->refresh()->nama);
        $this->assertSame(
            'Salah ketik saat pendaftaran',
            AuditLog::where('aksi', 'update')->latest('id')->first()->alasan
        );
    }

    public function test_rekap_kunjungan_harian_hanya_menghitung_hari_yang_dipilih(): void
    {
        Kunjungan::factory()->create(['tanggal' => '2026-08-18']);
        Kunjungan::factory()->create(['tanggal' => '2026-08-18']);
        Kunjungan::factory()->create(['tanggal' => '2026-08-17']);

        Livewire::actingAs($this->petugasRekamMedis())
            ->test(RekapKunjunganHarian::class)
            ->set('tanggal', '2026-08-18')
            ->assertSet('jumlahKunjungan', 2);
    }

    public function test_kasir_tidak_bisa_membuka_penelusuran_rekam_medis(): void
    {
        $kasir = User::factory()->create();
        $kasir->assignRole(Peran::Kasir->value);

        $this->actingAs($kasir)
            ->get(route('rekam-medis.telusur'))
            ->assertForbidden();
    }
}
```

- [ ] **Step 6: Tulis komponen rekam medis**

`app/Livewire/RekamMedis/PenelusuranRekamMedis.php` memakai `Pasien::cari()` seperti `CariPasien` (Task 15), tetapi barisnya menautkan ke riwayat kunjungan lengkap pasien beserta diagnosanya, bukan ke pembuatan kunjungan baru.

`app/Livewire/RekamMedis/KoreksiPasien.php`:

```php
<?php

namespace App\Livewire\RekamMedis;

use App\Models\Pasien;
use App\Services\PendaftaranPasien;
use App\Support\KonteksAudit;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class KoreksiPasien extends Component
{
    public Pasien $pasien;

    public string $nik = '';
    public string $nama = '';
    public string $tanggal_lahir = '';
    public string $jenis_kelamin = '';
    public string $alamat = '';
    public string $no_hp = '';
    public string $alasan = '';

    public function mount(Pasien $pasien): void
    {
        $this->pasien = $pasien;
        $this->fill($pasien->only(['nik', 'nama', 'jenis_kelamin', 'alamat', 'no_hp']));
        $this->tanggal_lahir = $pasien->tanggal_lahir->toDateString();
    }

    public function simpan(PendaftaranPasien $layanan): void
    {
        if (trim($this->alasan) === '') {
            $this->addError('alasan', 'Alasan koreksi wajib diisi.');

            return;
        }

        $data = $this->only(['nik', 'nama', 'tanggal_lahir', 'jenis_kelamin', 'alamat', 'no_hp']);

        try {
            KonteksAudit::dengan(trim($this->alasan), fn () => $layanan->perbarui($this->pasien, $data));
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kolom => $pesan) {
                $this->addError($kolom, $pesan[0]);
            }

            return;
        }

        $this->pasien->refresh();
        $this->reset('alasan');
        session()->flash('sukses', 'Koreksi data pasien tersimpan dan tercatat di audit log.');
    }

    public function render()
    {
        return view('livewire.rekam-medis.koreksi-pasien');
    }
}
```

`app/Livewire/RekamMedis/RekapKunjunganHarian.php`:

```php
<?php

namespace App\Livewire\RekamMedis;

use App\Models\Kunjungan;
use Livewire\Component;

class RekapKunjunganHarian extends Component
{
    public string $tanggal = '';

    public int $jumlahKunjungan = 0;

    public function mount(): void
    {
        $this->tanggal = now()->toDateString();
        $this->hitung();
    }

    public function updatedTanggal(): void
    {
        $this->hitung();
    }

    private function hitung(): void
    {
        $this->jumlahKunjungan = Kunjungan::whereDate('tanggal', $this->tanggal)->count();
    }

    public function render()
    {
        return view('livewire.rekam-medis.rekap-kunjungan-harian', [
            'perPoli' => Kunjungan::with('poli')
                ->whereDate('tanggal', $this->tanggal)
                ->get()
                ->groupBy(fn (Kunjungan $k) => $k->poli->nama)
                ->map->count(),
        ]);
    }
}
```

- [ ] **Step 7: Daftarkan rute**

```php
Route::middleware('role:rekam_medis')->group(function () {
    Route::get('/rekam-medis/telusur', PenelusuranRekamMedis::class)->name('rekam-medis.telusur');
    Route::get('/rekam-medis/koreksi/{pasien}', KoreksiPasien::class)->name('rekam-medis.koreksi');
    Route::get('/rekam-medis/rekap', RekapKunjunganHarian::class)->name('rekam-medis.rekap');
});

Route::middleware('role:admin')->group(function () {
    Route::get('/master/poli', DaftarPoli::class)->name('master.poli');
    Route::get('/master/dokter', DaftarDokter::class)->name('master.dokter');
    Route::get('/master/tindakan', DaftarTindakan::class)->name('master.tindakan');
    Route::get('/master/tarif', DaftarTarif::class)->name('master.tarif');
    Route::get('/admin/user', KelolaUser::class)->name('admin.user');
    Route::get('/admin/audit', PenampilAuditLog::class)->name('admin.audit');
});
```

- [ ] **Step 8: Jalankan kedua test sampai lulus**

Run: `php artisan test --filter="LayarAdminTest|LayarRekamMedisTest"`
Diharapkan: PASS, 10 test.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: tambah layar master data, kelola pengguna, audit log, dan layar rekam medis"
```

---

### Task 19: Display antrian untuk ruang tunggu

**Files:**
- Create: `routes/api.php`, `app/Http/Controllers/Api/AntrianController.php`, `resources/views/display/antrian.blade.php`
- Modify: `bootstrap/app.php` (daftarkan rute api), `routes/web.php`
- Test: `tests/Feature/DisplayAntrianTest.php`

**Interfaces:**
- Consumes: `Antrian` (Task 8)
- Produces: `GET /display/antrian` (halaman publik) dan `GET /api/antrian` (JSON). Keluaran JSON hanya memuat `nomor`, `kode`, `poli`, `dokter`, dan `status` — tanpa satu pun data pasien.

Memenuhi aturan 20.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/DisplayAntrianTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Antrian;
use App\Models\Kunjungan;
use App\Models\Pasien;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisplayAntrianTest extends TestCase
{
    use RefreshDatabase;

    private function antrianHariIni(string $namaPasien = 'Siti Aminah'): Antrian
    {
        $pasien = Pasien::factory()->create(['nama' => $namaPasien, 'nik' => '3202011203900001']);
        $kunjungan = Kunjungan::factory()->create(['pasien_id' => $pasien->id, 'tanggal' => today()]);

        return Antrian::factory()->create([
            'kunjungan_id' => $kunjungan->id,
            'poli_id' => $kunjungan->poli_id,
            'tanggal' => today(),
            'nomor' => 1,
        ]);
    }

    public function test_display_antrian_bisa_diakses_tanpa_login(): void
    {
        $this->get('/display/antrian')->assertSuccessful();
    }

    public function test_display_antrian_tidak_menampilkan_nama_pasien(): void
    {
        $this->antrianHariIni('Siti Aminah');

        $this->get('/display/antrian')->assertDontSee('Siti Aminah');
    }

    public function test_endpoint_api_mengembalikan_nomor_poli_dan_dokter(): void
    {
        $antrian = $this->antrianHariIni();

        $this->getJson('/api/antrian')
            ->assertSuccessful()
            ->assertJsonPath('data.0.nomor', 1)
            ->assertJsonPath('data.0.kode', $antrian->kode())
            ->assertJsonStructure(['data' => [['nomor', 'kode', 'poli', 'dokter', 'status']]]);
    }

    public function test_endpoint_api_tidak_memuat_data_pasien(): void
    {
        $this->antrianHariIni('Siti Aminah');

        $respons = $this->getJson('/api/antrian')->getContent();

        $this->assertStringNotContainsString('Siti Aminah', $respons);
        $this->assertStringNotContainsString('3202011203900001', $respons);
    }

    public function test_hanya_antrian_hari_ini_yang_ditampilkan(): void
    {
        $kemarin = $this->antrianHariIni();
        $kemarin->update(['tanggal' => today()->subDay()]);

        $this->getJson('/api/antrian')->assertJsonCount(0, 'data');
    }
}
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=DisplayAntrianTest`
Diharapkan: FAIL dengan 404 pada `/display/antrian`.

- [ ] **Step 3: Daftarkan berkas rute api**

Laravel 13 tidak menyertakan `routes/api.php` secara bawaan, dan `php artisan install:api` akan ikut memasang Sanctum yang tidak dibutuhkan di sini. Jadi buat berkasnya sendiri, lalu daftarkan di `bootstrap/app.php`:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    apiPrefix: 'api',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

- [ ] **Step 4: Tulis controller**

`app/Http/Controllers/Api/AntrianController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Antrian;
use Illuminate\Http\JsonResponse;

class AntrianController extends Controller
{
    public function index(): JsonResponse
    {
        $antrian = Antrian::with('poli', 'kunjungan.dokter')
            ->whereDate('tanggal', today())
            ->orderBy('poli_id')
            ->orderBy('nomor')
            ->get()
            ->map(fn (Antrian $baris) => [
                'nomor' => (int) $baris->nomor,
                'kode' => $baris->kode(),
                'poli' => $baris->poli->nama,
                'dokter' => $baris->kunjungan->dokter->nama,
                'status' => $baris->status->value,
            ]);

        return response()->json(['data' => $antrian]);
    }
}
```

Pemetaan eksplisit di atas adalah yang menjaga aturan 20: model tidak pernah dikirim utuh, sehingga kolom pasien tidak bisa bocor tanpa sengaja saat tabel bertambah kolom.

`routes/api.php`:

```php
<?php

use App\Http\Controllers\Api\AntrianController;
use Illuminate\Support\Facades\Route;

Route::get('/antrian', [AntrianController::class, 'index']);
```

- [ ] **Step 5: Tulis halaman display**

`routes/web.php` (di luar grup `auth`):

```php
Route::view('/display/antrian', 'display.antrian')->name('display.antrian');
```

`resources/views/display/antrian.blade.php` memuat halaman gelap berhuruf besar yang menarik `/api/antrian` setiap 5 detik:

```blade
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Antrian — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-slate-900 text-white p-8">
    <h1 class="text-3xl font-bold mb-6">Antrian Hari Ini</h1>
    <div id="daftar" class="grid grid-cols-2 gap-4"></div>

    <script>
        async function muat() {
            const respons = await fetch('/api/antrian');
            const { data } = await respons.json();

            document.getElementById('daftar').innerHTML = data.map(baris => `
                <div class="bg-slate-800 rounded p-6">
                    <div class="text-6xl font-bold">${baris.kode}</div>
                    <div class="text-xl mt-2">${baris.poli}</div>
                    <div class="text-sm text-slate-400">${baris.dokter} — ${baris.status}</div>
                </div>
            `).join('');
        }

        muat();
        setInterval(muat, 5000);
    </script>
</body>
</html>
```

- [ ] **Step 6: Jalankan test sampai lulus**

Run: `php artisan test --filter=DisplayAntrianTest`
Diharapkan: PASS, 5 test.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: tambah display antrian ruang tunggu tanpa membocorkan data pasien"
```

---

### Task 20: Seeder data dummy dan verifikasi kriteria selesai

**Files:**
- Create: `database/seeders/MasterSeeder.php`, `database/seeders/PenggunaSeeder.php`, `database/seeders/Icd10Seeder.php`, `database/seeders/PasienDummySeeder.php`, `database/seeders/KunjunganDummySeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`, `README.md`
- Test: `tests/Feature/AlurRawatJalanTest.php`

**Interfaces:**
- Consumes: seluruh service dan model dari Task 1–19
- Produces: `php artisan migrate:fresh --seed` menghasilkan sistem siap demo, dan satu test alur menyeluruh yang membuktikan kriteria selesai nomor 2.

- [ ] **Step 1: Tulis test alur menyeluruh yang gagal**

Buat `tests/Feature/AlurRawatJalanTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\JenisDiagnosa;
use App\Enums\MetodePembayaran;
use App\Enums\Peran;
use App\Enums\StatusKunjungan;
use App\Enums\StatusTagihan;
use App\Models\Dokter;
use App\Models\Icd10;
use App\Models\Obat;
use App\Models\Pasien;
use App\Models\Penjamin;
use App\Models\Poli;
use App\Models\TarifTindakan;
use App\Models\Tindakan;
use App\Models\User;
use App\Services\PemeriksaanKlinis;
use App\Services\PendaftaranKunjungan;
use App\Services\PendaftaranPasien;
use App\Services\PenulisanResep;
use App\Services\ProsesPembayaran;
use App\Services\TindakanPelayanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AlurRawatJalanTest extends TestCase
{
    use RefreshDatabase;

    private Poli $poli;
    private Dokter $dokter;
    private Tindakan $konsultasi;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Peran::semua() as $peran) {
            Role::findOrCreate($peran);
        }

        $this->poli = Poli::factory()->create(['kode' => 'UMU', 'nama' => 'Poli Umum']);
        $this->dokter = Dokter::factory()->create(['poli_id' => $this->poli->id]);
        $this->konsultasi = Tindakan::factory()->create(['nama' => 'Konsultasi Dokter Umum']);
    }

    private function penjaminDenganTarif(string $kode, string $jenis, int $tarif): Penjamin
    {
        $penjamin = Penjamin::factory()->create(['kode' => $kode, 'jenis' => $jenis]);

        TarifTindakan::factory()->create([
            'tindakan_id' => $this->konsultasi->id,
            'penjamin_id' => $penjamin->id,
            'tarif' => $tarif,
            'berlaku_mulai' => '2026-01-01',
        ]);

        return $penjamin;
    }

    private function jalankanAlur(Penjamin $penjamin, string $nik, ?string $noKartu): \App\Models\Kunjungan
    {
        $pasien = app(PendaftaranPasien::class)->daftarkan([
            'nik' => $nik,
            'nama' => 'Pasien Alur '.$penjamin->kode,
            'tanggal_lahir' => '1990-03-12',
            'jenis_kelamin' => 'P',
            'alamat' => 'Jl. Melati No. 12',
        ]);

        $admisi = User::factory()->create();
        $admisi->assignRole(Peran::Admisi->value);

        $kunjungan = app(PendaftaranKunjungan::class)->daftarkan([
            'pasien_id' => $pasien->id,
            'poli_id' => $this->poli->id,
            'dokter_id' => $this->dokter->id,
            'penjamin_id' => $penjamin->id,
            'no_kartu_penjamin' => $noKartu,
            'tanggal' => now()->toDateString(),
        ], $admisi);

        $perawat = User::factory()->create();
        $perawat->assignRole(Peran::Perawat->value);

        $klinis = app(PemeriksaanKlinis::class);
        $klinis->catatVital($kunjungan, [
            'sistolik' => 120, 'diastolik' => 80, 'nadi' => 78, 'suhu' => 36.7,
            'respirasi' => 18, 'berat_badan' => 62.5, 'tinggi_badan' => 165,
            'keluhan_awal' => 'Batuk tiga hari',
        ], $perawat);

        $dokterUser = User::factory()->create(['dokter_id' => $this->dokter->id]);
        $dokterUser->assignRole(Peran::Dokter->value);

        $klinis->catatSoap($kunjungan, [
            'subjective' => 'Batuk berdahak tiga hari',
            'objective' => 'Faring hiperemis',
            'assessment' => 'ISPA',
            'plan' => 'Antibiotik dan obat batuk',
        ], $dokterUser);

        $klinis->tambahDiagnosa($kunjungan, Icd10::factory()->create()->id, JenisDiagnosa::Primer);

        app(TindakanPelayanan::class)->tambah($kunjungan, $this->konsultasi->id, 1, $dokterUser);

        app(PenulisanResep::class)->tulis($kunjungan, [[
            'obat_id' => Obat::factory()->create()->id,
            'jumlah' => 10,
            'aturan_pakai' => '3x1 sesudah makan',
        ]], $dokterUser);

        $klinis->selesaikan($kunjungan, $dokterUser);

        return $kunjungan->refresh();
    }

    public function test_alur_lengkap_pasien_umum_sampai_kuitansi(): void
    {
        $umum = $this->penjaminDenganTarif('UMUM', 'tunai', 50000);
        $kunjungan = $this->jalankanAlur($umum, '3202011203900001', null);

        $kasir = User::factory()->create();
        $kasir->assignRole(Peran::Kasir->value);

        $pembayaran = app(ProsesPembayaran::class)
            ->bayar($kunjungan->tagihan, MetodePembayaran::Tunai, 100000, $kasir);

        $this->assertSame(StatusKunjungan::Selesai, $kunjungan->status);
        $this->assertSame(50000, (int) $kunjungan->tagihan->total);
        $this->assertSame(50000, (int) $pembayaran->kembalian);
        $this->assertSame(StatusTagihan::Lunas, $kunjungan->tagihan->refresh()->status);
        $this->assertNotNull($kunjungan->resep);
        $this->assertSame(1, $kunjungan->diagnosa()->count());
    }

    public function test_alur_lengkap_pasien_bpjs_tidak_ditagihkan_ke_pasien(): void
    {
        $this->penjaminDenganTarif('UMUM', 'tunai', 50000);
        $bpjs = $this->penjaminDenganTarif('BPJS', 'penjamin', 35000);

        $kunjungan = $this->jalankanAlur($bpjs, '3202011203900002', '0001234567890');

        $this->assertSame(35000, (int) $kunjungan->tagihan->total);
        $this->assertSame(35000, (int) $kunjungan->tagihan->ditanggung_penjamin);
        $this->assertSame(0, (int) $kunjungan->tagihan->ditagihkan_ke_pasien);
        $this->assertSame(StatusTagihan::DitanggungPenjamin, $kunjungan->tagihan->status);
    }

    public function test_seluruh_perubahan_klinis_pada_alur_terekam_di_audit_log(): void
    {
        $umum = $this->penjaminDenganTarif('UMUM', 'tunai', 50000);
        $kunjungan = $this->jalankanAlur($umum, '3202011203900003', null);

        $this->assertDatabaseHas('audit_logs', ['model_tipe' => \App\Models\Pasien::class, 'aksi' => 'create']);
        $this->assertDatabaseHas('audit_logs', ['model_tipe' => \App\Models\Kunjungan::class, 'model_id' => $kunjungan->id]);
        $this->assertDatabaseHas('audit_logs', ['model_tipe' => \App\Models\Pemeriksaan::class]);
        $this->assertDatabaseHas('audit_logs', ['model_tipe' => \App\Models\Tagihan::class]);
    }
}
```

- [ ] **Step 2: Jalankan test dan perbaiki sampai lulus**

Run: `php artisan test --filter=AlurRawatJalanTest`
Diharapkan: PASS, 3 test. Test ini merangkai seluruh service yang sudah ada, jadi kegagalan di sini menandakan cacat integrasi antar tugas — bukan fitur yang belum ditulis. Telusuri pesan kegagalannya, perbaiki penyebabnya, jangan melonggarkan assertion-nya.

- [ ] **Step 3: Tulis seeder master**

`database/seeders/MasterSeeder.php` mengisi:

- 5 poli: `UMU` Poli Umum, `GIG` Poli Gigi, `ANK` Poli Anak, `KDG` Poli Kandungan, `PDL` Poli Penyakit Dalam
- 10 dokter tersebar di kelima poli, masing-masing dengan jadwal Senin–Jumat 08:00–12:00 kuota 30
- 2 penjamin: `UMUM` (jenis `tunai`) dan `BPJS` (jenis `penjamin`)
- 30 tindakan lintas kategori (`administrasi`: pendaftaran, kartu berobat; `konsultasi`: konsultasi tiap poli; `tindakan_medis`: injeksi, nebulisasi, jahit luka, cabut gigi, tambal gigi, EKG, dan seterusnya)
- Tarif untuk **setiap** tindakan pada **kedua** penjamin dengan `berlaku_mulai` `'2026-01-01'`; tarif BPJS dibuat sekitar 70% tarif umum
- 50 obat dengan satuan dan bentuk sediaan yang wajar

- [ ] **Step 4: Tulis seeder pengguna dan ICD-10**

`database/seeders/PenggunaSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Enums\Peran;
use App\Models\Dokter;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class PenggunaSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException(
                'PenggunaSeeder memakai kata sandi seragam dan hanya boleh dijalankan di lingkungan lokal.'
            );
        }

        $daftar = [
            [Peran::Admisi, 'Petugas Admisi', 'admisi@rs.test'],
            [Peran::Perawat, 'Perawat Poli', 'perawat@rs.test'],
            [Peran::RekamMedis, 'Petugas Rekam Medis', 'rekammedis@rs.test'],
            [Peran::Kasir, 'Kasir Rawat Jalan', 'kasir@rs.test'],
            [Peran::Admin, 'Administrator', 'admin@rs.test'],
        ];

        foreach ($daftar as [$peran, $nama, $email]) {
            User::updateOrCreate(
                ['email' => $email],
                ['name' => $nama, 'password' => Hash::make('rahasia123'), 'aktif' => true]
            )->syncRoles([$peran->value]);
        }

        $dokter = Dokter::first();

        User::updateOrCreate(
            ['email' => 'dokter@rs.test'],
            [
                'name' => $dokter->nama,
                'password' => Hash::make('rahasia123'),
                'dokter_id' => $dokter->id,
                'aktif' => true,
            ]
        )->syncRoles([Peran::Dokter->value]);
    }
}
```

`database/seeders/Icd10Seeder.php` memuat sekitar 200 kode ICD-10 yang paling sering dipakai di rawat jalan (J06.9 ISPA, A09 Diare dan gastroenteritis, I10 Hipertensi esensial, E11.9 Diabetes melitus tipe 2, K29.7 Gastritis, dan seterusnya) sebagai array konstanta di dalam seeder.

- [ ] **Step 5: Tulis seeder pasien dan kunjungan dummy**

`database/seeders/PasienDummySeeder.php` membuat 100 pasien lewat `Pasien::factory()->count(100)->create()`.

`database/seeders/KunjunganDummySeeder.php` merangkai alur nyata untuk sebagian pasien memakai service, bukan `insert` langsung, supaya data dummy tetap patuh pada seluruh aturan bisnis:

- 30 kunjungan hari ini berstatus `terdaftar` (masih mengantre)
- 20 kunjungan hari ini yang sudah `selesai` beserta tagihannya, separuh BPJS separuh umum
- 10 di antaranya sudah dibayar di kasir

`DatabaseSeeder::run()` memanggil berurutan: `PeranSeeder`, `MasterSeeder`, `Icd10Seeder`, `PenggunaSeeder`, `PasienDummySeeder`, `KunjunganDummySeeder`.

- [ ] **Step 6: Jalankan seluruh alur dari nol**

```bash
php artisan migrate:fresh --seed
```

Diharapkan: selesai tanpa galat. Periksa hasilnya:

```bash
mysql -u irvan -p1 simrs -e "SELECT (SELECT COUNT(*) FROM pasien) AS pasien, (SELECT COUNT(*) FROM kunjungan) AS kunjungan, (SELECT COUNT(*) FROM tagihan) AS tagihan, (SELECT COUNT(*) FROM audit_logs) AS audit;"
```

Diharapkan: pasien ≥ 100, kunjungan ≥ 50, tagihan ≥ 20, audit > 0.

- [ ] **Step 7: Jalankan seluruh test**

Run: `php artisan test`
Diharapkan: seluruh berkas test PASS, tanpa satu pun yang di-skip.

- [ ] **Step 8: Periksa kriteria selesai secara manual**

```bash
php artisan serve
```

Telusuri satu per satu dan centang bila benar:

1. Masuk sebagai `admisi@rs.test` (sandi `rahasia123`), daftarkan pasien baru, buat kunjungan, cetak karcis.
2. Masuk sebagai `perawat@rs.test`, isi tanda vital pasien tersebut.
3. Masuk sebagai `dokter@rs.test`, isi SOAP, diagnosa, tindakan, resep, lalu selesaikan kunjungan.
4. Masuk sebagai `kasir@rs.test`, proses pembayaran, cetak kuitansi.
5. Ulangi langkah 1–4 untuk pasien BPJS; pastikan kasir melihat tagihan bertanda ditanggung penjamin dengan nominal pasien 0.
6. Masuk sebagai `admin@rs.test`, buka audit log, pastikan seluruh perubahan tadi berjejak beserta nama pelakunya.
7. Buka `/display/antrian` di jendela penyamaran (tanpa login), pastikan nomor antrian tampil dan tidak ada satu pun nama pasien.

- [ ] **Step 9: Tulis README**

Perbarui `README.md` dengan: ringkasan Fase 1, tautan ke spec dan rencana ini, cara menyiapkan (buat database, `composer install`, `.env`, `php artisan migrate:fresh --seed`, `npm run build`, `php artisan serve`), daftar akun demo beserta perannya, dan peringatan bahwa kata sandi seragam hanya untuk lingkungan lokal.

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "feat: tambah seeder data dummy lengkap dan test alur rawat jalan menyeluruh"
```

---

## Ringkasan Cakupan

| Aturan bisnis (spec bagian 8) | Tugas |
|---|---|
| 1, 2 NIK & tanggal lahir | Task 6 |
| 3 Nomor RM tidak dipakai ulang | Task 5, 6 |
| 4, 5 Penomoran antrian | Task 5, 8 |
| 6 Kunjungan aktif ganda | Task 8 |
| 7 Nomor kartu penjamin | Task 8 |
| 8 Dokter sesuai poli | Task 10 |
| 9 Diagnosa primer tunggal | Task 10 |
| 10 SOAP lengkap sebelum selesai | Task 10 |
| 11 Data klinis terkunci & koreksi berjejak | Task 10 |
| 12 Tagihan disusun sekali, tarif disalin | Task 11, 13 |
| 13 Jatuh tempo tarif UMUM | Task 11 |
| 14 Tagihan BPJS | Task 13 |
| 15, 16 Pembayaran | Task 14 |
| 17 Pembatalan kunjungan | Task 8 |
| 18 Admin tidak mengubah rekam medis | Task 10 |
| 19 Audit trail | Task 7 (diperluas di Task 8, 9, 10, 13) |
| 20 Display antrian tanpa data pasien | Task 19 |
