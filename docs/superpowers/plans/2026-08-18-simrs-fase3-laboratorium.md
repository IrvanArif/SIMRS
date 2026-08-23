# SIMRS Fase 3 (Laboratorium) — Rencana Implementasi

> **Untuk pekerja agentik:** SUB-SKILL WAJIB: gunakan superpowers:subagent-driven-development (disarankan) atau superpowers:executing-plans untuk mengerjakan rencana ini tugas demi tugas. Setiap langkah memakai checkbox (`- [ ]`).

**Goal:** Menambahkan laboratorium ke alur rawat jalan — dokter memesan, analis mengambil sampel dan mengentri hasil, hasil divalidasi sebelum terbaca dokter, dan kunjungan baru bisa ditutup setelah hasilnya keluar.

**Architecture:** Dua tugas pertama merapikan fondasi sebelum menambah apa pun: satu tabel tarif polimorfik menggantikan dua tabel harga yang nyaris kembar, dan `tagihan_detail` menyimpan sumbernya secara polimorfik. Selebihnya mengikuti pola Fase 1 dan 2 tanpa kecuali — aturan bisnis di kelas Service, komponen Livewire hanya memindahkan pesan ke layar, dan setiap perubahan data klinis berjejak di audit.

**Tech Stack:** PHP 8.5, Laravel 13, Livewire 4, Blade, Tailwind (Vite), MySQL, spatie/laravel-permission, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-08-18-simrs-fase3-laboratorium-design.md`

## Global Constraints

- **Bahasa Indonesia** untuk nama tabel, kolom, rute, label UI, pesan validasi, dan nama test (`test_...`).
- **TDD tanpa pengecualian:** test ditulis lebih dulu, dijalankan sampai terbukti GAGAL, baru implementasinya.
- **Nominal uang** berupa bilangan bulat rupiah (`unsignedBigInteger`).
- **Penomoran** lewat `App\Services\PencatatNomor`; `max() + 1` dilarang.
- **Enum PHP** untuk semua kolom berstatus.
- **Model klinis baru wajib didaftarkan** di `AppServiceProvider::modelTerauditkan()`.
- **Aturan bisnis 1–34 tidak boleh berubah perilakunya.** Task 1 dan 2 memindahkan tempat data disimpan, bukan mengubah aturannya.
- **224 test yang ada wajib tetap hijau** di akhir setiap tugas. Test yang merah karena perilaku berubah berarti pekerjaannya salah — test tidak boleh dilonggarkan untuk menutupinya.
- **Tidak boleh ada nama rumah sakit nyata** di berkas mana pun; data contoh memakai "RS Sampel".
- **Commit setiap selesai satu tugas**, pesan berbahasa Indonesia.

## Pola yang Sudah Ada — Baca Sebelum Menulis

| Kebutuhan | Acuan |
|---|---|
| Service dengan validasi + transaksi | `app/Services/PendaftaranKunjungan.php` |
| Pencarian tarif dengan jatuh tempo + log | `app/Services/PencariTarif.php` |
| Alur berstatus dengan jejak pelaku tiap tahap | `app/Services/PenyiapanResep.php` |
| Pembebanan biaya ke tagihan | `app/Services/PenyusunTagihan.php` (`tambahObat`, `hitungUlang`) |
| Koreksi beralasan yang berjejak | `app/Services/PenyesuaianStok.php` |
| Komponen Livewire dengan aksi majemuk | `app/Livewire/Apotek/LayarPenyiapan.php` |
| Test service | `tests/Feature/PenyiapanResepTest.php` |
| Test layar + hak akses | `tests/Feature/LayarApotekTest.php` |

## Struktur Berkas

**Fondasi (Task 1–2)**

| Berkas | Tanggung jawab |
|---|---|
| `app/Enums/JenisLayanan.php` | `tindakan`, `obat`, `lab` |
| `app/Models/Tarif.php` | Satu tabel harga untuk ketiga jenis layanan |
| `app/Services/PencariTarif.php` | Ditulis ulang menerima jenis layanan |

**Laboratorium (Task 3–10)**

| Berkas | Tanggung jawab |
|---|---|
| `app/Enums/StatusOrderLab.php`, `app/Enums/PenandaHasil.php` | Status order dan penanda nilai |
| `app/Models/{PemeriksaanLab,ParameterLab,RujukanLab,OrderLab,OrderLabDetail,HasilLab}.php` | Entitas laboratorium |
| `app/Services/PenandaNilai.php` | Menentukan rendah/normal/tinggi dari rujukan |
| `app/Services/PemesananLab.php` | Order, salin tarif, bebankan ke tagihan |
| `app/Services/PemeriksaanLaboratorium.php` | Ambil sampel, entri hasil, validasi, koreksi |
| `app/Policies/OrderLabPolicy.php` | Kewenangan analis |
| `app/Livewire/Lab/*.php` | Layar analis dan pembacaan hasil |

---

### Task 1: Satukan tabel harga menjadi satu tabel tarif

**Files:**
- Create: `app/Enums/JenisLayanan.php`, `app/Models/Tarif.php`, `database/factories/TarifFactory.php`, migration `create_tarif_table`
- Modify: `app/Services/PencariTarif.php`, `app/Services/TindakanPelayanan.php`, `app/Services/PenyiapanResep.php`, `app/Models/Tindakan.php`, `app/Models/Obat.php`, `app/Livewire/Master/DaftarTarif.php`, `database/seeders/MasterSeeder.php`, `database/seeders/FarmasiSeeder.php`, `routes/web.php`
- Delete: `app/Models/TarifTindakan.php`, `app/Models/HargaObat.php`, `app/Services/PencariHargaObat.php`, `database/factories/TarifTindakanFactory.php`, `database/factories/HargaObatFactory.php`, `app/Livewire/Master/DaftarHargaObat.php`, `resources/views/livewire/master/daftar-harga-obat.blade.php`, `tests/Feature/HargaObatTest.php`
- Test: `tests/Feature/TarifTest.php` (ditulis ulang)

**Interfaces:**
- Consumes: `Penjamin` (Fase 1)
- Produces:
  - `JenisLayanan` enum: `Tindakan = 'tindakan'`, `Obat = 'obat'`, `Lab = 'lab'`.
  - Model `Tarif` (tabel `tarif`) berkolom `jenis_layanan`, `layanan_id`, `penjamin_id`, `harga`, `berlaku_mulai`.
  - `PencariTarif::untuk(JenisLayanan $jenis, int $layananId, int $penjaminId, ?CarbonInterface $tanggal = null): int` — jatuh tempo ke penjamin `UMUM` sambil mencatat peringatan; melempar `RuntimeException` bila tarif UMUM pun tidak ada.

Tugas ini tidak menambah fitur apa pun. Ia hanya memindahkan tempat harga disimpan. Karena itu ukuran keberhasilannya bukan test baru yang lulus, melainkan **seluruh test lama tetap hijau**.

- [ ] **Step 1: Tulis ulang test tarif**

Ganti seluruh isi `tests/Feature/TarifTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\JenisLayanan;
use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\Penjamin;
use App\Models\Tarif;
use App\Models\Tindakan;
use App\Models\User;
use App\Services\PencariTarif;
use App\Services\TindakanPelayanan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class TarifTest extends TestCase
{
    use RefreshDatabase;

    private Tindakan $tindakan;
    private Obat $obat;
    private Penjamin $umum;
    private Penjamin $bpjs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tindakan = Tindakan::factory()->create(['nama' => 'Konsultasi Dokter Umum']);
        $this->obat = Obat::factory()->create(['nama' => 'Paracetamol 500 mg']);
        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $this->bpjs = Penjamin::factory()->create(['kode' => 'BPJS', 'jenis' => 'penjamin']);
    }

    private function tarif(
        JenisLayanan $jenis,
        int $layananId,
        Penjamin $penjamin,
        int $harga,
        string $berlakuMulai = '2026-01-01'
    ): void {
        Tarif::factory()->create([
            'jenis_layanan' => $jenis,
            'layanan_id' => $layananId,
            'penjamin_id' => $penjamin->id,
            'harga' => $harga,
            'berlaku_mulai' => $berlakuMulai,
        ]);
    }

    public function test_tarif_tindakan_diambil_sesuai_penjamin(): void
    {
        $this->tarif(JenisLayanan::Tindakan, $this->tindakan->id, $this->umum, 50000);
        $this->tarif(JenisLayanan::Tindakan, $this->tindakan->id, $this->bpjs, 35000);

        $this->assertSame(
            35000,
            app(PencariTarif::class)->untuk(JenisLayanan::Tindakan, $this->tindakan->id, $this->bpjs->id)
        );
    }

    public function test_harga_obat_diambil_sesuai_penjamin(): void
    {
        $this->tarif(JenisLayanan::Obat, $this->obat->id, $this->umum, 1500);
        $this->tarif(JenisLayanan::Obat, $this->obat->id, $this->bpjs, 1000);

        $this->assertSame(
            1000,
            app(PencariTarif::class)->untuk(JenisLayanan::Obat, $this->obat->id, $this->bpjs->id)
        );
    }

    public function test_layanan_berbeda_dengan_id_sama_tidak_tertukar(): void
    {
        // Tindakan #1 dan obat #1 adalah dua hal berbeda meski id-nya sama.
        $this->tarif(JenisLayanan::Tindakan, 1, $this->umum, 50000);
        $this->tarif(JenisLayanan::Obat, 1, $this->umum, 1500);

        $pencari = app(PencariTarif::class);

        $this->assertSame(50000, $pencari->untuk(JenisLayanan::Tindakan, 1, $this->umum->id));
        $this->assertSame(1500, $pencari->untuk(JenisLayanan::Obat, 1, $this->umum->id));
    }

    public function test_tarif_jatuh_tempo_ke_umum_bila_penjamin_belum_punya_tarif(): void
    {
        $this->tarif(JenisLayanan::Tindakan, $this->tindakan->id, $this->umum, 50000);

        $this->assertSame(
            50000,
            app(PencariTarif::class)->untuk(JenisLayanan::Tindakan, $this->tindakan->id, $this->bpjs->id)
        );
    }

    public function test_ketiadaan_tarif_penjamin_dicatat_sebagai_peringatan(): void
    {
        $this->tarif(JenisLayanan::Tindakan, $this->tindakan->id, $this->umum, 50000);
        Log::spy();

        app(PencariTarif::class)->untuk(JenisLayanan::Tindakan, $this->tindakan->id, $this->bpjs->id);

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_tarif_terbaru_yang_sudah_berlaku_yang_dipakai(): void
    {
        $this->tarif(JenisLayanan::Tindakan, $this->tindakan->id, $this->umum, 50000, '2026-01-01');
        $this->tarif(JenisLayanan::Tindakan, $this->tindakan->id, $this->umum, 60000, '2026-06-01');

        $pencari = app(PencariTarif::class);

        $this->assertSame(
            50000,
            $pencari->untuk(JenisLayanan::Tindakan, $this->tindakan->id, $this->umum->id, Carbon::parse('2026-03-01'))
        );
        $this->assertSame(
            60000,
            $pencari->untuk(JenisLayanan::Tindakan, $this->tindakan->id, $this->umum->id, Carbon::parse('2026-08-18'))
        );
    }

    public function test_tanpa_tarif_sama_sekali_maka_gagal_dengan_pesan_jelas(): void
    {
        $this->expectException(RuntimeException::class);

        app(PencariTarif::class)->untuk(JenisLayanan::Tindakan, $this->tindakan->id, $this->bpjs->id);
    }

    public function test_tarif_ganda_untuk_kombinasi_yang_sama_ditolak_database(): void
    {
        $this->tarif(JenisLayanan::Tindakan, $this->tindakan->id, $this->umum, 50000);

        $this->expectException(QueryException::class);

        $this->tarif(JenisLayanan::Tindakan, $this->tindakan->id, $this->umum, 60000);
    }

    public function test_tarif_disalin_ke_tindakan_kunjungan(): void
    {
        $this->tarif(JenisLayanan::Tindakan, $this->tindakan->id, $this->umum, 50000);
        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]);

        $baris = app(TindakanPelayanan::class)
            ->tambah($kunjungan, $this->tindakan->id, 1, User::factory()->create());

        $this->assertSame(50000, (int) $baris->tarif_satuan);
    }

    public function test_perubahan_master_tarif_tidak_mengubah_tindakan_yang_sudah_dicatat(): void
    {
        $this->tarif(JenisLayanan::Tindakan, $this->tindakan->id, $this->umum, 50000);
        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]);

        $baris = app(TindakanPelayanan::class)
            ->tambah($kunjungan, $this->tindakan->id, 1, User::factory()->create());

        Tarif::query()->update(['harga' => 99000]);

        $this->assertSame(50000, (int) $baris->refresh()->tarif_satuan);
    }

    public function test_jumlah_tindakan_minimal_satu(): void
    {
        $this->tarif(JenisLayanan::Tindakan, $this->tindakan->id, $this->umum, 50000);
        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(TindakanPelayanan::class)->tambah($kunjungan, $this->tindakan->id, 0, User::factory()->create());
    }
}
```

Hapus `tests/Feature/HargaObatTest.php` — seluruh perilakunya sudah tercakup di atas.

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=TarifTest`
Diharapkan: FAIL dengan "Class App\Enums\JenisLayanan not found".

- [ ] **Step 3: Tulis enum dan model**

`app/Enums/JenisLayanan.php`:

```php
<?php

namespace App\Enums;

enum JenisLayanan: string
{
    case Tindakan = 'tindakan';
    case Obat = 'obat';
    case Lab = 'lab';

    public function label(): string
    {
        return match ($this) {
            self::Tindakan => 'Tindakan',
            self::Obat => 'Obat',
            self::Lab => 'Pemeriksaan Laboratorium',
        };
    }
}
```

`app/Models/Tarif.php`:

```php
<?php

namespace App\Models;

use App\Enums\JenisLayanan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tarif extends Model
{
    use HasFactory;

    protected $table = 'tarif';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'jenis_layanan' => JenisLayanan::class,
            'berlaku_mulai' => 'date',
        ];
    }

    public function penjamin(): BelongsTo
    {
        return $this->belongsTo(Penjamin::class);
    }

    /**
     * Layanan yang ditarifkan tidak memakai relasi polimorfik Eloquent karena
     * ketiganya berbeda tabel dan tidak pernah dimuat bersamaan — pemanggilnya
     * selalu sudah tahu jenis apa yang sedang ia tangani.
     */
    public function namaLayanan(): string
    {
        return match ($this->jenis_layanan) {
            JenisLayanan::Tindakan => Tindakan::find($this->layanan_id)?->nama ?? '—',
            JenisLayanan::Obat => Obat::find($this->layanan_id)?->nama ?? '—',
            JenisLayanan::Lab => PemeriksaanLab::find($this->layanan_id)?->nama ?? '—',
        };
    }
}
```

`PemeriksaanLab` baru dibuat di Task 4; sampai saat itu cabang `Lab` tidak pernah
dijalankan karena belum ada baris tarif berjenis `lab`.

`database/factories/TarifFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\JenisLayanan;
use App\Models\Penjamin;
use App\Models\Tarif;
use App\Models\Tindakan;
use Illuminate\Database\Eloquent\Factories\Factory;

