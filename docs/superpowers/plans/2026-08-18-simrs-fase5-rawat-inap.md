# Rencana Implementasi — Fase 5 Rawat Inap

Spec: [`../specs/2026-08-18-simrs-fase5-rawat-inap-design.md`](../specs/2026-08-18-simrs-fase5-rawat-inap-design.md)

## Global Constraints

- **TDD tanpa kecuali.** Test ditulis lebih dulu, dijalankan sampai **gagal**,
  baru implementasinya. Test yang lulus di percobaan pertama berarti ia tidak
  menguji apa pun yang baru.
- **Jalankan test lalu baca hasilnya sebelum commit.** Jangan merangkai test dan
  commit dengan `&&` dalam satu perintah.
- **Periksa import ganda setiap kali menyunting berkas yang sudah ada** —
  `grep "^use " berkas | sort | uniq -d` harus kosong. Import ganda menghasilkan
  "Premature end of PHP process" yang tidak menyebut penyebabnya sama sekali.
- **Uang selalu bilangan bulat rupiah.** Tidak pernah `decimal`, tidak pernah `float`.
- **`max()+1` terlarang untuk penomoran.** Pakai `PencatatNomor`.
- **Sapu seluruh rute di aplikasi yang benar-benar berjalan** setelah tugas layar.
  Cara ini yang menemukan bug 500 pada layar ubah pasien di Fase 1 dan layar
  ekspertise yang tak terjangkau di Fase 4 — keduanya lolos dari ratusan test.
- Seluruh nama tabel, kolom, kelas, rute, label, dan pesan validasi dalam bahasa Indonesia.
- Nama rumah sakit nyata tidak boleh muncul di mana pun. Pakai "RS Sampel".

## Pola yang Sudah Ada — Baca Sebelum Menulis

| Kebutuhan | Contoh yang sudah ada |
|---|---|
| Penomoran aman balapan | `app/Services/NomorDokumen.php`, `PencatatNomor.php` |
| Pencarian tarif + cadangan UMUM | `app/Services/PencariTarif.php` |
| Koreksi beralasan berjejak | `app/Services/PenulisanEkspertise.php::koreksi()` |
| Penguncian baris di dalam transaksi | `app/Services/PelaksanaanRadiologi.php::kerjakan()` |
| Periksa ulang status dari basis data, bukan objek pemanggil | `app/Services/PenulisanEkspertise.php::tulis()` |
| Baris tagihan polimorfik | `app/Services/PenyusunTagihan.php` |
| Policy peran + layar Livewire | `app/Policies/OrderRadiologiPolicy.php`, `app/Livewire/Radiologi/` |

## Struktur Berkas

```
app/Enums/            StatusRawatInap, CaraPulang, JenisLayanan::Kamar,
                      StatusKunjungan::DalamPerawatan
app/Models/           Ruang, KelasKamar, Bed, RawatInap, OkupansiBed,
                      CatatanPerkembangan
app/Services/         PerintahRawatInap, PenempatanBed, CatatanHarian,
                      PemulanganPasien, PenghitungBiayaKamar
app/Policies/         RawatInapPolicy
app/Livewire/RawatInap/   PapanBed, LayarPenempatan, LayarPerawatan, LayarPemulangan
app/Livewire/Master/      DaftarRuangBed
```

---

### Task 1: Master ruang, kelas, bed, dan tarif kamar

**Files:**
- Create: `app/Enums/StatusRawatInap.php`, `app/Enums/CaraPulang.php`, migration `create_master_rawat_inap_tables`, model `Ruang`, `KelasKamar`, `Bed`, factory ketiganya
- Modify: `app/Enums/JenisLayanan.php`, `app/Enums/StatusKunjungan.php`, `app/Models/Tarif.php`
- Test: `tests/Unit/EnumRawatInapTest.php`, `tests/Feature/MasterRawatInapTest.php`