class TarifFactory extends Factory
{
    protected $model = Tarif::class;

    public function definition(): array
    {
        return [
            'jenis_layanan' => JenisLayanan::Tindakan,
            'layanan_id' => Tindakan::factory(),
            'penjamin_id' => Penjamin::factory(),
            'harga' => $this->faker->numberBetween(1000, 500000),
            'berlaku_mulai' => '2026-01-01',
        ];
    }
}
```

- [ ] **Step 4: Tulis migration yang sekaligus memindahkan isinya**

```bash
php artisan make:migration create_tarif_table
```

```php
public function up(): void
{
    Schema::create('tarif', function (Blueprint $table) {
        $table->id();
        $table->string('jenis_layanan', 20);
        $table->unsignedBigInteger('layanan_id');
        $table->foreignId('penjamin_id')->constrained('penjamin');
        $table->unsignedBigInteger('harga');
        $table->date('berlaku_mulai');
        $table->timestamps();
        $table->unique(['jenis_layanan', 'layanan_id', 'penjamin_id', 'berlaku_mulai'], 'tarif_unik');
        $table->index(['jenis_layanan', 'layanan_id']);
    });

    // Pindahkan isi kedua tabel lama sebelum keduanya dihapus, supaya data
    // harga yang sudah ada tidak hilang saat migrasi dijalankan di basis data
    // yang sudah terisi.
    foreach ([
        ['tarif_tindakan', 'tindakan', 'tindakan_id', 'tarif'],
        ['harga_obat', 'obat', 'obat_id', 'harga'],
    ] as [$tabelLama, $jenis, $kolomLayanan, $kolomHarga]) {
        if (! Schema::hasTable($tabelLama)) {
            continue;
        }

        DB::table($tabelLama)->orderBy('id')->chunk(200, function ($baris) use ($jenis, $kolomLayanan, $kolomHarga) {
            DB::table('tarif')->insert($baris->map(fn ($b) => [
                'jenis_layanan' => $jenis,
                'layanan_id' => $b->{$kolomLayanan},
                'penjamin_id' => $b->penjamin_id,
                'harga' => $b->{$kolomHarga},
                'berlaku_mulai' => $b->berlaku_mulai,
                'created_at' => $b->created_at,
                'updated_at' => $b->updated_at,
            ])->all());
        });
    }

    Schema::dropIfExists('harga_obat');
    Schema::dropIfExists('tarif_tindakan');
}

public function down(): void
{
    Schema::dropIfExists('tarif');
}
```

Tambahkan `use Illuminate\Support\Facades\DB;` di bagian atas migration.

`down()` sengaja tidak membangun ulang kedua tabel lama: migrasi ini adalah jalan
satu arah, dan memulihkannya setengah jadi lebih berbahaya daripada memulai ulang
dari `migrate:fresh`.

- [ ] **Step 5: Tulis ulang PencariTarif**

Ganti seluruh isi `app/Services/PencariTarif.php`:

```php
<?php

namespace App\Services;

use App\Enums\JenisLayanan;
use App\Models\Penjamin;
use App\Models\Tarif;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Satu pencari untuk seluruh jenis layanan. Bila penjamin belum punya tarif,
 * dipakai tarif UMUM dan kejadiannya dicatat agar admin menindaklanjuti
 * (aturan 13 untuk tindakan, aturan 27 untuk obat, aturan 41 untuk lab).
 */
class PencariTarif
{
    public function untuk(
        JenisLayanan $jenis,
        int $layananId,
        int $penjaminId,
        ?CarbonInterface $tanggal = null
    ): int {
        $tanggal ??= Carbon::today();

        $harga = $this->cari($jenis, $layananId, $penjaminId, $tanggal);

        if ($harga !== null) {
            return $harga;
        }

        $umum = Penjamin::where('kode', 'UMUM')->first();

        Log::warning('Tarif khusus penjamin tidak ditemukan, memakai tarif UMUM.', [
            'jenis_layanan' => $jenis->value,
            'layanan_id' => $layananId,
            'penjamin_id' => $penjaminId,
            'tanggal' => $tanggal->toDateString(),
        ]);

        $tarifUmum = $umum ? $this->cari($jenis, $layananId, $umum->id, $tanggal) : null;

        if ($tarifUmum === null) {
            throw new RuntimeException(
                "Tarif {$jenis->label()} #{$layananId} belum diisi, termasuk tarif UMUM. Hubungi admin master data."
            );
        }

        return $tarifUmum;
    }

    private function cari(
        JenisLayanan $jenis,
        int $layananId,
        int $penjaminId,
        CarbonInterface $tanggal
    ): ?int {
        $baris = Tarif::where('jenis_layanan', $jenis->value)
            ->where('layanan_id', $layananId)
            ->where('penjamin_id', $penjaminId)
            ->whereDate('berlaku_mulai', '<=', $tanggal->toDateString())
            ->orderByDesc('berlaku_mulai')
            ->first();

        return $baris ? (int) $baris->harga : null;
    }
}
```

- [ ] **Step 6: Perbarui pemanggilnya**

Di `app/Services/TindakanPelayanan.php`, tambahkan `use App\Enums\JenisLayanan;` dan ubah pemanggilan tarif menjadi:

```php
                'tarif_satuan' => $this->pencariTarif->untuk(
                    JenisLayanan::Tindakan, $tindakanId, $kunjungan->penjamin_id, $kunjungan->tanggal
                ),
```

Di `app/Services/PenyiapanResep.php`, ganti ketergantungan `PencariHargaObat` menjadi `PencariTarif`:

```php
    public function __construct(
        private readonly PencariTarif $pencariTarif,
        private readonly PenyusunTagihan $penyusunTagihan,
    ) {}
```

dan pemanggilannya:

```php
                    'harga_satuan' => $this->pencariTarif->untuk(
                        JenisLayanan::Obat, $baris->obat_id, $kunjungan->penjamin_id, $tanggal
                    ),
```

Tambahkan `use App\Enums\JenisLayanan;` dan `use App\Services\PencariTarif;` bila belum ada, lalu hapus import `PencariHargaObat`.

- [ ] **Step 7: Hapus berkas yang digantikan**

```bash
cd /var/www/html/SIMRS
rm app/Models/TarifTindakan.php app/Models/HargaObat.php
rm app/Services/PencariHargaObat.php
rm database/factories/TarifTindakanFactory.php database/factories/HargaObatFactory.php
rm app/Livewire/Master/DaftarHargaObat.php resources/views/livewire/master/daftar-harga-obat.blade.php
rm tests/Feature/HargaObatTest.php
```

Hapus relasi `tarif()` pada `app/Models/Tindakan.php` dan relasi `harga()` pada
`app/Models/Obat.php` beserta importnya — keduanya menunjuk model yang sudah tidak ada.

- [ ] **Step 8: Satukan layar master tarif**

`app/Livewire/Master/DaftarTarif.php` kini mengelola ketiga jenis layanan. Isian
`tindakan_id` diganti sepasang isian `jenis_layanan` dan `layanan_id`, dengan daftar
pilihan layanan mengikuti jenis yang dipilih:

```php
    public string $jenis_layanan = 'tindakan';
    public ?int $layanan_id = null;
    public ?int $penjamin_id = null;
    public int $harga = 0;
    public string $berlaku_mulai = '';

    public function simpan(): void
    {
        $data = $this->validate([
            'jenis_layanan' => ['required', Rule::in(array_column(JenisLayanan::cases(), 'value'))],
            'layanan_id' => ['required', 'integer'],
            'penjamin_id' => ['required', 'exists:penjamin,id'],
            'harga' => ['required', 'integer', 'min:0'],
            'berlaku_mulai' => ['required', 'date'],
        ], [
            'layanan_id.required' => 'Layanan wajib dipilih.',
            'penjamin_id.required' => 'Penjamin wajib dipilih.',
            'harga.min' => 'Tarif tidak boleh negatif.',
        ]);

        $sudahAda = Tarif::where('jenis_layanan', $data['jenis_layanan'])
            ->where('layanan_id', $data['layanan_id'])
            ->where('penjamin_id', $data['penjamin_id'])
            ->whereDate('berlaku_mulai', $data['berlaku_mulai'])
            ->exists();

        if ($sudahAda) {
            $this->addError('harga', 'Tarif untuk layanan, penjamin, dan tanggal berlaku ini sudah ada.');

            return;
        }

        Tarif::create($data);

        $this->reset(['layanan_id', 'penjamin_id', 'harga']);
        session()->flash('sukses', 'Tarif tersimpan.');
    }

    public function render()
    {
        $pilihanLayanan = match (JenisLayanan::from($this->jenis_layanan)) {
            JenisLayanan::Tindakan => Tindakan::orderBy('nama')->get(['id', 'nama']),
            JenisLayanan::Obat => Obat::orderBy('nama')->get(['id', 'nama']),
            JenisLayanan::Lab => collect(),
        };

        return view('livewire.master.daftar-tarif', [
            'daftarTarif' => Tarif::with('penjamin')->orderByDesc('berlaku_mulai')->paginate(15),
            'pilihanLayanan' => $pilihanLayanan,
            'daftarPenjamin' => Penjamin::orderBy('nama')->get(),
            'pilihanJenis' => JenisLayanan::cases(),
        ]);
    }
```

Cabang `Lab` mengembalikan koleksi kosong sampai Task 4 membuat masternya.

Sesuaikan `resources/views/livewire/master/daftar-tarif.blade.php`: tambahkan `select`
untuk `jenis_layanan` dengan `wire:model.live`, ganti `select` tindakan menjadi
`layanan_id` yang diisi dari `$pilihanLayanan`, dan kolom tabel memakai
`$baris->namaLayanan()` beserta `$baris->jenis_layanan->label()`.

Di `routes/web.php`, hapus baris rute `master.harga-obat` beserta importnya.

- [ ] **Step 9: Perbarui seeder**

Di `database/seeders/MasterSeeder.php`, ganti penulisan `TarifTindakan::updateOrCreate`
menjadi:

```php
                Tarif::updateOrCreate([
                    'jenis_layanan' => JenisLayanan::Tindakan,
                    'layanan_id' => $tindakan->id,
                    'penjamin_id' => $penjamin[$kodePenjamin]->id,
                    'berlaku_mulai' => '2026-01-01',
                ], ['harga' => $tarif]);
```

Di `database/seeders/FarmasiSeeder.php`, ganti kedua penulisan `HargaObat::updateOrCreate`
menjadi `Tarif::updateOrCreate` dengan `'jenis_layanan' => JenisLayanan::Obat` dan
`'layanan_id' => $obat->id`. Sesuaikan importnya di kedua berkas.

- [ ] **Step 10: Perbarui test yang merujuk model lama**

Berkas berikut memakai `TarifTindakan::factory()` atau `HargaObat::factory()` dan harus
diarahkan ke `Tarif::factory()` dengan `jenis_layanan` serta `layanan_id` yang sesuai:

`tests/Feature/TagihanTest.php`, `tests/Feature/PenyiapanResepTest.php`,
`tests/Feature/TagihanObatTest.php`, `tests/Feature/PenyerahanObatTest.php`,
`tests/Feature/LayarPoliTest.php`, `tests/Feature/LayarApotekTest.php`,
`tests/Feature/AlurRawatJalanTest.php`, `tests/Feature/AlurFarmasiTest.php`.

Polanya seragam — misalnya yang sebelumnya:

```php
        TarifTindakan::factory()->create([
            'tindakan_id' => $tindakan->id, 'penjamin_id' => $penjamin->id,
            'tarif' => 50000, 'berlaku_mulai' => '2026-01-01',
        ]);
```

menjadi:

```php
        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Tindakan, 'layanan_id' => $tindakan->id,
            'penjamin_id' => $penjamin->id, 'harga' => 50000, 'berlaku_mulai' => '2026-01-01',
        ]);
```

dan yang sebelumnya `HargaObat::factory()` menjadi `Tarif::factory()` dengan
`'jenis_layanan' => JenisLayanan::Obat` serta `'layanan_id' => $obat->id`.

- [ ] **Step 11: Jalankan seluruh test**

Run: `php artisan test`
Diharapkan: seluruh test lulus, dengan jumlah berkurang sebanyak test `HargaObatTest`
yang dihapus dan bertambah sebanyak test baru pada `TarifTest`. **Satu pun test yang
merah karena perilaku berubah berarti penyatuannya salah** — perbaiki penyatuannya,
jangan longgarkan test-nya.

- [ ] **Step 12: Pastikan seeder tetap jalan**

```bash
php artisan migrate:fresh --seed
mysql -u irvan -p1 simrs -e "SELECT jenis_layanan, COUNT(*) FROM tarif GROUP BY jenis_layanan;"
```

Diharapkan: `tindakan` 60 baris, `obat` 100 baris, dan tabel `tarif_tindakan` serta
`harga_obat` sudah tidak ada.

- [ ] **Step 13: Commit**

```bash
git add -A
git commit -m "refactor: satukan tarif tindakan dan harga obat menjadi satu tabel tarif"
```

---

### Task 2: Sumber baris tagihan menjadi polimorfik

**Files:**
- Create: migration `ubah_tagihan_detail_menjadi_polimorfik`
- Modify: `app/Models/TagihanDetail.php`, `app/Services/PenyusunTagihan.php`, `app/Services/PenyiapanResep.php`
- Test: `tests/Feature/SumberTagihanTest.php`

**Interfaces:**
- Consumes: `Tarif` (Task 1)
- Produces: `tagihan_detail` berkolom `sumber_tipe` dan `sumber_id`; `TagihanDetail::sumber()` berupa relasi `morphTo`. `PenyusunTagihan::hapusBarisDari(Tagihan $tagihan, string $sumberTipe): void` menggantikan penghapusan berbasis kolom nullable.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/SumberTagihanTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\JenisLayanan;
use App\Enums\StatusTagihan;
use App\Models\BatchObat;
use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\Penjamin;
use App\Models\ResepDetail;
use App\Models\Tagihan;
use App\Models\Tarif;
use App\Models\TindakanKunjungan;
use App\Models\User;
use App\Services\PenulisanResep;
use App\Services\PenyiapanResep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SumberTagihanTest extends TestCase
{
    use RefreshDatabase;

    public function test_baris_obat_menunjuk_resep_detail_sebagai_sumbernya(): void
    {
        $umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $obat = Obat::factory()->create(['nama' => 'Paracetamol 500 mg']);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Obat, 'layanan_id' => $obat->id,
            'penjamin_id' => $umum->id, 'harga' => 1500, 'berlaku_mulai' => '2026-01-01',
        ]);

        BatchObat::factory()->create([
            'obat_id' => $obat->id, 'tanggal_kedaluwarsa' => '2029-01-31',
            'jumlah_awal' => 100, 'jumlah_tersisa' => 100,
        ]);

        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $umum->id]);
        $tagihan = Tagihan::factory()->create([
            'kunjungan_id' => $kunjungan->id, 'penjamin_id' => $umum->id,
            'status' => StatusTagihan::BelumBayar,
        ]);

        $resep = app(PenulisanResep::class)->tulis($kunjungan, [[
            'obat_id' => $obat->id, 'jumlah' => 10, 'aturan_pakai' => '3x1',
        ]], User::factory()->create());

        app(PenyiapanResep::class)->siapkan($resep, User::factory()->create());

        $baris = $tagihan->refresh()->detail()->where('sumber_tipe', ResepDetail::class)->first();

        $this->assertNotNull($baris);
        $this->assertInstanceOf(ResepDetail::class, $baris->sumber);
        $this->assertSame('Paracetamol 500 mg', $baris->deskripsi);
    }

    public function test_baris_tindakan_menunjuk_tindakan_kunjungan_sebagai_sumbernya(): void
    {
        $umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $tindakan = \App\Models\Tindakan::factory()->create(['nama' => 'Konsultasi Dokter Umum']);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Tindakan, 'layanan_id' => $tindakan->id,
            'penjamin_id' => $umum->id, 'harga' => 50000, 'berlaku_mulai' => '2026-01-01',
        ]);

        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $umum->id]);
        app(\App\Services\TindakanPelayanan::class)
            ->tambah($kunjungan, $tindakan->id, 1, User::factory()->create());

        $tagihan = app(\App\Services\PenyusunTagihan::class)->susun($kunjungan->refresh());
        $baris = $tagihan->detail()->first();

        $this->assertSame(TindakanKunjungan::class, $baris->sumber_tipe);
        $this->assertInstanceOf(TindakanKunjungan::class, $baris->sumber);
    }

    public function test_seluruh_komponen_biaya_satu_kunjungan_terbaca_dalam_satu_query(): void
    {
        $umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $umum->id]);
        $tagihan = Tagihan::factory()->create([
            'kunjungan_id' => $kunjungan->id, 'penjamin_id' => $umum->id,
        ]);

        $tagihan->detail()->create([
            'sumber_tipe' => TindakanKunjungan::class, 'sumber_id' => 1,
            'deskripsi' => 'Konsultasi', 'jumlah' => 1, 'tarif_satuan' => 50000, 'subtotal' => 50000,
        ]);
        $tagihan->detail()->create([
            'sumber_tipe' => ResepDetail::class, 'sumber_id' => 1,
            'deskripsi' => 'Paracetamol', 'jumlah' => 10, 'tarif_satuan' => 1500, 'subtotal' => 15000,
        ]);

        $ringkasan = $tagihan->detail()
            ->selectRaw('sumber_tipe, SUM(subtotal) AS total')
            ->groupBy('sumber_tipe')
            ->pluck('total', 'sumber_tipe');

        $this->assertSame(50000, (int) $ringkasan[TindakanKunjungan::class]);
        $this->assertSame(15000, (int) $ringkasan[ResepDetail::class]);
    }
}
```

Test ketiga adalah alasan seluruh penyatuan ini dikerjakan: satu query mengembalikan
rincian biaya per jenis layanan. Itulah yang dibutuhkan modul klaim pada fase berikutnya.

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=SumberTagihanTest`
Diharapkan: FAIL — kolom `sumber_tipe` belum ada.

- [ ] **Step 3: Tulis migration**

```bash
php artisan make:migration ubah_tagihan_detail_menjadi_polimorfik
```

```php
public function up(): void
{
    Schema::table('tagihan_detail', function (Blueprint $table) {
        $table->string('sumber_tipe', 100)->nullable()->after('tagihan_id');
        $table->unsignedBigInteger('sumber_id')->nullable()->after('sumber_tipe');
        $table->index(['sumber_tipe', 'sumber_id']);
    });

    // Pindahkan penanda sumber dari dua kolom nullable ke sepasang kolom polimorfik.
    DB::table('tagihan_detail')->whereNotNull('tindakan_kunjungan_id')->update([
        'sumber_tipe' => \App\Models\TindakanKunjungan::class,
        'sumber_id' => DB::raw('tindakan_kunjungan_id'),
    ]);

    DB::table('tagihan_detail')->whereNotNull('resep_detail_id')->update([
        'sumber_tipe' => \App\Models\ResepDetail::class,
        'sumber_id' => DB::raw('resep_detail_id'),
    ]);

    Schema::table('tagihan_detail', function (Blueprint $table) {
        $table->dropConstrainedForeignId('tindakan_kunjungan_id');
        $table->dropConstrainedForeignId('resep_detail_id');
    });
}

public function down(): void
{
    Schema::table('tagihan_detail', function (Blueprint $table) {
        $table->dropIndex(['sumber_tipe', 'sumber_id']);
        $table->dropColumn(['sumber_tipe', 'sumber_id']);
    });
}
```

Tambahkan `use Illuminate\Support\Facades\DB;` di bagian atas migration.

- [ ] **Step 4: Tambahkan relasi morphTo**

Di `app/Models/TagihanDetail.php`, tambahkan import `Illuminate\Database\Eloquent\Relations\MorphTo` dan method:

```php
    public function sumber(): MorphTo
    {
        return $this->morphTo(null, 'sumber_tipe', 'sumber_id');
    }
```

- [ ] **Step 5: Perbarui penulis baris tagihan**

Di `app/Services/PenyusunTagihan.php`, pada `susun()` ganti kunci baris tindakan:

```php
                $tagihan->detail()->create([
                    'sumber_tipe' => $item::class,
                    'sumber_id' => $item->id,
                    'deskripsi' => $item->tindakan->nama,
                    'jumlah' => $item->jumlah,
                    'tarif_satuan' => $item->tarif_satuan,
                    'subtotal' => $item->subtotal(),
                ]);
```

pada `tambahObat()`:

```php
                $tagihan->detail()->create([
                    'sumber_tipe' => $baris::class,
                    'sumber_id' => $baris->id,
                    'deskripsi' => $baris->obat->nama,
                    'jumlah' => $baris->jumlah_diserahkan,
                    'tarif_satuan' => $baris->harga_satuan,
                    'subtotal' => $baris->subtotal(),
                ]);
```

dan tambahkan method pembantu:

```php
    /**
     * Mencabut seluruh baris yang berasal dari satu jenis sumber. Dipakai saat
     * penyiapan resep dibatalkan, dan nanti saat order laboratorium dibatalkan.
     */
    public function hapusBarisDari(Tagihan $tagihan, string $sumberTipe): void
    {
        $tagihan->detail()->where('sumber_tipe', $sumberTipe)->delete();
    }
```

- [ ] **Step 6: Perbarui pembatalan penyiapan**

Di `app/Services/PenyiapanResep.php` pada `batalkan()`, ganti:

```php
                    $tagihan->detail()->whereNotNull('resep_detail_id')->delete();
```

menjadi:

```php
                    $this->penyusunTagihan->hapusBarisDari($tagihan, ResepDetail::class);
```

Tambahkan `use App\Models\ResepDetail;` bila belum ada.

- [ ] **Step 7: Perbarui test yang merujuk kolom lama**

`tests/Feature/PenyerahanObatTest.php` memakai
`$tagihan->detail()->whereNotNull('resep_detail_id')->count()`. Ganti menjadi:

```php
        $this->assertSame(0, $tagihan->detail()->where('sumber_tipe', \App\Models\ResepDetail::class)->count());
```

- [ ] **Step 8: Jalankan seluruh test**

Run: `php artisan test`
Diharapkan: seluruh test lulus, termasuk 3 test baru pada `SumberTagihanTest`.

- [ ] **Step 9: Pastikan seeder tetap jalan**

```bash
php artisan migrate:fresh --seed
mysql -u irvan -p1 simrs -e "SELECT sumber_tipe, COUNT(*) FROM tagihan_detail GROUP BY sumber_tipe;"
```

Diharapkan: dua baris — `App\Models\TindakanKunjungan` dan `App\Models\ResepDetail`.

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "refactor: jadikan sumber baris tagihan polimorfik"
```

---
### Task 3: Enum laboratorium dan peran analis

**Files:**
- Create: `app/Enums/StatusOrderLab.php`, `app/Enums/PenandaHasil.php`
- Modify: `app/Enums/Peran.php`
- Test: `tests/Unit/EnumLabTest.php`

**Interfaces:**
- Consumes: —
- Produces: `StatusOrderLab` dengan case `Dipesan`, `SampelDiambil`, `HasilDientri`, `Divalidasi`, `Batal`, method `bisaEntriHasil(): bool` (hanya `SampelDiambil` dan `HasilDientri`) serta `selesai(): bool` (`Divalidasi` atau `Batal`). `PenandaHasil` dengan case `Rendah`, `Normal`, `Tinggi`. `Peran::Analis` bernilai `'analis'`.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Unit/EnumLabTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Enums\Peran;
use App\Enums\PenandaHasil;
use App\Enums\StatusOrderLab;
use PHPUnit\Framework\TestCase;

class EnumLabTest extends TestCase
{
    public function test_hasil_hanya_bisa_dientri_setelah_sampel_diambil(): void
    {
        $this->assertFalse(StatusOrderLab::Dipesan->bisaEntriHasil());
        $this->assertTrue(StatusOrderLab::SampelDiambil->bisaEntriHasil());
        $this->assertTrue(StatusOrderLab::HasilDientri->bisaEntriHasil());
        $this->assertFalse(StatusOrderLab::Divalidasi->bisaEntriHasil());
        $this->assertFalse(StatusOrderLab::Batal->bisaEntriHasil());
    }

    public function test_order_dianggap_selesai_saat_divalidasi_atau_batal(): void
    {
        $this->assertTrue(StatusOrderLab::Divalidasi->selesai());
        $this->assertTrue(StatusOrderLab::Batal->selesai());
        $this->assertFalse(StatusOrderLab::Dipesan->selesai());
        $this->assertFalse(StatusOrderLab::SampelDiambil->selesai());
        $this->assertFalse(StatusOrderLab::HasilDientri->selesai());
    }

    public function test_penanda_hasil_lengkap(): void
    {
        $this->assertSame(
            ['rendah', 'normal', 'tinggi'],
            array_column(PenandaHasil::cases(), 'value')
        );
    }

    public function test_analis_termasuk_daftar_peran(): void
    {
        $this->assertContains('analis', Peran::semua());
        $this->assertCount(8, Peran::semua());
    }
}
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=EnumLabTest`
Diharapkan: FAIL dengan "Class App\Enums\StatusOrderLab not found".

- [ ] **Step 3: Tulis enum**

`app/Enums/StatusOrderLab.php`:

```php
<?php

namespace App\Enums;

enum StatusOrderLab: string
{
    case Dipesan = 'dipesan';
    case SampelDiambil = 'sampel_diambil';
    case HasilDientri = 'hasil_dientri';
    case Divalidasi = 'divalidasi';
    case Batal = 'batal';

    /**
     * Hasil hanya boleh dientri setelah sampel benar-benar diambil (aturan 38),
     * dan masih boleh diperbaiki selama belum divalidasi.
     */
    public function bisaEntriHasil(): bool
    {
        return in_array($this, [self::SampelDiambil, self::HasilDientri], true);
    }

    /**
     * Order yang sudah selesai tidak lagi menahan penyelesaian kunjungan (aturan 37).
     */
    public function selesai(): bool
    {
        return in_array($this, [self::Divalidasi, self::Batal], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Dipesan => 'Menunggu Sampel',
            self::SampelDiambil => 'Sampel Diambil',
            self::HasilDientri => 'Menunggu Validasi',
            self::Divalidasi => 'Selesai',
            self::Batal => 'Batal',
        };
    }
}
```

`app/Enums/PenandaHasil.php`:

```php
<?php

namespace App\Enums;

enum PenandaHasil: string
{
    case Rendah = 'rendah';
    case Normal = 'normal';
    case Tinggi = 'tinggi';

    public function abnormal(): bool
    {
        return $this !== self::Normal;
    }
}
```

- [ ] **Step 4: Tambahkan peran analis**

Di `app/Enums/Peran.php`, sisipkan sebelum `Admin`:

```php
    case Analis = 'analis';
```

- [ ] **Step 5: Jalankan test sampai lulus**

Run: `php artisan test --filter=EnumLabTest`
Diharapkan: PASS, 4 test.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: tambah enum status order lab, penanda hasil, dan peran analis"
```

---

### Task 4: Master pemeriksaan laboratorium dan penandaan nilai

**Files:**
- Create: migration `create_master_lab_tables`, `app/Models/PemeriksaanLab.php`, `app/Models/ParameterLab.php`, `app/Models/RujukanLab.php`, factory ketiganya, `app/Services/PenandaNilai.php`
- Test: `tests/Feature/PenandaNilaiTest.php`

**Interfaces:**
- Consumes: `PenandaHasil` (Task 3), `JenisKelamin` (Fase 1)
- Produces:
  - Model `PemeriksaanLab` (relasi `parameter()`), `ParameterLab` (relasi `pemeriksaan()`, `rujukan()`), `RujukanLab`.
  - `PenandaNilai::untuk(ParameterLab $parameter, float $nilai, JenisKelamin $jenisKelamin): ?PenandaHasil` — mengembalikan null bila tidak ada rujukan yang cocok, sambil mencatat peringatan.