**Interfaces:**
- Produces:
  - `StatusRawatInap` — `Dirawat`, `Pulang`, `Batal`; `aktif(): bool`, `label(): string`
  - `CaraPulang` — `Sembuh`, `Membaik`, `RujukKeluar`, `PulangPaksa`, `Meninggal`; `label()`
  - `JenisLayanan::Kamar` bernilai `'kamar'`
  - `StatusKunjungan::DalamPerawatan` bernilai `'dalam_perawatan'`, termasuk status **aktif**
  - Model `Ruang`, `KelasKamar`, `Bed` (`scopeKosong()`, `terisi(): bool`)

Memenuhi separuh aturan 62 (struktur bednya).

**Test yang ditulis:**

```
test_status_dirawat_termasuk_aktif
test_status_pulang_dan_batal_tidak_aktif
test_kunjungan_dalam_perawatan_termasuk_status_aktif
test_kamar_termasuk_jenis_layanan_bertarif
test_cara_pulang_punya_label_yang_bisa_dibaca
test_bed_menyimpan_kelas_dan_ruangnya
test_nomor_bed_ganda_dalam_satu_ruang_ditolak_database
test_tarif_kamar_memakai_tabel_tarif_yang_sama
test_nama_layanan_pada_tarif_menampilkan_nama_kelas
test_bed_kosong_terpilih_oleh_scope_kosong
```

**Migration:**

```php
Schema::create('ruang', function (Blueprint $table) {
    $table->id();
    $table->string('kode', 20)->unique();
    $table->string('nama', 100);
    $table->string('lantai', 30)->nullable();
    $table->boolean('aktif')->default(true);
    $table->timestamps();
});

Schema::create('kelas_kamar', function (Blueprint $table) {
    $table->id();
    $table->string('kode', 20)->unique();
    $table->string('nama', 50);
    $table->unsignedSmallInteger('urutan')->default(1);
    $table->boolean('aktif')->default(true);
    $table->timestamps();
});

Schema::create('bed', function (Blueprint $table) {
    $table->id();
    $table->foreignId('ruang_id')->constrained('ruang');
    $table->foreignId('kelas_kamar_id')->constrained('kelas_kamar');
    $table->string('nomor', 20);
    // Penunjuk penghuni saat ini. Uniknya inilah yang membuat dua pasien
    // mustahil menempati satu bed, bahkan saat dua petugas menekan tombol
    // pada milidetik yang sama (aturan 62).
    $table->foreignId('rawat_inap_id')->nullable()->unique();
    $table->boolean('aktif')->default(true);
    $table->timestamps();
    $table->unique(['ruang_id', 'nomor']);
});
```

`rawat_inap_id` sengaja belum diberi `constrained()`: tabel `rawat_inap` baru
lahir di Task 2. Kuncinya ditambahkan di migrasi Task 2 supaya urutannya sah.

**Steps:**
1. Tulis kedua berkas test.
2. Jalankan → FAIL dengan "Class App\Enums\StatusRawatInap not found".
3. Tulis kedua enum; tambahkan `Kamar` ke `JenisLayanan` beserta labelnya;
   tambahkan `DalamPerawatan` ke `StatusKunjungan` (dan pastikan `aktif()`
   memperlakukannya sebagai aktif — cukup dengan tidak memasukkannya ke daftar
   yang tidak aktif).
4. Tambahkan cabang `JenisLayanan::Kamar => KelasKamar::find(...)?->nama` pada `Tarif::namaLayanan()`.
5. Tulis migration, model, factory.
6. Jalankan test sampai lulus, lalu seluruh suite.
7. Commit: `feat: tambah master ruang, kelas, dan bed rawat inap`.

---

### Task 2: Perintah rawat inap

**Files:**
- Create: migration `create_rawat_inap_table`, `app/Models/RawatInap.php`, `app/Services/PerintahRawatInap.php`
- Modify: `app/Services/NomorDokumen.php`, `app/Models/Kunjungan.php`, `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/PerintahRawatInapTest.php`