Memenuhi aturan 40 dan 41.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/PenandaNilaiTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\JenisKelamin;
use App\Enums\PenandaHasil;
use App\Models\ParameterLab;
use App\Models\PemeriksaanLab;
use App\Models\RujukanLab;
use App\Services\PenandaNilai;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PenandaNilaiTest extends TestCase
{
    use RefreshDatabase;

    private ParameterLab $hemoglobin;

    protected function setUp(): void
    {
        parent::setUp();

        $pemeriksaan = PemeriksaanLab::factory()->create(['nama' => 'Darah Rutin']);

        $this->hemoglobin = ParameterLab::factory()->create([
            'pemeriksaan_lab_id' => $pemeriksaan->id,
            'kode' => 'HB',
            'nama' => 'Hemoglobin',
            'satuan' => 'g/dL',
        ]);

        // Rentang normal hemoglobin memang berbeda antara laki-laki dan perempuan.
        RujukanLab::factory()->create([
            'parameter_lab_id' => $this->hemoglobin->id,
            'jenis_kelamin' => 'L', 'nilai_min' => 13.0, 'nilai_maks' => 17.0,
        ]);
        RujukanLab::factory()->create([
            'parameter_lab_id' => $this->hemoglobin->id,
            'jenis_kelamin' => 'P', 'nilai_min' => 12.0, 'nilai_maks' => 15.0,
        ]);
    }

    private function penanda(float $nilai, JenisKelamin $jk): ?PenandaHasil
    {
        return app(PenandaNilai::class)->untuk($this->hemoglobin, $nilai, $jk);
    }

    public function test_nilai_di_dalam_rentang_ditandai_normal(): void
    {
        $this->assertSame(PenandaHasil::Normal, $this->penanda(14.0, JenisKelamin::LakiLaki));
    }

    public function test_nilai_di_bawah_rentang_ditandai_rendah(): void
    {
        $this->assertSame(PenandaHasil::Rendah, $this->penanda(11.0, JenisKelamin::LakiLaki));
    }

    public function test_nilai_di_atas_rentang_ditandai_tinggi(): void
    {
        $this->assertSame(PenandaHasil::Tinggi, $this->penanda(18.5, JenisKelamin::LakiLaki));
    }

    public function test_batas_rentang_terhitung_normal(): void
    {
        $this->assertSame(PenandaHasil::Normal, $this->penanda(13.0, JenisKelamin::LakiLaki));
        $this->assertSame(PenandaHasil::Normal, $this->penanda(17.0, JenisKelamin::LakiLaki));
    }

    public function test_nilai_yang_sama_bisa_normal_bagi_pria_tapi_tinggi_bagi_wanita(): void
    {
        // Inilah alasan rujukan dibedakan menurut jenis kelamin.
        $this->assertSame(PenandaHasil::Normal, $this->penanda(16.0, JenisKelamin::LakiLaki));
        $this->assertSame(PenandaHasil::Tinggi, $this->penanda(16.0, JenisKelamin::Perempuan));
    }

    public function test_rujukan_semua_dipakai_bila_tidak_ada_yang_khusus(): void
    {
        $pemeriksaan = PemeriksaanLab::factory()->create(['nama' => 'Kimia Klinik']);
        $glukosa = ParameterLab::factory()->create([
            'pemeriksaan_lab_id' => $pemeriksaan->id,
            'kode' => 'GDS', 'nama' => 'Gula Darah Sewaktu', 'satuan' => 'mg/dL',
        ]);

        RujukanLab::factory()->create([
            'parameter_lab_id' => $glukosa->id,
            'jenis_kelamin' => 'semua', 'nilai_min' => 70, 'nilai_maks' => 140,
        ]);

        $penanda = app(PenandaNilai::class);

        $this->assertSame(PenandaHasil::Normal, $penanda->untuk($glukosa, 100, JenisKelamin::LakiLaki));
        $this->assertSame(PenandaHasil::Tinggi, $penanda->untuk($glukosa, 200, JenisKelamin::Perempuan));
    }

    public function test_parameter_tanpa_rujukan_menghasilkan_penanda_kosong(): void
    {
        $pemeriksaan = PemeriksaanLab::factory()->create();
        $tanpaRujukan = ParameterLab::factory()->create([
            'pemeriksaan_lab_id' => $pemeriksaan->id, 'kode' => 'XX', 'nama' => 'Tanpa Rujukan',
        ]);

        $this->assertNull(app(PenandaNilai::class)->untuk($tanpaRujukan, 5, JenisKelamin::LakiLaki));
    }

    public function test_ketiadaan_rujukan_dicatat_sebagai_peringatan(): void
    {
        $pemeriksaan = PemeriksaanLab::factory()->create();
        $tanpaRujukan = ParameterLab::factory()->create([
            'pemeriksaan_lab_id' => $pemeriksaan->id, 'kode' => 'YY', 'nama' => 'Tanpa Rujukan',
        ]);

        Log::spy();

        app(PenandaNilai::class)->untuk($tanpaRujukan, 5, JenisKelamin::LakiLaki);

        Log::shouldHaveReceived('warning')->once();
    }
}
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=PenandaNilaiTest`
Diharapkan: FAIL dengan "Class App\Models\PemeriksaanLab not found".

- [ ] **Step 3: Tulis migration**

```bash
php artisan make:migration create_master_lab_tables
```

```php
Schema::create('pemeriksaan_lab', function (Blueprint $table) {
    $table->id();
    $table->string('kode', 20)->unique();
    $table->string('nama', 150);
    $table->enum('kategori', ['hematologi', 'kimia_klinik', 'urinalisis', 'imunologi', 'mikrobiologi']);
    $table->boolean('aktif')->default(true);
    $table->timestamps();
});

Schema::create('parameter_lab', function (Blueprint $table) {
    $table->id();
    $table->foreignId('pemeriksaan_lab_id')->constrained('pemeriksaan_lab')->cascadeOnDelete();
    $table->string('kode', 20);
    $table->string('nama', 100);
    $table->string('satuan', 20)->nullable();
    $table->unsignedSmallInteger('urutan')->default(1);
    $table->timestamps();
    $table->unique(['pemeriksaan_lab_id', 'kode']);
});

Schema::create('rujukan_lab', function (Blueprint $table) {
    $table->id();
    $table->foreignId('parameter_lab_id')->constrained('parameter_lab')->cascadeOnDelete();
    $table->enum('jenis_kelamin', ['L', 'P', 'semua']);
    $table->decimal('nilai_min', 10, 2);
    $table->decimal('nilai_maks', 10, 2);
    $table->timestamps();
    $table->unique(['parameter_lab_id', 'jenis_kelamin']);
});
```

- [ ] **Step 4: Tulis model**

`app/Models/PemeriksaanLab.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PemeriksaanLab extends Model
{
    use HasFactory;

    protected $table = 'pemeriksaan_lab';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function parameter(): HasMany
    {
        return $this->hasMany(ParameterLab::class)->orderBy('urutan');
    }
}
```

`app/Models/ParameterLab.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParameterLab extends Model
{
    use HasFactory;

    protected $table = 'parameter_lab';

    protected $guarded = [];

    public function pemeriksaan(): BelongsTo
    {
        return $this->belongsTo(PemeriksaanLab::class, 'pemeriksaan_lab_id');
    }

    public function rujukan(): HasMany
    {
        return $this->hasMany(RujukanLab::class, 'parameter_lab_id');
    }
}
```

`app/Models/RujukanLab.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RujukanLab extends Model
{
    use HasFactory;

    protected $table = 'rujukan_lab';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['nilai_min' => 'float', 'nilai_maks' => 'float'];
    }

    public function parameter(): BelongsTo
    {
        return $this->belongsTo(ParameterLab::class, 'parameter_lab_id');
    }

    public function rentang(): string
    {
        return $this->nilai_min.' – '.$this->nilai_maks;
    }
}
```

- [ ] **Step 5: Tulis factory**

`database/factories/PemeriksaanLabFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\PemeriksaanLab;
use Illuminate\Database\Eloquent\Factories\Factory;

class PemeriksaanLabFactory extends Factory
{
    protected $model = PemeriksaanLab::class;

    public function definition(): array
    {
        return [
            'kode' => strtoupper($this->faker->unique()->bothify('LAB###')),
            'nama' => 'Pemeriksaan '.$this->faker->unique()->word(),
            'kategori' => 'kimia_klinik',
            'aktif' => true,
        ];
    }
}
```

`database/factories/ParameterLabFactory.php` mengisi `pemeriksaan_lab_id` dari
`PemeriksaanLab::factory()`, `kode` unik, `nama`, `satuan`, dan `urutan` 1.

`database/factories/RujukanLabFactory.php` mengisi `parameter_lab_id` dari
`ParameterLab::factory()`, `jenis_kelamin` `'semua'`, `nilai_min` 0, `nilai_maks` 100.

- [ ] **Step 6: Tulis PenandaNilai**

`app/Services/PenandaNilai.php`:

```php
<?php

namespace App\Services;

use App\Enums\JenisKelamin;
use App\Enums\PenandaHasil;
use App\Models\ParameterLab;
use Illuminate\Support\Facades\Log;

/**
 * Menentukan rendah/normal/tinggi dari rujukan sesuai jenis kelamin pasien
 * (aturan 40). Penanda dihitung sistem, tidak pernah diketik petugas.
 */
class PenandaNilai
{
    public function untuk(ParameterLab $parameter, float $nilai, JenisKelamin $jenisKelamin): ?PenandaHasil
    {
        $rujukan = $parameter->rujukan()
            ->whereIn('jenis_kelamin', [$jenisKelamin->value, 'semua'])
            // Rujukan khusus jenis kelamin didahulukan; 'semua' hanya dipakai
            // bila yang khusus tidak ada (aturan 41).
            ->orderByRaw("CASE WHEN jenis_kelamin = ? THEN 0 ELSE 1 END", [$jenisKelamin->value])
            ->first();

        if ($rujukan === null) {
            Log::warning('Parameter laboratorium tanpa nilai rujukan yang cocok.', [
                'parameter_lab_id' => $parameter->id,
                'parameter' => $parameter->nama,
                'jenis_kelamin' => $jenisKelamin->value,
            ]);

            return null;
        }

        if ($nilai < $rujukan->nilai_min) {
            return PenandaHasil::Rendah;
        }

        if ($nilai > $rujukan->nilai_maks) {
            return PenandaHasil::Tinggi;
        }

        return PenandaHasil::Normal;
    }
}
```

- [ ] **Step 7: Jalankan test sampai lulus**

Run: `php artisan test --filter=PenandaNilaiTest`
Diharapkan: PASS, 8 test.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: tambah master pemeriksaan lab dan penandaan nilai menurut rujukan"
```

---
### Task 5: Pemesanan laboratorium

**Files:**
- Create: migration `create_order_lab_tables`, `app/Models/OrderLab.php`, `app/Models/OrderLabDetail.php`, `app/Services/PemesananLab.php`
- Modify: `app/Models/Kunjungan.php`, `app/Services/NomorDokumen.php`, `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/PemesananLabTest.php`

**Interfaces:**
- Consumes: `PencariTarif` (Task 1), `StatusOrderLab` (Task 3), `PemeriksaanLab` (Task 4), `NomorDokumen` (Fase 1)
- Produces:
  - `PemesananLab::pesan(Kunjungan $kunjungan, array $pemeriksaanId, User $dokter, ?string $catatanKlinis = null): OrderLab`
  - `PemesananLab::batalkan(OrderLab $order, User $petugas, string $alasan): OrderLab`
  - Model `OrderLab` (relasi `kunjungan`, `detail`, scope `belumSelesai()`), `OrderLabDetail` (relasi `order`, `pemeriksaan`, `hasil`).
  - `NomorDokumen` menerima jenis `'lab'` berawalan `LB`.
  - `Kunjungan::orderLab(): HasMany`.

Memenuhi aturan 35, 36, 45, dan 46.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/PemesananLabTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\JenisLayanan;
use App\Enums\StatusOrderLab;
use App\Models\Kunjungan;
use App\Models\PemeriksaanLab;
use App\Models\Penjamin;
use App\Models\Tarif;
use App\Models\User;
use App\Services\PemesananLab;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class PemesananLabTest extends TestCase
{
    use RefreshDatabase;

    private Penjamin $umum;
    private PemeriksaanLab $darahRutin;
    private Kunjungan $kunjungan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $this->darahRutin = PemeriksaanLab::factory()->create([
            'nama' => 'Darah Rutin', 'kategori' => 'hematologi',
        ]);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Lab,
            'layanan_id' => $this->darahRutin->id,
            'penjamin_id' => $this->umum->id,
            'harga' => 75000,
            'berlaku_mulai' => '2026-01-01',
        ]);

        $this->kunjungan = Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]);
    }

    private function dokter(): User
    {
        return User::factory()->create();
    }

    public function test_order_lab_bernomor_dan_berstatus_dipesan(): void
    {
        $order = app(PemesananLab::class)
            ->pesan($this->kunjungan, [$this->darahRutin->id], $this->dokter(), 'Curiga anemia');

        $this->assertStringStartsWith('LB-', $order->no_order);
        $this->assertSame(StatusOrderLab::Dipesan, $order->status);
        $this->assertSame('Curiga anemia', $order->catatan_klinis);
        $this->assertSame(1, $order->detail()->count());
    }

    public function test_order_wajib_memuat_minimal_satu_pemeriksaan(): void
    {
        $this->expectException(ValidationException::class);

        app(PemesananLab::class)->pesan($this->kunjungan, [], $this->dokter());
    }

    public function test_pemeriksaan_yang_sama_tidak_boleh_dipesan_dua_kali_dalam_satu_order(): void
    {
        $this->expectException(ValidationException::class);

        app(PemesananLab::class)->pesan(
            $this->kunjungan,
            [$this->darahRutin->id, $this->darahRutin->id],
            $this->dokter()
        );
    }

    public function test_tarif_disalin_saat_order_dibuat(): void
    {
        $order = app(PemesananLab::class)
            ->pesan($this->kunjungan, [$this->darahRutin->id], $this->dokter());

        $this->assertSame(75000, (int) $order->detail->first()->tarif_satuan);
    }

    public function test_perubahan_master_tarif_tidak_mengubah_order_yang_sudah_dibuat(): void
    {
        $order = app(PemesananLab::class)
            ->pesan($this->kunjungan, [$this->darahRutin->id], $this->dokter());

        Tarif::query()->update(['harga' => 120000]);

        $this->assertSame(75000, (int) $order->detail->first()->refresh()->tarif_satuan);
    }

    public function test_order_tidak_bisa_dibuat_pada_kunjungan_yang_sudah_selesai(): void
    {
        $selesai = Kunjungan::factory()->create([
            'penjamin_id' => $this->umum->id,
            'status' => \App\Enums\StatusKunjungan::Selesai,
        ]);

        $this->expectException(RuntimeException::class);

        app(PemesananLab::class)->pesan($selesai, [$this->darahRutin->id], $this->dokter());
    }

    public function test_pembatalan_sebelum_sampel_diambil_menandai_order_batal(): void
    {
        $order = app(PemesananLab::class)
            ->pesan($this->kunjungan, [$this->darahRutin->id], $this->dokter());

        $dibatalkan = app(PemesananLab::class)
            ->batalkan($order, $this->dokter(), 'Salah pesan');

        $this->assertSame(StatusOrderLab::Batal, $dibatalkan->status);
    }

    public function test_pembatalan_wajib_menyertakan_alasan(): void
    {
        $order = app(PemesananLab::class)
            ->pesan($this->kunjungan, [$this->darahRutin->id], $this->dokter());

        $this->expectException(ValidationException::class);

        app(PemesananLab::class)->batalkan($order, $this->dokter(), '   ');
    }

    public function test_alasan_pembatalan_tercatat_di_audit_log(): void
    {
        $order = app(PemesananLab::class)
            ->pesan($this->kunjungan, [$this->darahRutin->id], $this->dokter());

        app(PemesananLab::class)->batalkan($order, $this->dokter(), 'Pasien menolak diambil darah');

        $this->assertDatabaseHas('audit_logs', ['alasan' => 'Pasien menolak diambil darah']);
    }

    public function test_order_yang_sudah_batal_tidak_bisa_dibatalkan_lagi(): void
    {
        $order = app(PemesananLab::class)
            ->pesan($this->kunjungan, [$this->darahRutin->id], $this->dokter());

        app(PemesananLab::class)->batalkan($order, $this->dokter(), 'Salah pesan');

        $this->expectException(RuntimeException::class);

        app(PemesananLab::class)->batalkan($order->refresh(), $this->dokter(), 'Sekali lagi');
    }
}
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=PemesananLabTest`
Diharapkan: FAIL dengan "Target class [App\Services\PemesananLab] does not exist."

- [ ] **Step 3: Tulis migration**

```bash
php artisan make:migration create_order_lab_tables
```

```php
Schema::create('order_lab', function (Blueprint $table) {
    $table->id();
    $table->string('no_order', 20)->unique();
    $table->foreignId('kunjungan_id')->constrained('kunjungan')->cascadeOnDelete();
    $table->foreignId('dokter_id')->nullable()->constrained('users')->nullOnDelete();
    $table->string('status', 20)->default('dipesan');
    $table->string('catatan_klinis', 255)->nullable();
    $table->timestamp('waktu_sampel')->nullable();
    $table->foreignId('diambil_oleh')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('waktu_hasil')->nullable();
    $table->foreignId('dientri_oleh')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('waktu_validasi')->nullable();
    $table->foreignId('divalidasi_oleh')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->index(['kunjungan_id', 'status']);
});

Schema::create('order_lab_detail', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_lab_id')->constrained('order_lab')->cascadeOnDelete();
    $table->foreignId('pemeriksaan_lab_id')->constrained('pemeriksaan_lab');
    $table->unsignedBigInteger('tarif_satuan');
    $table->timestamps();
    $table->unique(['order_lab_id', 'pemeriksaan_lab_id']);
});
```

- [ ] **Step 4: Tulis model**

`app/Models/OrderLab.php`:

```php
<?php

namespace App\Models;

use App\Enums\StatusOrderLab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderLab extends Model
{
    use HasFactory;

    protected $table = 'order_lab';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => StatusOrderLab::class,
            'waktu_sampel' => 'datetime',
            'waktu_hasil' => 'datetime',
            'waktu_validasi' => 'datetime',
        ];
    }

    public function kunjungan(): BelongsTo
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function detail(): HasMany
    {
        return $this->hasMany(OrderLabDetail::class, 'order_lab_id');
    }

    /**
     * Order yang masih menahan penyelesaian kunjungan (aturan 37).
     */
    public function scopeBelumSelesai(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            StatusOrderLab::Divalidasi->value,
            StatusOrderLab::Batal->value,
        ]);
    }

    public function totalTarif(): int
    {
        return (int) $this->detail()->sum('tarif_satuan');
    }
}
```

`app/Models/OrderLabDetail.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderLabDetail extends Model
{
    use HasFactory;

    protected $table = 'order_lab_detail';

    protected $guarded = [];

    public function order(): BelongsTo
    {
        return $this->belongsTo(OrderLab::class, 'order_lab_id');
    }

    public function pemeriksaan(): BelongsTo
    {
        return $this->belongsTo(PemeriksaanLab::class, 'pemeriksaan_lab_id');
    }

    public function hasil(): HasMany
    {
        return $this->hasMany(HasilLab::class, 'order_lab_detail_id');
    }
}
```

`HasilLab` baru dibuat di Task 6; relasi ini belum dipanggil test mana pun sampai saat itu.

Tambahkan ke `app/Models/Kunjungan.php`:

```php
    public function orderLab(): HasMany
    {
        return $this->hasMany(OrderLab::class);
    }
```

- [ ] **Step 5: Tambahkan awalan nomor lab**

Di `app/Services/NomorDokumen.php`, tambahkan satu baris pada konstanta `AWALAN`:

```php
        'lab' => 'LB',
```

- [ ] **Step 6: Tulis PemesananLab**

`app/Services/PemesananLab.php`:

```php
<?php

namespace App\Services;

use App\Enums\JenisLayanan;
use App\Enums\StatusOrderLab;
use App\Models\Kunjungan;
use App\Models\OrderLab;
use App\Models\User;
use App\Support\KonteksAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PemesananLab
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
        ?string $catatanKlinis = null
    ): OrderLab {
        if (! $kunjungan->status->aktif()) {
            throw new RuntimeException(
                'Pemeriksaan laboratorium tidak bisa dipesan pada kunjungan yang sudah selesai atau dibatalkan.'
            );
        }

        Validator::make(['pemeriksaan' => $pemeriksaanId], [
            'pemeriksaan' => ['required', 'array', 'min:1'],
            'pemeriksaan.*' => ['required', 'exists:pemeriksaan_lab,id'],
        ], [
            'pemeriksaan.required' => 'Order laboratorium harus memuat minimal satu pemeriksaan.',
            'pemeriksaan.min' => 'Order laboratorium harus memuat minimal satu pemeriksaan.',
        ])->validate();

        if (count($pemeriksaanId) !== count(array_unique($pemeriksaanId))) {
            throw ValidationException::withMessages([
                'pemeriksaan' => 'Satu pemeriksaan hanya boleh muncul sekali dalam satu order.',
            ]);
        }

        return DB::transaction(function () use ($kunjungan, $pemeriksaanId, $dokter, $catatanKlinis) {
            $order = OrderLab::create([
                'no_order' => $this->nomorDokumen->berikutnya('lab', $kunjungan->tanggal),
                'kunjungan_id' => $kunjungan->id,
                'dokter_id' => $dokter->id,
                'status' => StatusOrderLab::Dipesan,
                'catatan_klinis' => $catatanKlinis,
            ]);

            foreach ($pemeriksaanId as $id) {
                // Tarif disalin sekarang supaya perubahan master tidak mengubah
                // order lama. Biayanya sendiri baru masuk tagihan saat kunjungan
                // diselesaikan, karena pada titik ini tagihannya memang belum ada.
                $order->detail()->create([
                    'pemeriksaan_lab_id' => $id,
                    'tarif_satuan' => $this->pencariTarif->untuk(
                        JenisLayanan::Lab, (int) $id, $kunjungan->penjamin_id, $kunjungan->tanggal
                    ),
                ]);
            }

            return $order->refresh()->load('detail');
        });
    }

    public function batalkan(OrderLab $order, User $petugas, string $alasan): OrderLab
    {
        if (trim($alasan) === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan pembatalan order laboratorium wajib diisi.',
            ]);
        }

        if ($order->status->selesai()) {
            throw new RuntimeException(
                "Order {$order->no_order} berstatus {$order->status->label()} dan tidak bisa dibatalkan."
            );
        }

        return KonteksAudit::dengan(trim($alasan), function () use ($order) {
            $order->update(['status' => StatusOrderLab::Batal]);

            return $order->refresh();
        });
    }
}
```

- [ ] **Step 7: Daftarkan OrderLab ke audit**

Di `app/Providers/AppServiceProvider.php`, tambahkan `use App\Models\OrderLab;` dan sisipkan `OrderLab::class` ke daftar `modelTerauditkan()`.

- [ ] **Step 8: Jalankan test sampai lulus**

Run: `php artisan test --filter=PemesananLabTest`
Diharapkan: PASS, 10 test.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: tambah pemesanan laboratorium beserta penyalinan tarif dan pembatalannya"
```

---

### Task 6: Pengambilan sampel dan entri hasil

**Files:**
- Create: migration `create_hasil_lab_table`, `app/Models/HasilLab.php`, `app/Services/PemeriksaanLaboratorium.php`
- Test: `tests/Feature/EntriHasilLabTest.php`

**Interfaces:**
- Consumes: `OrderLab` (Task 5), `PenandaNilai` (Task 4)
- Produces:
  - `PemeriksaanLaboratorium::ambilSampel(OrderLab $order, User $analis): OrderLab`
  - `PemeriksaanLaboratorium::entriHasil(OrderLab $order, array $nilai, User $analis): OrderLab` — `$nilai` berbentuk `[parameter_lab_id => nilai]`.
  - Model `HasilLab` berkolom `order_lab_detail_id`, `parameter_lab_id`, `nilai`, `penanda`, `catatan`.

Memenuhi aturan 38, 39, 40, dan 41.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/EntriHasilLabTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\JenisLayanan;
use App\Enums\PenandaHasil;
use App\Enums\StatusOrderLab;
use App\Models\HasilLab;
use App\Models\Kunjungan;
use App\Models\ParameterLab;
use App\Models\Pasien;
use App\Models\PemeriksaanLab;
use App\Models\Penjamin;
use App\Models\RujukanLab;
use App\Models\Tarif;
use App\Models\User;
use App\Services\PemesananLab;
use App\Services\PemeriksaanLaboratorium;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class EntriHasilLabTest extends TestCase
{
    use RefreshDatabase;

    private PemeriksaanLab $darahRutin;
    private ParameterLab $hemoglobin;
    private Penjamin $umum;

    protected function setUp(): void
    {
        parent::setUp();

        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $this->darahRutin = PemeriksaanLab::factory()->create([
            'nama' => 'Darah Rutin', 'kategori' => 'hematologi',
        ]);

        $this->hemoglobin = ParameterLab::factory()->create([
            'pemeriksaan_lab_id' => $this->darahRutin->id,
            'kode' => 'HB', 'nama' => 'Hemoglobin', 'satuan' => 'g/dL',
        ]);

        RujukanLab::factory()->create([
            'parameter_lab_id' => $this->hemoglobin->id,
            'jenis_kelamin' => 'L', 'nilai_min' => 13.0, 'nilai_maks' => 17.0,
        ]);
        RujukanLab::factory()->create([
            'parameter_lab_id' => $this->hemoglobin->id,
            'jenis_kelamin' => 'P', 'nilai_min' => 12.0, 'nilai_maks' => 15.0,
        ]);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Lab, 'layanan_id' => $this->darahRutin->id,
            'penjamin_id' => $this->umum->id, 'harga' => 75000, 'berlaku_mulai' => '2026-01-01',
        ]);
    }

    private function order(string $jenisKelamin = 'L'): \App\Models\OrderLab
    {
        $pasien = Pasien::factory()->create(['jenis_kelamin' => $jenisKelamin]);
        $kunjungan = Kunjungan::factory()->create([
            'pasien_id' => $pasien->id, 'penjamin_id' => $this->umum->id,
        ]);

        return app(PemesananLab::class)
            ->pesan($kunjungan, [$this->darahRutin->id], User::factory()->create());
    }

    private function analis(): User
    {
        return User::factory()->create();
    }

    public function test_pengambilan_sampel_mencatat_waktu_dan_pelakunya(): void
    {
        $order = $this->order();
        $analis = $this->analis();

        $hasil = app(PemeriksaanLaboratorium::class)->ambilSampel($order, $analis);

        $this->assertSame(StatusOrderLab::SampelDiambil, $hasil->status);
        $this->assertNotNull($hasil->waktu_sampel);
        $this->assertSame($analis->id, $hasil->diambil_oleh);
    }

    public function test_hasil_tidak_bisa_dientri_sebelum_sampel_diambil(): void
    {
        $order = $this->order();

        $this->expectException(RuntimeException::class);

        app(PemeriksaanLaboratorium::class)
            ->entriHasil($order, [$this->hemoglobin->id => 14.0], $this->analis());
    }

    public function test_entri_hasil_menyimpan_nilai_dan_penandanya(): void
    {
        $order = $this->order('L');
        $lab = app(PemeriksaanLaboratorium::class);
        $analis = $this->analis();

        $lab->ambilSampel($order, $analis);
        $hasil = $lab->entriHasil($order->refresh(), [$this->hemoglobin->id => 14.0], $analis);

        $this->assertSame(StatusOrderLab::HasilDientri, $hasil->status);
        $this->assertSame($analis->id, $hasil->dientri_oleh);

        $baris = HasilLab::where('parameter_lab_id', $this->hemoglobin->id)->first();

        $this->assertSame(14.0, (float) $baris->nilai);
        $this->assertSame(PenandaHasil::Normal, $baris->penanda);
    }

    public function test_penanda_mengikuti_jenis_kelamin_pasien(): void
    {
        $lab = app(PemeriksaanLaboratorium::class);
        $analis = $this->analis();

        $orderPria = $this->order('L');
        $lab->ambilSampel($orderPria, $analis);
        $lab->entriHasil($orderPria->refresh(), [$this->hemoglobin->id => 16.0], $analis);

        $orderWanita = $this->order('P');
        $lab->ambilSampel($orderWanita, $analis);
        $lab->entriHasil($orderWanita->refresh(), [$this->hemoglobin->id => 16.0], $analis);

        $penandaPria = HasilLab::whereHas('orderDetail', fn ($q) => $q->where('order_lab_id', $orderPria->id))->first();
        $penandaWanita = HasilLab::whereHas('orderDetail', fn ($q) => $q->where('order_lab_id', $orderWanita->id))->first();

        $this->assertSame(PenandaHasil::Normal, $penandaPria->penanda);
        $this->assertSame(PenandaHasil::Tinggi, $penandaWanita->penanda);
    }

    public function test_nilai_bukan_angka_ditolak_dengan_menyebut_parameternya(): void
    {
        $order = $this->order();
        $lab = app(PemeriksaanLaboratorium::class);
        $analis = $this->analis();

        $lab->ambilSampel($order, $analis);

        try {
            $lab->entriHasil($order->refresh(), [$this->hemoglobin->id => 'empat belas'], $analis);
            $this->fail('Nilai bukan angka seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Hemoglobin', implode(' ', $e->errors()[array_key_first($e->errors())]));
        }
    }

    public function test_entri_ulang_memperbarui_nilai_yang_sama_bukan_menambah_baris(): void
    {
        $order = $this->order();
        $lab = app(PemeriksaanLaboratorium::class);
        $analis = $this->analis();

        $lab->ambilSampel($order, $analis);
        $lab->entriHasil($order->refresh(), [$this->hemoglobin->id => 14.0], $analis);
        $lab->entriHasil($order->refresh(), [$this->hemoglobin->id => 11.0], $analis);

        $this->assertSame(1, HasilLab::where('parameter_lab_id', $this->hemoglobin->id)->count());
        $this->assertSame(
            PenandaHasil::Rendah,
            HasilLab::where('parameter_lab_id', $this->hemoglobin->id)->first()->penanda
        );
    }

    public function test_parameter_tanpa_rujukan_tersimpan_tanpa_penanda(): void
    {
        $tanpaRujukan = ParameterLab::factory()->create([
            'pemeriksaan_lab_id' => $this->darahRutin->id,
            'kode' => 'XX', 'nama' => 'Parameter Tanpa Rujukan',
        ]);

        $order = $this->order();
        $lab = app(PemeriksaanLaboratorium::class);
        $analis = $this->analis();

        $lab->ambilSampel($order, $analis);
        $lab->entriHasil($order->refresh(), [$tanpaRujukan->id => 7.5], $analis);

        $baris = HasilLab::where('parameter_lab_id', $tanpaRujukan->id)->first();

        $this->assertSame(7.5, (float) $baris->nilai);
        $this->assertNull($baris->penanda);
    }

    public function test_order_yang_sudah_batal_tidak_bisa_diambil_sampelnya(): void
    {
        $order = $this->order();
        app(PemesananLab::class)->batalkan($order, $this->analis(), 'Salah pesan');

        $this->expectException(RuntimeException::class);

        app(PemeriksaanLaboratorium::class)->ambilSampel($order->refresh(), $this->analis());
    }
}
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=EntriHasilLabTest`
Diharapkan: FAIL dengan "Class App\Models\HasilLab not found".

- [ ] **Step 3: Tulis migration**

```bash
php artisan make:migration create_hasil_lab_table
```

```php
Schema::create('hasil_lab', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_lab_detail_id')->constrained('order_lab_detail')->cascadeOnDelete();
    $table->foreignId('parameter_lab_id')->constrained('parameter_lab');
    $table->decimal('nilai', 12, 2);
    $table->string('penanda', 10)->nullable();
    $table->string('catatan', 255)->nullable();
    $table->timestamps();
    $table->unique(['order_lab_detail_id', 'parameter_lab_id']);
});
```

- [ ] **Step 4: Tulis model HasilLab**

`app/Models/HasilLab.php`:

```php
<?php