**Interfaces:**
- Produces:
  - `PerintahRawatInap::terbitkan(Kunjungan $kunjungan, User $dokter, string $indikasi, KelasKamar $kelasDiminta): RawatInap`
  - `PerintahRawatInap::batalkan(RawatInap $rawatInap, User $petugas, string $alasan): RawatInap`
  - `RawatInap` dengan `scopeAktif()`, `bedSekarang(): ?Bed`, `lamaRawat(): int`
  - `NomorDokumen` menerima jenis `'rawat_inap'` berawalan `RI`
  - `Kunjungan::rawatInap(): HasOne`, `Kunjungan::sedangDirawatInap(): bool`

Memenuhi aturan 59, 60, 61.

**Test yang ditulis:**

```
test_perintah_bernomor_dan_berstatus_dirawat
test_perintah_rawat_inap_wajib_menyertakan_indikasi
test_satu_kunjungan_hanya_boleh_punya_satu_masa_rawat
test_batasan_unik_menolak_masa_rawat_kedua_pada_kunjungan_yang_sama
test_perintah_tidak_bisa_diterbitkan_pada_kunjungan_yang_sudah_selesai
test_kunjungan_berpindah_ke_status_dalam_perawatan
test_kelas_yang_diminta_tercatat
test_pembatalan_wajib_menyertakan_alasan
test_alasan_pembatalan_tercatat_di_audit_log
test_masa_rawat_yang_sudah_batal_tidak_bisa_dibatalkan_lagi
```

**Migration:**

```php
Schema::create('rawat_inap', function (Blueprint $table) {
    $table->id();
    $table->string('no_rawat_inap', 20)->unique();
    // Unik: satu kunjungan satu masa rawat (aturan 60), dijamin basis data
    // dan bukan sekadar pemeriksaan di service.
    $table->foreignId('kunjungan_id')->unique()->constrained('kunjungan')->cascadeOnDelete();
    $table->foreignId('dokter_id')->constrained('dokter');
    $table->foreignId('kelas_diminta_id')->constrained('kelas_kamar');
    $table->string('indikasi', 255);
    $table->string('status', 20)->default('dirawat');
    $table->timestamp('waktu_masuk')->nullable();
    $table->timestamp('waktu_pulang')->nullable();
    $table->string('cara_pulang', 20)->nullable();
    $table->foreignId('diagnosa_akhir_id')->nullable()->constrained('icd10')->nullOnDelete();
    $table->text('ringkasan_pulang')->nullable();
    $table->foreignId('diperintahkan_oleh')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('dipulangkan_oleh')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->index('status');
});

Schema::table('bed', function (Blueprint $table) {
    $table->foreign('rawat_inap_id')->references('id')->on('rawat_inap')->nullOnDelete();
});
```

`waktu_masuk` baru terisi saat pasien benar-benar menempati bed (Task 3);
perintah rawat inap saja belum berarti pasien sudah masuk.

**Steps:**
1. Tulis test → jalankan → FAIL.
2. Tulis migration, model, service.
3. Tambahkan `'rawat_inap' => 'RI'` pada `NomorDokumen::AWALAN`.
4. Tambahkan relasi pada `Kunjungan`; daftarkan `RawatInap` ke `modelTerauditkan()`.
5. Test lulus, seluruh suite hijau, commit:
   `feat: tambah perintah rawat inap beserta indikasi wajib`.

---

### Task 3: Penempatan bed, pindah bed, dan okupansi

**Files:**
- Create: migration `create_okupansi_bed_table`, `app/Models/OkupansiBed.php`, `app/Services/PenempatanBed.php`
- Test: `tests/Feature/PenempatanBedTest.php`

**Interfaces:**
- Produces:
  - `PenempatanBed::tempatkan(RawatInap $rawatInap, Bed $bed, User $petugas): OkupansiBed`
  - `PenempatanBed::pindahkan(RawatInap $rawatInap, Bed $tujuan, User $petugas, string $alasan): OkupansiBed`
  - `PenempatanBed::lepaskan(RawatInap $rawatInap, CarbonInterface $tanggal): void` — dipakai pemulangan
  - `OkupansiBed` dengan `scopeBerjalan()`, `hari(): int`

Memenuhi aturan 62, 63, 64, 65.

**Test yang ditulis:**

```
test_penempatan_mencatat_okupansi_dan_mengunci_bednya
test_penempatan_mengisi_waktu_masuk_masa_rawat
test_bed_terisi_tidak_bisa_ditempati_pasien_lain
test_pesan_penolakan_menyebut_nomor_bed
test_batasan_unik_basis_data_menolak_okupansi_ganda
test_pasien_yang_sudah_di_bed_tidak_bisa_ditempatkan_lagi
test_bed_nonaktif_tidak_bisa_ditempati
test_pindah_bed_menutup_penggal_lama_dan_membuka_penggal_baru
test_pindah_bed_melepaskan_bed_lama_sehingga_bisa_dipakai_pasien_lain
test_pindah_ke_bed_yang_sama_ditolak
test_pindah_bed_wajib_beralasan_dan_tercatat_di_audit_log
test_masa_rawat_yang_sudah_pulang_tidak_bisa_ditempatkan
```

**Migration:**

```php
Schema::create('okupansi_bed', function (Blueprint $table) {
    $table->id();
    $table->foreignId('rawat_inap_id')->constrained('rawat_inap')->cascadeOnDelete();
    $table->foreignId('bed_id')->constrained('bed');
    // Tarif kelas disalin saat penggalnya dibuka, supaya perubahan master
    // tidak mengubah biaya masa rawat yang sedang berjalan.
    $table->unsignedBigInteger('tarif_harian');
    $table->date('mulai');
    $table->date('selesai')->nullable();
    $table->timestamps();
    $table->index(['rawat_inap_id', 'selesai']);
});
```

**Inti `tempatkan()`:**

```php
return DB::transaction(function () use ($rawatInap, $bed, $petugas) {
    $terkunci = Bed::whereKey($bed->id)->lockForUpdate()->firstOrFail();

    if ($terkunci->rawat_inap_id !== null) {
        throw new RuntimeException(
            "Bed {$terkunci->nomor} sudah ditempati pasien lain."
        );
    }
    ...
});
```

Batasan unik pada `bed.rawat_inap_id` tetap ada sebagai jaring pengaman terakhir:
penguncian melindungi dari balapan di dalam satu basis data, batasan uniklah yang
melindungi dari jalur tulis mana pun yang belum terbayang.

**Steps:** test → gagal → migration, model, service → lulus → suite → commit
`feat: tambah penempatan dan pemindahan bed dengan okupansi berpenggal`.

---

### Task 4: Catatan perkembangan harian

**Files:**
- Create: migration `create_catatan_perkembangan_table`, `app/Models/CatatanPerkembangan.php`, `app/Services/CatatanHarian.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/CatatanPerkembanganTest.php`

**Interfaces:**
- Produces:
  - `CatatanHarian::tulis(RawatInap $rawatInap, array $soap, User $penulis): CatatanPerkembangan`
  - `CatatanHarian::koreksi(CatatanPerkembangan $catatan, array $soap, User $penulis, string $alasan): CatatanPerkembangan`

Memenuhi aturan 66, 67.

**Test yang ditulis:**

```
test_catatan_perkembangan_wajib_soap_lengkap
test_catatan_mencatat_penulis_dan_perannya
test_perawat_dan_dokter_sama_sama_boleh_menulis
test_beberapa_catatan_dalam_satu_masa_rawat_tersimpan_berurutan
test_catatan_tidak_bisa_ditulis_pada_masa_rawat_yang_sudah_pulang
test_koreksi_catatan_wajib_beralasan
test_koreksi_mengubah_isi_dan_tercatat_di_audit_log
test_koreksi_tidak_mengubah_penulis_aslinya
```