namespace App\Models;

use App\Enums\PenandaHasil;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HasilLab extends Model
{
    use HasFactory;

    protected $table = 'hasil_lab';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['nilai' => 'float', 'penanda' => PenandaHasil::class];
    }

    public function orderDetail(): BelongsTo
    {
        return $this->belongsTo(OrderLabDetail::class, 'order_lab_detail_id');
    }

    public function parameter(): BelongsTo
    {
        return $this->belongsTo(ParameterLab::class, 'parameter_lab_id');
    }
}
```

- [ ] **Step 5: Tulis PemeriksaanLaboratorium**

`app/Services/PemeriksaanLaboratorium.php`:

```php
<?php

namespace App\Services;

use App\Enums\StatusOrderLab;
use App\Models\HasilLab;
use App\Models\OrderLab;
use App\Models\ParameterLab;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PemeriksaanLaboratorium
{
    public function __construct(private readonly PenandaNilai $penandaNilai) {}

    public function ambilSampel(OrderLab $order, User $analis): OrderLab
    {
        if ($order->status !== StatusOrderLab::Dipesan) {
            throw new RuntimeException(
                "Order {$order->no_order} berstatus {$order->status->label()} dan sampelnya tidak bisa diambil."
            );
        }

        $order->update([
            'status' => StatusOrderLab::SampelDiambil,
            'waktu_sampel' => now(),
            'diambil_oleh' => $analis->id,
        ]);

        return $order->refresh();
    }

    /**
     * @param  array<int, mixed>  $nilai  parameter_lab_id => nilai
     */
    public function entriHasil(OrderLab $order, array $nilai, User $analis): OrderLab
    {
        if (! $order->status->bisaEntriHasil()) {
            throw new RuntimeException(
                "Hasil belum bisa dientri: order {$order->no_order} berstatus {$order->status->label()}."
            );
        }

        $jenisKelamin = $order->kunjungan->pasien->jenis_kelamin;
        $tervalidasi = $this->validasiNilai($nilai);

        return DB::transaction(function () use ($order, $tervalidasi, $analis, $jenisKelamin) {
            foreach ($tervalidasi as $parameterId => $angka) {
                $parameter = ParameterLab::findOrFail($parameterId);

                $detail = $order->detail()
                    ->where('pemeriksaan_lab_id', $parameter->pemeriksaan_lab_id)
                    ->firstOrFail();

                HasilLab::updateOrCreate([
                    'order_lab_detail_id' => $detail->id,
                    'parameter_lab_id' => $parameter->id,
                ], [
                    'nilai' => $angka,
                    // Penanda dihitung sistem, tidak pernah diketik petugas (aturan 40).
                    'penanda' => $this->penandaNilai->untuk($parameter, $angka, $jenisKelamin),
                ]);
            }

            $order->update([
                'status' => StatusOrderLab::HasilDientri,
                'waktu_hasil' => now(),
                'dientri_oleh' => $analis->id,
            ]);

            return $order->refresh();
        });
    }

    /**
     * @param  array<int, mixed>  $nilai
     * @return array<int, float>
     */
    private function validasiNilai(array $nilai): array
    {
        $tervalidasi = [];

        foreach ($nilai as $parameterId => $angka) {
            if (! is_numeric($angka)) {
                $nama = ParameterLab::find($parameterId)?->nama ?? "#{$parameterId}";

                // Pesannya menyebut parameter mana yang salah — menolak seluruh
                // formulir tanpa keterangan membuat analis menebak-nebak.
                throw ValidationException::withMessages([
                    "nilai.{$parameterId}" => "Nilai {$nama} harus berupa angka.",
                ]);
            }

            $tervalidasi[(int) $parameterId] = (float) $angka;
        }

        return $tervalidasi;
    }
}
```

- [ ] **Step 6: Jalankan test sampai lulus**

Run: `php artisan test --filter=EntriHasilLabTest`
Diharapkan: PASS, 8 test.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: tambah pengambilan sampel dan entri hasil laboratorium berpenanda otomatis"
```

---
### Task 7: Validasi, koreksi, penguncian kunjungan, dan pembebanan tagihan

**Files:**
- Modify: `app/Services/PemeriksaanLaboratorium.php`, `app/Services/PemeriksaanKlinis.php`, `app/Services/PenyusunTagihan.php`, `app/Models/OrderLab.php`
- Test: `tests/Feature/ValidasiHasilLabTest.php`, `tests/Feature/TagihanLabTest.php`

**Interfaces:**
- Consumes: `PemeriksaanLaboratorium` (Task 6), `PenyusunTagihan` (Fase 1)
- Produces:
  - `PemeriksaanLaboratorium::validasi(OrderLab $order, User $analis): OrderLab`
  - `PemeriksaanLaboratorium::koreksi(OrderLab $order, array $nilai, User $analis, string $alasan): OrderLab`
  - `OrderLab::terbacaDokter(): bool`
  - `PemeriksaanKlinis::selesaikan()` menolak selama ada order laboratorium yang belum selesai.
  - `PenyusunTagihan::susun()` ikut memasukkan baris laboratorium.

Memenuhi aturan 36, 37, 42, 43, 44, 45, dan 46.

- [ ] **Step 1: Tulis test validasi yang gagal**

Buat `tests/Feature/ValidasiHasilLabTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\JenisDiagnosa;
use App\Enums\JenisLayanan;
use App\Enums\StatusOrderLab;
use App\Models\HasilLab;
use App\Models\Icd10;
use App\Models\Kunjungan;
use App\Models\OrderLab;
use App\Models\ParameterLab;
use App\Models\PemeriksaanLab;
use App\Models\Penjamin;
use App\Models\RujukanLab;
use App\Models\Tarif;
use App\Models\User;
use App\Services\PemeriksaanKlinis;
use App\Services\PemeriksaanLaboratorium;
use App\Services\PemesananLab;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class ValidasiHasilLabTest extends TestCase
{
    use RefreshDatabase;

    private PemeriksaanLab $darahRutin;
    private ParameterLab $hemoglobin;
    private Penjamin $umum;

    protected function setUp(): void
    {
        parent::setUp();

        $this->umum = Penjamin::factory()->create(['kode' => 'UMUM', 'jenis' => 'tunai']);
        $this->darahRutin = PemeriksaanLab::factory()->create(['nama' => 'Darah Rutin']);

        $this->hemoglobin = ParameterLab::factory()->create([
            'pemeriksaan_lab_id' => $this->darahRutin->id,
            'kode' => 'HB', 'nama' => 'Hemoglobin', 'satuan' => 'g/dL',
        ]);

        RujukanLab::factory()->create([
            'parameter_lab_id' => $this->hemoglobin->id,
            'jenis_kelamin' => 'semua', 'nilai_min' => 12.0, 'nilai_maks' => 17.0,
        ]);

        Tarif::factory()->create([
            'jenis_layanan' => JenisLayanan::Lab, 'layanan_id' => $this->darahRutin->id,
            'penjamin_id' => $this->umum->id, 'harga' => 75000, 'berlaku_mulai' => '2026-01-01',
        ]);
    }

    private function orderBerhasil(): array
    {
        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]);
        $analis = User::factory()->create();

        $order = app(PemesananLab::class)
            ->pesan($kunjungan, [$this->darahRutin->id], User::factory()->create());

        $lab = app(PemeriksaanLaboratorium::class);
        $lab->ambilSampel($order, $analis);
        $lab->entriHasil($order->refresh(), [$this->hemoglobin->id => 14.0], $analis);

        return [$kunjungan, $order->refresh(), $analis];
    }

    public function test_validasi_mencatat_waktu_dan_pelakunya(): void
    {
        [$kunjungan, $order, $analis] = $this->orderBerhasil();

        $divalidasi = app(PemeriksaanLaboratorium::class)->validasi($order, $analis);

        $this->assertSame(StatusOrderLab::Divalidasi, $divalidasi->status);
        $this->assertNotNull($divalidasi->waktu_validasi);
        $this->assertSame($analis->id, $divalidasi->divalidasi_oleh);
    }

    public function test_hasil_belum_divalidasi_tidak_terbaca_dokter(): void
    {
        [$kunjungan, $order] = $this->orderBerhasil();

        $this->assertFalse($order->terbacaDokter());

        app(PemeriksaanLaboratorium::class)->validasi($order, User::factory()->create());

        $this->assertTrue($order->refresh()->terbacaDokter());
    }

    public function test_validasi_oleh_petugas_yang_sama_diperbolehkan_dan_kedua_pelakunya_tercatat(): void
    {
        [$kunjungan, $order, $analis] = $this->orderBerhasil();

        $divalidasi = app(PemeriksaanLaboratorium::class)->validasi($order, $analis);

        $this->assertSame($analis->id, $divalidasi->dientri_oleh);
        $this->assertSame($analis->id, $divalidasi->divalidasi_oleh);
    }

    public function test_order_yang_belum_ada_hasilnya_tidak_bisa_divalidasi(): void
    {
        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]);
        $order = app(PemesananLab::class)
            ->pesan($kunjungan, [$this->darahRutin->id], User::factory()->create());

        $this->expectException(RuntimeException::class);

        app(PemeriksaanLaboratorium::class)->validasi($order, User::factory()->create());
    }

    public function test_hasil_tervalidasi_tidak_bisa_dientri_ulang_lewat_jalur_biasa(): void
    {
        [$kunjungan, $order, $analis] = $this->orderBerhasil();
        app(PemeriksaanLaboratorium::class)->validasi($order, $analis);

        $this->expectException(RuntimeException::class);

        app(PemeriksaanLaboratorium::class)
            ->entriHasil($order->refresh(), [$this->hemoglobin->id => 9.0], $analis);
    }

    public function test_koreksi_hasil_tervalidasi_wajib_beralasan(): void
    {
        [$kunjungan, $order, $analis] = $this->orderBerhasil();
        app(PemeriksaanLaboratorium::class)->validasi($order, $analis);

        $this->expectException(ValidationException::class);

        app(PemeriksaanLaboratorium::class)
            ->koreksi($order->refresh(), [$this->hemoglobin->id => 9.0], $analis, '   ');
    }

    public function test_koreksi_mengubah_nilai_dan_tercatat_di_audit_log(): void
    {
        [$kunjungan, $order, $analis] = $this->orderBerhasil();
        app(PemeriksaanLaboratorium::class)->validasi($order, $analis);

        app(PemeriksaanLaboratorium::class)->koreksi(
            $order->refresh(), [$this->hemoglobin->id => 9.0], $analis, 'Salah baca alat'
        );

        $baris = HasilLab::where('parameter_lab_id', $this->hemoglobin->id)->first();

        $this->assertSame(9.0, (float) $baris->nilai);
        $this->assertSame(\App\Enums\PenandaHasil::Rendah, $baris->penanda);
        $this->assertDatabaseHas('audit_logs', ['alasan' => 'Salah baca alat']);
    }

    public function test_kunjungan_tidak_bisa_diselesaikan_saat_hasil_lab_belum_divalidasi(): void
    {
        [$kunjungan, $order, $analis] = $this->orderBerhasil();

        $dokter = User::factory()->create(['dokter_id' => $kunjungan->dokter_id]);
        $klinis = app(PemeriksaanKlinis::class);

        $klinis->catatSoap($kunjungan, [
            'subjective' => 'Lemas', 'objective' => 'Konjungtiva pucat',
            'assessment' => 'Anemia', 'plan' => 'Terapi besi',
        ], $dokter);
        $klinis->tambahDiagnosa($kunjungan, Icd10::factory()->create()->id, JenisDiagnosa::Primer);

        try {
            $klinis->selesaikan($kunjungan->refresh(), $dokter);
            $this->fail('Kunjungan seharusnya ditolak karena hasil lab belum divalidasi.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString($order->no_order, $e->getMessage());
        }
    }

    public function test_kunjungan_bisa_diselesaikan_setelah_seluruh_order_divalidasi(): void
    {
        [$kunjungan, $order, $analis] = $this->orderBerhasil();
        app(PemeriksaanLaboratorium::class)->validasi($order, $analis);

        $dokter = User::factory()->create(['dokter_id' => $kunjungan->dokter_id]);
        $klinis = app(PemeriksaanKlinis::class);

        $klinis->catatSoap($kunjungan, [
            'subjective' => 'Lemas', 'objective' => 'Konjungtiva pucat',
            'assessment' => 'Anemia', 'plan' => 'Terapi besi',
        ], $dokter);
        $klinis->tambahDiagnosa($kunjungan, Icd10::factory()->create()->id, JenisDiagnosa::Primer);

        $selesai = $klinis->selesaikan($kunjungan->refresh(), $dokter);

        $this->assertSame(\App\Enums\StatusKunjungan::Selesai, $selesai->status);
    }

    public function test_order_yang_dibatalkan_tidak_menahan_penyelesaian_kunjungan(): void
    {
        $kunjungan = Kunjungan::factory()->create(['penjamin_id' => $this->umum->id]);
        $order = app(PemesananLab::class)
            ->pesan($kunjungan, [$this->darahRutin->id], User::factory()->create());

        app(PemesananLab::class)->batalkan($order, User::factory()->create(), 'Salah pesan');

        $dokter = User::factory()->create(['dokter_id' => $kunjungan->dokter_id]);
        $klinis = app(PemeriksaanKlinis::class);

        $klinis->catatSoap($kunjungan, [
            'subjective' => 'a', 'objective' => 'b', 'assessment' => 'c', 'plan' => 'd',
        ], $dokter);
        $klinis->tambahDiagnosa($kunjungan, Icd10::factory()->create()->id, JenisDiagnosa::Primer);

        $this->assertSame(
            \App\Enums\StatusKunjungan::Selesai,
            $klinis->selesaikan($kunjungan->refresh(), $dokter)->status
        );
    }
}
```

- [ ] **Step 2: Tulis test pembebanan tagihan yang gagal**

Buat `tests/Feature/TagihanLabTest.php` dengan tiga test:

```php
    public function test_biaya_lab_masuk_ke_tagihan_saat_kunjungan_diselesaikan(): void
    {
        // Order lab 75.000 + konsultasi 50.000 = 125.000
        // Susun kunjungan lengkap seperti pada ValidasiHasilLabTest, tambahkan
        // satu tindakan konsultasi bertarif 50.000, validasi lab, lalu selesaikan.
        $this->assertSame(125000, (int) $kunjungan->refresh()->tagihan->total);
        $this->assertDatabaseHas('tagihan_detail', [
            'sumber_tipe' => \App\Models\OrderLabDetail::class,
            'deskripsi' => 'Darah Rutin',
            'tarif_satuan' => 75000,
        ]);
    }

    public function test_order_yang_dibatalkan_sebelum_sampel_tidak_ditagihkan(): void
    {
        // Pesan lab, batalkan sebelum ambil sampel, lalu selesaikan kunjungan.
        $this->assertSame(50000, (int) $kunjungan->refresh()->tagihan->total);
        $this->assertSame(
            0,
            $kunjungan->tagihan->detail()->where('sumber_tipe', \App\Models\OrderLabDetail::class)->count()
        );
    }

    public function test_order_yang_dibatalkan_setelah_sampel_tetap_ditagihkan(): void
    {
        // Pesan lab, ambil sampel, batalkan, lalu selesaikan kunjungan.
        // Bahan dan waktu kerjanya sudah terpakai, jadi tetap ditagihkan (aturan 46).
        $this->assertSame(125000, (int) $kunjungan->refresh()->tagihan->total);
    }
```

Susun ketiganya utuh memakai `ValidasiHasilLabTest::setUp()` sebagai kerangka, ditambah
`Tindakan` bertarif 50.000 dan `TindakanPelayanan::tambah()` sebelum kunjungan
diselesaikan. Ketiga test harus benar-benar berjalan — komentar di atas hanya penanda
tempat, bukan pengganti kode.

- [ ] **Step 3: Jalankan kedua test untuk memastikan gagal**

Run: `php artisan test --filter="ValidasiHasilLabTest|TagihanLabTest"`
Diharapkan: FAIL — method `validasi` belum ada.

- [ ] **Step 4: Tambahkan validasi dan koreksi**

Di `app/Services/PemeriksaanLaboratorium.php`, tambahkan `use App\Support\KonteksAudit;` lalu method:

```php
    public function validasi(OrderLab $order, User $analis): OrderLab
    {
        if ($order->status !== StatusOrderLab::HasilDientri) {
            throw new RuntimeException(
                "Order {$order->no_order} berstatus {$order->status->label()} dan belum bisa divalidasi."
            );
        }

        return DB::transaction(function () use ($order, $analis) {
            $terkunci = OrderLab::whereKey($order->id)->lockForUpdate()->first();

            if ($terkunci->status !== StatusOrderLab::HasilDientri) {
                throw new RuntimeException('Order ini baru saja divalidasi petugas lain.');
            }

            // Aturan 43: pelaku entri dan pelaku validasi boleh sama, tetapi
            // keduanya tetap tercatat sehingga bisa ditelusuri bila ada masalah.
            $terkunci->update([
                'status' => StatusOrderLab::Divalidasi,
                'waktu_validasi' => now(),
                'divalidasi_oleh' => $analis->id,
            ]);

            return $terkunci->refresh();
        });
    }

    /**
     * Mengubah hasil yang sudah divalidasi. Wajib beralasan dan berjejak (aturan 44).
     *
     * @param  array<int, mixed>  $nilai
     */
    public function koreksi(OrderLab $order, array $nilai, User $analis, string $alasan): OrderLab
    {
        if (trim($alasan) === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan koreksi hasil laboratorium wajib diisi.',
            ]);
        }

        if ($order->status !== StatusOrderLab::Divalidasi) {
            throw new RuntimeException(
                'Koreksi hanya berlaku untuk hasil yang sudah divalidasi. Hasil yang belum divalidasi cukup dientri ulang.'
            );
        }

        $jenisKelamin = $order->kunjungan->pasien->jenis_kelamin;
        $tervalidasi = $this->validasiNilai($nilai);

        return KonteksAudit::dengan(trim($alasan), function () use ($order, $tervalidasi, $analis, $jenisKelamin) {
            return DB::transaction(function () use ($order, $tervalidasi, $analis, $jenisKelamin) {
                foreach ($tervalidasi as $parameterId => $angka) {
                    $parameter = ParameterLab::findOrFail($parameterId);

                    $detail = $order->detail()
                        ->where('pemeriksaan_lab_id', $parameter->pemeriksaan_lab_id)
                        ->firstOrFail();

                    HasilLab::updateOrCreate([
                        'order_lab_detail_id' => $detail->id,
                        'parameter_lab_id' => $parameter->id,
                    ], [
                        'nilai' => $angka,
                        'penanda' => $this->penandaNilai->untuk($parameter, $angka, $jenisKelamin),
                    ]);
                }

                $order->update([
                    'waktu_validasi' => now(),
                    'divalidasi_oleh' => $analis->id,
                ]);

                return $order->refresh();
            });
        });
    }
```

Ubah pula penjaga `entriHasil()` agar order tervalidasi ditolak — ini sudah terjadi
sendiri karena `StatusOrderLab::Divalidasi->bisaEntriHasil()` bernilai false.

- [ ] **Step 5: Tambahkan terbacaDokter pada OrderLab**

```php
    /**
     * Aturan 42: hasil baru boleh dibaca dokter setelah divalidasi.
     */
    public function terbacaDokter(): bool
    {
        return $this->status === StatusOrderLab::Divalidasi;
    }
```

- [ ] **Step 6: Kunci penyelesaian kunjungan**

Di `app/Services/PemeriksaanKlinis.php` pada `selesaikan()`, sisipkan sebelum transaksi:

```php
        // Aturan 37: kunjungan ditutup setelah hasil keluar, supaya diagnosanya
        // benar-benar berdasar hasil — bukan ditulis sambil menunggu.
        $orderMenunggu = $kunjungan->orderLab()->belumSelesai()->first();

        if ($orderMenunggu !== null) {
            throw new RuntimeException(
                "Kunjungan belum bisa diselesaikan: hasil order {$orderMenunggu->no_order} belum divalidasi."
            );
        }
```

- [ ] **Step 7: Masukkan baris laboratorium ke tagihan**

Di `app/Services/PenyusunTagihan.php` pada `susun()`, setelah perulangan tindakan tambahkan:

```php
            // Order yang dibatalkan sebelum sampel diambil tidak ditagihkan
            // (aturan 45); yang dibatalkan setelah sampel tetap ditagihkan karena
            // bahan dan waktu kerjanya sudah terpakai (aturan 46).
            $orderLab = $kunjungan->orderLab()
                ->where(function ($q) {
                    $q->where('status', '!=', StatusOrderLab::Batal->value)
                        ->orWhereNotNull('waktu_sampel');
                })
                ->with('detail.pemeriksaan')
                ->get();

            foreach ($orderLab as $order) {
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

lalu ganti perhitungan totalnya menjadi `return $this->hitungUlang($tagihan);` supaya
angkanya tetap dihitung dari rinciannya, bukan dari penjumlahan terpisah. Tambahkan
`use App\Enums\StatusOrderLab;`.

- [ ] **Step 8: Jalankan seluruh test**

Run: `php artisan test`
Diharapkan: seluruh test lulus. Bila `AlurRawatJalanTest` atau `AlurFarmasiTest` gagal,
periksa apakah kunjungannya punya order laboratorium — seharusnya tidak, sehingga
penjaga baru tidak aktif.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: tambah validasi hasil lab, koreksi berjejak, dan penguncian penyelesaian kunjungan"
```

---

### Task 8: Hak akses analis

**Files:**
- Create: `app/Policies/OrderLabPolicy.php`
- Test: `tests/Feature/HakAksesLabTest.php`

**Interfaces:**
- Consumes: `Peran::Analis` (Task 3), `OrderLab` (Task 5)
- Produces: `OrderLabPolicy::kerjakan(User $user, OrderLab $order): bool`, `OrderLabPolicy::validasi(User $user, OrderLab $order): bool`, `OrderLabPolicy::pesan(User $user): bool`, `OrderLabPolicy::baca(User $user, OrderLab $order): bool`

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/HakAksesLabTest.php` dengan test berikut, memakai pola
`penggunaBerperan()` seperti pada `tests/Feature/HakAksesApotekTest.php`:

```php
    public function test_analis_boleh_mengerjakan_dan_memvalidasi_order(): void
    {
        $order = $this->order();
        $analis = $this->penggunaBerperan(Peran::Analis->value);

        $this->assertTrue(Gate::forUser($analis)->allows('kerjakan', $order));
        $this->assertTrue(Gate::forUser($analis)->allows('validasi', $order));
    }

    public function test_dokter_tidak_bisa_mengentri_hasil_lab(): void
    {
        $dokter = $this->penggunaBerperan(Peran::Dokter->value);

        $this->assertFalse(Gate::forUser($dokter)->allows('kerjakan', $this->order()));
    }

    public function test_hanya_dokter_yang_boleh_memesan_lab(): void
    {
        $this->assertTrue(Gate::forUser($this->penggunaBerperan(Peran::Dokter->value))->allows('pesan', OrderLab::class));
        $this->assertFalse(Gate::forUser($this->penggunaBerperan(Peran::Analis->value))->allows('pesan', OrderLab::class));
    }

    public function test_analis_tidak_bisa_mengubah_rekam_medis(): void
    {
        $pemeriksaan = Pemeriksaan::factory()->create();
        $analis = $this->penggunaBerperan(Peran::Analis->value);

        $this->assertFalse(Gate::forUser($analis)->allows('ubah', $pemeriksaan));
    }

    public function test_analis_tidak_bisa_menyiapkan_resep(): void
    {
        $resep = app(PenulisanResep::class)->tulis(
            Kunjungan::factory()->create(),
            [['obat_id' => Obat::factory()->create()->id, 'jumlah' => 5, 'aturan_pakai' => '2x1']],
            User::factory()->create()
        );

        $this->assertFalse(Gate::forUser($this->penggunaBerperan(Peran::Analis->value))->allows('siapkan', $resep));
    }

    public function test_analis_tidak_bisa_memproses_pembayaran(): void
    {
        $this->assertFalse(
            Gate::forUser($this->penggunaBerperan(Peran::Analis->value))->allows('proses', Tagihan::factory()->create())
        );
    }

    public function test_hasil_belum_divalidasi_tidak_boleh_dibaca_dokter(): void
    {
        $order = $this->order();
        $dokter = $this->penggunaBerperan(Peran::Dokter->value, ['dokter_id' => $order->kunjungan->dokter_id]);

        $this->assertFalse(Gate::forUser($dokter)->allows('baca', $order));
    }
```

Lengkapi berkasnya dengan `setUp()` pembuat peran, helper `penggunaBerperan()`, dan
helper `order()` yang membuat satu `OrderLab` lewat `PemesananLab` seperti pada
`PemesananLabTest`.

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=HakAksesLabTest`
Diharapkan: FAIL — kemampuan `kerjakan` belum terdaftar sehingga Gate menolak semuanya.

- [ ] **Step 3: Tulis OrderLabPolicy**

`app/Policies/OrderLabPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Models\OrderLab;
use App\Models\User;

class OrderLabPolicy
{
    public function pesan(User $user): bool
    {
        return $user->hasRole(Peran::Dokter->value);
    }

    public function kerjakan(User $user, OrderLab $order): bool
    {
        return $user->hasRole(Peran::Analis->value) && ! $order->status->selesai();
    }

    public function validasi(User $user, OrderLab $order): bool
    {
        return $user->hasRole(Peran::Analis->value);
    }

    /**
     * Aturan 42: dokter hanya boleh membaca hasil yang sudah divalidasi.
     */
    public function baca(User $user, OrderLab $order): bool
    {
        return $user->hasAnyRole([Peran::Dokter->value, Peran::Analis->value, Peran::RekamMedis->value])
            && $order->terbacaDokter();
    }
}
```

- [ ] **Step 4: Jalankan test sampai lulus**

Run: `php artisan test --filter=HakAksesLabTest`
Diharapkan: PASS, 7 test.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: tambah hak akses analis dan pembatasan pembacaan hasil lab"
```

---

### Task 9: Layar laboratorium

**Files:**
- Create: `app/Livewire/Lab/{AntreanOrder,LayarSampel,LayarEntriHasil,LayarValidasi}.php`, `app/Livewire/Master/DaftarPemeriksaanLab.php`, view masing-masing
- Modify: `app/Livewire/Poli/FormSoap.php`, `resources/views/livewire/poli/form-soap.blade.php`, `routes/web.php`
- Test: `tests/Feature/LayarLabTest.php`

**Interfaces:**
- Consumes: seluruh service Task 5–7, `OrderLabPolicy` (Task 8)
- Produces: rute `lab.antrean`, `lab.sampel`, `lab.hasil`, `lab.validasi` di belakang `role:analis`; `master.pemeriksaan-lab` di belakang `role:admin`. `FormSoap` mendapat aksi `pesanLab()` dan menampilkan hasil yang sudah divalidasi.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/LayarLabTest.php` dengan test berikut, memakai pola
`tests/Feature/LayarApotekTest.php`:

```php
    public function test_antrean_menampilkan_order_yang_belum_dikerjakan(): void
    public function test_analis_mengambil_sampel_lewat_layar(): void
    public function test_analis_mengentri_hasil_lewat_layar_dan_penandanya_muncul(): void
    public function test_nilai_bukan_angka_menampilkan_pesan_di_layar_bukan_error(): void
    public function test_analis_memvalidasi_lewat_layar(): void
    public function test_dokter_memesan_lab_dari_layar_soap(): void
    public function test_dokter_tidak_bisa_membuka_layar_analis(): void
    public function test_analis_tidak_bisa_membuka_layar_kasir(): void
```

Tulis kedelapannya utuh — bukan sekadar tanda tangan method — dengan susunan data
seperti pada `ValidasiHasilLabTest::setUp()`.

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=LayarLabTest`
Diharapkan: FAIL dengan "Unable to find component: [App\Livewire\Lab\AntreanOrder]".

- [ ] **Step 3: Tulis komponen analis**

`app/Livewire/Lab/AntreanOrder.php` menampilkan `OrderLab::with('kunjungan.pasien', 'detail.pemeriksaan')->where('status', $this->status)->latest('id')->paginate(15)` dengan pemilih status seperti `Apotek\AntreanResep`.

`app/Livewire/Lab/LayarSampel.php` menerima `OrderLab`, memanggil `PemeriksaanLaboratorium::ambilSampel()` di dalam `try/catch` yang memetakan `RuntimeException` ke kunci error `sampel`.

`app/Livewire/Lab/LayarEntriHasil.php`:

```php
<?php

namespace App\Livewire\Lab;

use App\Models\OrderLab;
use App\Services\PemeriksaanLaboratorium;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use RuntimeException;

class LayarEntriHasil extends Component
{
    use AuthorizesRequests;

    public OrderLab $order;

    /** @var array<int, string> parameter_lab_id => nilai */
    public array $nilai = [];

    public string $alasanKoreksi = '';

    public function mount(OrderLab $order): void
    {
        $this->authorize('kerjakan', $order);

        $this->order = $order;

        foreach ($order->detail as $detail) {
            foreach ($detail->pemeriksaan->parameter as $parameter) {
                $tersimpan = $detail->hasil->firstWhere('parameter_lab_id', $parameter->id);
                $this->nilai[$parameter->id] = (string) ($tersimpan->nilai ?? '');
            }
        }
    }

    public function simpan(PemeriksaanLaboratorium $layanan): void
    {
        $terisi = array_filter($this->nilai, fn ($v) => trim((string) $v) !== '');

        try {
            $layanan->entriHasil($this->order, $terisi, auth()->user());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kolom => $pesan) {
                $this->addError($kolom, $pesan[0]);
            }

            return;
        } catch (RuntimeException $e) {
            $this->addError('entri', $e->getMessage());

            return;
        }

        $this->order->refresh();
        session()->flash('sukses', 'Hasil tersimpan.');
    }

    public function render()
    {
        return view('livewire.lab.layar-entri-hasil', [
            'jenisKelamin' => $this->order->kunjungan->pasien->jenis_kelamin,
        ]);
    }
}
```

`app/Livewire/Lab/LayarValidasi.php` menampilkan hasil beserta penandanya dan memanggil
`PemeriksaanLaboratorium::validasi()`, dengan aksi koreksi yang menyertakan isian alasan.

`app/Livewire/Master/DaftarPemeriksaanLab.php` mengelola master pemeriksaan, parameter,
dan rujukannya mengikuti pola `Master\DaftarPoli`.

- [ ] **Step 4: Tulis view**

`resources/views/livewire/lab/layar-entri-hasil.blade.php` menampilkan satu baris per
parameter: nama, satuan, kolom isian `wire:model="nilai.{{ $parameter->id }}"`, dan
**rentang rujukan sesuai jenis kelamin pasien tepat di sebelahnya**, sehingga analis
melihat kewajaran nilainya saat mengetik — bukan setelah tersimpan.

`antrean-order.blade.php`, `layar-sampel.blade.php`, `layar-validasi.blade.php`, dan
`master/daftar-pemeriksaan-lab.blade.php` mengikuti pola tabel dan form yang sama dengan
layar apotek.

- [ ] **Step 5: Sambungkan ke layar dokter**

Di `app/Livewire/Poli/FormSoap.php`, tambahkan properti `public array $pemeriksaanLabDipilih = [];` dan method:

```php
    public function pesanLab(PemesananLab $layanan): void
    {
        $this->jalankan(fn () => $layanan->pesan(
            $this->kunjungan, $this->pemeriksaanLabDipilih, auth()->user()
        ));
    }
```

Nama method pada komponen adalah `pesanLab()` agar tidak bertabrakan dengan aksi lain
di layar SOAP, sedangkan method pada service bernama `pesan()`.

Pada `render()`, tambahkan `'daftarPemeriksaanLab' => PemeriksaanLab::where('aktif', true)->orderBy('nama')->get()` dan `'hasilLab' => $this->kunjungan->orderLab()->with('detail.hasil.parameter', 'detail.pemeriksaan')->get()`.

Pada viewnya, tambahkan satu kartu berisi pemilihan pemeriksaan laboratorium dan satu
kartu berisi hasil yang **sudah divalidasi saja**, dengan nilai abnormal ditandai warna.

- [ ] **Step 6: Daftarkan rute**

```php
Route::middleware('role:analis')->group(function () {
    Route::get('/lab/antrean', AntreanOrder::class)->name('lab.antrean');
    Route::get('/lab/sampel/{order}', LayarSampel::class)->name('lab.sampel');
    Route::get('/lab/hasil/{order}', LayarEntriHasil::class)->name('lab.hasil');
    Route::get('/lab/validasi/{order}', LayarValidasi::class)->name('lab.validasi');
});
```

dan `Route::get('/master/pemeriksaan-lab', DaftarPemeriksaanLab::class)->name('master.pemeriksaan-lab');` ke dalam grup `role:admin`.

- [ ] **Step 7: Jalankan test sampai lulus**

Run: `php artisan test --filter=LayarLabTest`
Diharapkan: PASS, 8 test.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: tambah layar laboratorium untuk analis dan pemesanan lab dari layar dokter"
```

---

### Task 10: Seeder laboratorium dan verifikasi menyeluruh

**Files:**
- Create: `database/seeders/LaboratoriumSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`, `database/seeders/PenggunaSeeder.php`, `database/seeders/KunjunganDummySeeder.php`, `README.md`
- Test: `tests/Feature/AlurLabTest.php`

**Interfaces:**
- Consumes: seluruh service Task 4–8
- Produces: `php artisan migrate:fresh --seed` menghasilkan laboratorium siap demo, dan satu test alur menyeluruh yang membuktikan kriteria selesai nomor 3 dan 4.

- [ ] **Step 1: Tulis test alur menyeluruh**

Buat `tests/Feature/AlurLabTest.php` dengan dua test yang ditulis utuh:

```php
    public function test_alur_lengkap_dari_dokter_memesan_sampai_kunjungan_ditutup(): void
    {
        // pasien -> kunjungan -> vital -> soap -> pesan lab -> ambil sampel ->
        // entri hasil -> validasi -> diagnosa -> selesaikan -> bayar
        // assert: status kunjungan Selesai, tagihan memuat baris lab dan tindakan,
        //         hasil terbaca dokter, penanda hasil benar
    }

    public function test_nilai_abnormal_tertandai_benar_untuk_pasien_pria_dan_wanita(): void
    {
        // dua pasien berbeda jenis kelamin dengan nilai hemoglobin sama
        // assert: satu Normal, satu Tinggi
    }
```

Susun keduanya memakai `tests/Feature/AlurFarmasiTest.php` sebagai kerangka. Komentar di
atas adalah penanda urutan langkah, bukan pengganti kode — tulis seluruh pemanggilannya.

- [ ] **Step 2: Jalankan dan perbaiki sampai lulus**

Run: `php artisan test --filter=AlurLabTest`
Diharapkan: PASS, 2 test. Kegagalan di sini menandakan cacat integrasi antar tugas.

- [ ] **Step 3: Tulis LaboratoriumSeeder**

`database/seeders/LaboratoriumSeeder.php` mengisi sepuluh pemeriksaan beserta parameter
dan rujukannya:

| Pemeriksaan | Parameter | Rujukan |
|---|---|---|
| Darah Rutin | Hemoglobin (g/dL) | L 13–17, P 12–15 |
| | Leukosit (10³/µL) | semua 4–11 |
| | Trombosit (10³/µL) | semua 150–450 |
| | Hematokrit (%) | L 40–52, P 36–47 |
| Gula Darah Sewaktu | GDS (mg/dL) | semua 70–140 |
| Gula Darah Puasa | GDP (mg/dL) | semua 70–110 |
| Kolesterol Total | Kolesterol (mg/dL) | semua 0–200 |
| Asam Urat | Asam urat (mg/dL) | L 3,4–7,0; P 2,4–5,7 |
| Fungsi Ginjal | Ureum (mg/dL) | semua 15–40 |
| | Kreatinin (mg/dL) | L 0,7–1,3; P 0,6–1,1 |
| Fungsi Hati | SGOT (U/L) | L 0–37, P 0–31 |
| | SGPT (U/L) | L 0–41, P 0–31 |
| Urinalisis | Berat jenis | semua 1,005–1,030 |
| | pH urine | semua 4,6–8,0 |
| Widal | Titer O | semua 0–80 |
| Tes Kehamilan | Beta HCG (mIU/mL) | semua 0–5 |

Tarif kedua penjamin: umum Rp 35.000–150.000 menurut kategori, BPJS sekitar 70%
dibulatkan ke ribuan, ditulis ke tabel `tarif` dengan `jenis_layanan` `lab`.

- [ ] **Step 4: Tambahkan pengguna analis**

Di `database/seeders/PenggunaSeeder.php`, tambahkan satu baris:

```php
            [Peran::Analis, 'Analis Laboratorium', 'analis@rs.test'],
```

- [ ] **Step 5: Sambungkan ke data dummy kunjungan**

Di `database/seeders/KunjunganDummySeeder.php`, untuk sebagian kunjungan yang akan
diselesaikan, pesan satu order laboratorium lalu jalankan sampai divalidasi **sebelum**
kunjungan diselesaikan — aturan 37 akan menolak bila urutannya terbalik, dan penolakan
itu justru bukti aturannya bekerja.

Sisakan beberapa order pada tiap status supaya keempat layar analis punya isi:
5 order berstatus `dipesan`, 3 `sampel_diambil`, 3 `hasil_dientri`. Order pada kunjungan
yang belum diselesaikan aman dibiarkan menggantung karena kunjungannya memang masih terbuka.

Tambahkan `LaboratoriumSeeder::class` ke `DatabaseSeeder` **sebelum** `KunjunganDummySeeder::class`.

- [ ] **Step 6: Jalankan seluruh alur dari nol**

```bash
php artisan migrate:fresh --seed
mysql -u irvan -p1 simrs -e "
SELECT (SELECT COUNT(*) FROM pemeriksaan_lab) AS pemeriksaan,
       (SELECT COUNT(*) FROM parameter_lab) AS parameter,
       (SELECT COUNT(*) FROM rujukan_lab) AS rujukan,
       (SELECT COUNT(*) FROM order_lab) AS \`order\`,
       (SELECT COUNT(*) FROM hasil_lab) AS hasil,
       (SELECT COUNT(*) FROM hasil_lab WHERE penanda <> 'normal') AS abnormal,
       (SELECT COUNT(*) FROM tarif WHERE jenis_layanan='lab') AS tarif_lab;"
```

Diharapkan: pemeriksaan 10, parameter ≥ 16, rujukan ≥ 16, order > 10, hasil > 0,
abnormal > 0, tarif_lab 20.

- [ ] **Step 7: Jalankan seluruh test**

Run: `php artisan test`
Diharapkan: seluruh berkas test PASS, tanpa satu pun yang di-skip.

- [ ] **Step 8: Telusuri manual**

```bash
php artisan serve
```

1. Masuk sebagai `dokter@rs.test`, buka satu kunjungan aktif, pesan pemeriksaan laboratorium.
2. Coba selesaikan kunjungannya — harus ditolak dengan pesan menyebut nomor order.
3. Masuk sebagai `analis@rs.test`, ambil sampel, entri hasil, perhatikan rujukan tampil di sebelah kolom isian, lalu validasi.
4. Kembali sebagai dokter, pastikan hasilnya kini terbaca dan nilai abnormalnya tertandai, lalu selesaikan kunjungan.
5. Masuk sebagai `kasir@rs.test`, pastikan tagihannya memuat baris pemeriksaan laboratorium.

- [ ] **Step 9: Perbarui README**

Tambahkan modul Laboratorium ke tabel cakupan, akun `analis@rs.test` ke tabel akun demo,
dan satu paragraf tentang alur lab beserta penguncian penyelesaian kunjungan.

- [ ] **Step 10: Commit dan dorong**

```bash
git add -A
git commit -m "feat: tambah seeder laboratorium dan test alur lab menyeluruh"
git push
```

---

## Ringkasan Cakupan

| Aturan (spec Fase 3 bagian 9) | Tugas |
|---|---|
| 35 Order wajib memuat pemeriksaan | Task 5 |
| 36 Tarif disalin saat order; biaya masuk saat kunjungan selesai | Task 5, 7 |
| 37 Kunjungan terkunci sampai hasil divalidasi | Task 7 |
| 38 Hasil hanya setelah sampel diambil | Task 3, 6 |
| 39 Nilai wajib angka | Task 6 |
| 40 Penanda dihitung sistem | Task 4, 6 |
| 41 Jatuh tempo ke rujukan `semua`, lalu kosong | Task 4 |
| 42 Hasil terbaca dokter setelah divalidasi | Task 7, 8 |
| 43 Validasi boleh oleh pengentri, kedua pelaku tercatat | Task 7 |
| 44 Koreksi hasil tervalidasi wajib beralasan | Task 7 |
| 45 Order batal sebelum sampel tidak ditagihkan | Task 7 |
| 46 Order batal setelah sampel tetap ditagihkan | Task 7 |