Aturan 67 dipenuhi dengan `KonteksAudit::dengan()`, dan `CatatanPerkembangan`
didaftarkan ke `modelTerauditkan()` — yang penting berjejak adalah isi
catatannya, bukan cap waktu pada masa rawatnya. Pelajaran Fase 3: mengandalkan
jejak pada model induk menghasilkan nol catatan karena updatenya kadang tidak
mengubah apa pun.

`test_koreksi_tidak_mengubah_penulis_aslinya` menjaga hal yang mudah salah:
koreksi mencatat siapa mengoreksi di audit log, tetapi kolom `ditulis_oleh` tetap
menunjuk penulis pertama. Catatan klinis adalah pernyataan seseorang; menimpa
namanya saat mengoreksi akan memalsukan siapa yang menyatakannya.

**Steps:** test → gagal → implementasi → lulus → suite → commit
`feat: tambah catatan perkembangan harian rawat inap`.

---

### Task 5: Pemulangan pasien

**Files:**
- Create: `app/Services/PemulanganPasien.php`
- Modify: `app/Services/PemeriksaanKlinis.php`
- Test: `tests/Feature/PemulanganPasienTest.php`

**Interfaces:**
- Produces: `PemulanganPasien::pulangkan(RawatInap $rawatInap, User $dokter, int $icd10Id, CaraPulang $cara, ?string $ringkasan = null): RawatInap`
- Mengubah: `PemeriksaanKlinis::selesaikan()` menolak kunjungan yang sedang dirawat inap.

Memenuhi aturan 68, 69, 70, 74.

**Test yang ditulis:**

```
test_pemulangan_wajib_diagnosa_akhir_dan_cara_pulang
test_pemulangan_mencatat_waktu_cara_pulang_dan_pemulangnya
test_pasien_tidak_bisa_pulang_saat_order_lab_belum_selesai
test_pasien_tidak_bisa_pulang_saat_ekspertise_radiologi_belum_ditulis
test_pesan_penolakan_menyebut_nomor_order_yang_ditunggu
test_pemulangan_melepaskan_bed
test_bed_yang_dilepas_bisa_ditempati_pasien_berikutnya
test_pemulangan_menutup_penggal_okupansi_terakhir
test_pemulangan_menyelesaikan_kunjungannya
test_kunjungan_rawat_inap_tidak_bisa_ditutup_lewat_alur_poli
test_masa_rawat_yang_sudah_pulang_tidak_bisa_dipulangkan_lagi
```

Penjaga pada `PemeriksaanKlinis::selesaikan()`:

```php
if ($kunjungan->sedangDirawatInap()) {
    throw new RuntimeException(
        'Kunjungan ini sedang rawat inap. Penutupnya adalah pemulangan pasien, '
        .'bukan penyelesaian kunjungan poli.'
    );
}
```

Pemulangan sendiri **memanggil** `selesaikan()` — tetapi lewat jalur yang sudah
melepas bed dan menandai masa rawat berstatus `pulang`, sehingga penjaga di atas
tidak lagi menyala. Urutannya: lepaskan bed → tandai pulang → selesaikan
kunjungan (yang menyusun tagihan).

**Steps:** test → gagal → implementasi → lulus → suite → commit
`feat: tambah pemulangan pasien beserta pelepasan bed`.

---

### Task 6: Biaya kamar dan tagihan rawat inap

**Files:**
- Create: `app/Services/PenghitungBiayaKamar.php`
- Modify: `app/Services/PenyusunTagihan.php`
- Test: `tests/Feature/BiayaKamarTest.php`, `tests/Feature/TagihanRawatInapTest.php`

**Interfaces:**
- Produces:
  - `PenghitungBiayaKamar::penggal(RawatInap $rawatInap): Collection` — tiap unsur `['okupansi' => OkupansiBed, 'hari' => int, 'subtotal' => int]`
  - `PenghitungBiayaKamar::total(RawatInap $rawatInap): int`
  - `PenyusunTagihan::rincianSementara(Kunjungan $kunjungan): array` — total berjalan tanpa membuat tagihan
- Mengubah: `PenyusunTagihan::susun()` memasukkan baris kamar per penggal.

Memenuhi aturan 71, 72, 73, 75.

**Test yang ditulis:**

```
test_lama_rawat_sehari_saat_masuk_dan_pulang_di_tanggal_sama
test_lama_rawat_tiga_hari_dihitung_dari_selisih_tanggal
test_tarif_kamar_dihitung_per_penggal_menurut_kelasnya
test_pindah_kelas_menghasilkan_dua_baris_tarif_berbeda
test_hari_peralihan_menjadi_milik_penggal_yang_ditinggalkan
test_tiap_penggal_minimal_satu_hari
test_perubahan_master_tarif_tidak_mengubah_masa_rawat_berjalan
test_tagihan_rawat_inap_memuat_kamar_tindakan_obat_lab_dan_radiologi
test_baris_kamar_menyebut_kelas_dan_jumlah_harinya
test_rincian_sementara_menampilkan_total_berjalan
test_rincian_sementara_tidak_membuat_tagihan
```

Baris kamar memakai sumber polimorfik `OkupansiBed`, sehingga rinciannya bisa
ditelusuri balik ke bed dan tanggalnya — sama seperti baris lab menunjuk
`OrderLabDetail`.

Deskripsinya berbentuk `"Kamar Kelas 2 — Melati 03 (3 hari)"` supaya kuitansi
pasien bisa dibaca tanpa membuka sistem.

**Steps:** test → gagal → implementasi → lulus → suite → commit
`feat: tambah perhitungan biaya kamar per penggal okupansi`.

---

### Task 7: Hak akses dan layar rawat inap

**Files:**
- Create: `app/Policies/RawatInapPolicy.php`, `app/Livewire/RawatInap/{PapanBed,LayarPenempatan,LayarPerawatan,LayarPemulangan}.php`, `app/Livewire/Master/DaftarRuangBed.php`, view masing-masing
- Modify: `app/Livewire/Poli/FormSoap.php`, viewnya, `routes/web.php`
- Test: `tests/Feature/HakAksesRawatInapTest.php`, `tests/Feature/LayarRawatInapTest.php`

**Interfaces:**
- `RawatInapPolicy`: `perintahkan` (dokter), `tempatkan` (admisi), `rawat` (perawat|dokter), `pulangkan` (dokter), `lihat` (dokter|perawat|admisi|rekam_medis)
- Rute: `rawat-inap.papan` (admisi|perawat|dokter), `rawat-inap.tempatkan` (admisi),
  `rawat-inap.rawat` (perawat|dokter), `rawat-inap.pulangkan` (dokter),
  `master.ruang-bed` (admin)
- `FormSoap` mendapat aksi `perintahkanRawatInap()`.

**Test yang ditulis:**

```
test_hanya_dokter_yang_boleh_memerintahkan_rawat_inap
test_hanya_admisi_yang_boleh_menempatkan_pasien_di_bed
test_perawat_boleh_menulis_catatan_perkembangan
test_perawat_tidak_bisa_memulangkan_pasien
test_admisi_tidak_bisa_menulis_catatan_perkembangan
test_papan_bed_menampilkan_bed_kosong_dan_terisi
test_admisi_menempatkan_pasien_lewat_layar
test_menempatkan_di_bed_terisi_menampilkan_pesan_di_layar
test_layar_perawatan_menampilkan_catatan_terurut_dan_biaya_berjalan
test_perawat_menulis_catatan_lewat_layar
test_soap_tidak_lengkap_menampilkan_pesan_di_layar
test_dokter_memulangkan_pasien_lewat_layar
test_pemulangan_tanpa_diagnosa_menampilkan_pesan_di_layar
test_dokter_memerintahkan_rawat_inap_dari_layar_soap
test_papan_bed_menautkan_ke_layar_sesuai_kewenangan_pembaca
```

Test terakhir menjaga pelajaran Fase 4: layar yang tidak tertaut dari mana pun
sama saja dengan tidak ada. Papan bed adalah satu-satunya daftar pasien rawat
inap, jadi tautan ke layar perawatan dan pemulangan harus muncul di sana sesuai
peran pembacanya.

**Steps:**
1. Kedua test → gagal.
2. Policy, komponen, view, rute, sambungan ke `FormSoap`.
3. Test lulus, seluruh suite hijau.
4. **Sapu seluruh rute di aplikasi yang berjalan** untuk kesepuluh akun.
5. Commit: `feat: tambah layar rawat inap dan hak aksesnya`.

---

### Task 8: Seeder, alur menyeluruh, dan dokumentasi

**Files:**
- Create: `database/seeders/RawatInapSeeder.php`, `tests/Feature/AlurRawatInapTest.php`
- Modify: `database/seeders/DatabaseSeeder.php`, `KunjunganDummySeeder.php`, `README.md`, `docs/akun-pengguna.md`

**Isi seeder:** 4 ruang, 4 kelas, 40 bed, tarif kamar kedua penjamin.

| Kelas | Tarif umum per hari |
|---|---|
| VIP | 750.000 |
| Kelas 1 | 450.000 |
| Kelas 2 | 300.000 |
| Kelas 3 | 175.000 |

Ruang: Melati (Kelas 3), Anggrek (Kelas 2), Mawar (Kelas 1), Cendana (VIP).

**Data dummy:** sebagian kunjungan berlanjut ke rawat inap — beberapa masih
dirawat (supaya papan bed ada isinya), beberapa sudah pulang lengkap dengan
tagihan berisi baris kamar. Satu di antaranya sengaja pindah bed, supaya
perhitungan berpenggal terlihat nyata saat didemokan.

**Test alur menyeluruh:**

```
test_alur_lengkap_dari_perintah_rawat_sampai_tagihan_lunas
test_pasien_pindah_kelas_ditagih_dua_tarif_berbeda
test_bed_berputar_dipakai_pasien_berikutnya_setelah_pemulangan
```

**Steps:** test alur → seeder → `migrate:fresh --seed` → verifikasi lewat SQL →
seluruh suite → perbarui README dan dokumen akun → commit dan dorong.

---

## Ringkasan Cakupan

| Aturan (spec Fase 5 bagian 8) | Tugas |
|---|---|
| 59 Perintah oleh dokter, indikasi wajib | Task 2, 7 |
| 60 Satu kunjungan satu masa rawat | Task 2 |
| 61 Tidak pada kunjungan yang sudah selesai | Task 2 |
| 62 Satu bed satu pasien, dijamin basis data | Task 1, 3 |
| 63 Penempatan di bed terisi ditolak | Task 3 |
| 64 Pasien yang sudah di bed tidak ditempatkan lagi | Task 3 |
| 65 Pindah bed berpenggal | Task 3 |
| 66 Catatan wajib SOAP lengkap berpenulis | Task 4 |
| 67 Koreksi catatan wajib beralasan | Task 4 |
| 68 Pemulangan wajib diagnosa akhir dan cara pulang | Task 5 |
| 69 Tidak pulang saat penunjang belum selesai | Task 5 |
| 70 Pemulangan melepaskan bed | Task 5 |
| 71 Lama rawat minimal satu hari | Task 6 |
| 72 Tarif per penggal menurut kelas | Task 6 |
| 73 Tagihan memuat kamar dan seluruh layanan | Task 6 |
| 74 Kunjungan rawat inap tidak ditutup lewat alur poli | Task 5 |
| 75 Rincian sementara tanpa membuat tagihan | Task 6 |
