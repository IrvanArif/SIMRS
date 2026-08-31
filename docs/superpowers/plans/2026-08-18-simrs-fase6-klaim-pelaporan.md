# Rencana Implementasi — Fase 6 Klaim dan Pelaporan

Spec: [`../specs/2026-08-18-simrs-fase6-klaim-pelaporan-design.md`](../specs/2026-08-18-simrs-fase6-klaim-pelaporan-design.md)

## Global Constraints

- **TDD tanpa kecuali.** Test ditulis lebih dulu, dijalankan sampai **gagal**, baru implementasinya.
- **Jalankan test lalu baca hasilnya sebelum commit.** Jangan merangkai dengan `&&`.
- **Periksa import ganda** setiap menyunting berkas lama: `grep "^use " berkas | sort | uniq -d` harus kosong.
- **Uang selalu bilangan bulat rupiah.**
- **`max()+1` terlarang untuk penomoran.** Pakai `PencatatNomor`.
- **Sapu seluruh rute di aplikasi yang berjalan** setelah tugas layar, untuk setiap akun.
- **Setiap tautan menu wajib bisa dibuka pemiliknya** — tambahkan rute barunya ke `MenuNavigasi` beserta testnya.
- Seluruh nama tabel, kolom, kelas, rute, label, dan pesan dalam bahasa Indonesia.
- Nama rumah sakit nyata tidak boleh muncul di mana pun. Pakai "RS Sampel".

## Pola yang Sudah Ada — Baca Sebelum Menulis

| Kebutuhan | Contoh |
|---|---|
| Penomoran aman balapan | `app/Services/NomorDokumen.php` |
| Koreksi/pembatalan beralasan berjejak | `app/Services/PenulisanEkspertise.php::koreksi()` |
| Periksa ulang status di dalam kunci | `app/Services/PenulisanEkspertise.php::tulis()` |
| Menyusun baris dari banyak sumber | `app/Services/PenyusunTagihan.php` |
| Perhitungan berpenggal | `app/Services/PenghitungBiayaKamar.php` |
| Policy + layar + menu | `app/Policies/RawatInapPolicy.php`, `app/Support/MenuNavigasi.php` |

## Struktur Berkas

```
app/Enums/          JenisPelayanan, StatusBerkasKlaim
app/Models/         Icd9, Sep, BerkasKlaim, BerkasKlaimDiagnosa, BerkasKlaimProsedur
app/Kontrak/        PenerbitSep            (antarmuka)
app/Services/       SepLokal, PenerbitanSep, PenyusunBerkasKlaim, EksporKlaim,
                    IndikatorRawatInap, LaporanMorbiditas, LaporanPendapatan,
                    LaporanKunjungan, RentangTanggal
app/Policies/       SepPolicy, BerkasKlaimPolicy
app/Livewire/Klaim/     DaftarSep, LayarSep, DaftarBerkasKlaim, LayarBerkasKlaim
app/Livewire/Laporan/   IndikatorRawatInap, Morbiditas, Pendapatan, RekapKunjungan
```

---

### Task 1: Master ICD-9-CM dan pemetaan tindakan

**Files:**
- Create: migration `create_icd9_table` + kolom `tindakan.icd9_id`, `app/Models/Icd9.php`, factory
- Modify: `app/Models/Tindakan.php`, `database/seeders/MasterSeeder.php`
- Test: `tests/Feature/MasterIcd9Test.php`

**Interfaces:**
- Produces: `Icd9` (kode, nama), `Tindakan::icd9()` nullable belongsTo.

Memenuhi separuh aturan 88.

**Test:**
```
test_icd9_menyimpan_kode_dan_nama
test_kode_icd9_ganda_ditolak_database
test_tindakan_bisa_dipetakan_ke_icd9
test_tindakan_tanpa_pemetaan_tetap_sah
```

**Migration:**
```php
Schema::create('icd9', function (Blueprint $table) {
    $table->id();
    $table->string('kode', 10)->unique();
    $table->string('nama', 255);
    $table->timestamps();
});

Schema::table('tindakan', function (Blueprint $table) {
    // Nullable: tidak semua tindakan punya padanan ICD-9-CM, dan yang tidak
    // punya tidak boleh menggagalkan klaim (aturan 88).
    $table->foreignId('icd9_id')->nullable()->after('kategori')->constrained('icd9')->nullOnDelete();
});
```

Seeder mengisi belasan kode ICD-9-CM yang lazim di rawat jalan dan rawat inap,
lalu memetakannya ke tindakan yang sudah ada.

**Commit:** `feat: tambah master ICD-9-CM dan pemetaan prosedur tindakan`

---

### Task 2: SEP — antarmuka, penerapan lokal, dan penerbitannya

**Files:**
- Create: `app/Enums/JenisPelayanan.php`, `app/Kontrak/PenerbitSep.php`, `app/Services/SepLokal.php`, `app/Services/PenerbitanSep.php`, migration `create_sep_table`, `app/Models/Sep.php`
- Modify: `app/Services/NomorDokumen.php`, `app/Models/Kunjungan.php`, `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/PenerbitanSepTest.php`

**Interfaces:**
- `JenisPelayanan`: `RawatInap = '1'`, `RawatJalan = '2'` (nilainya mengikuti kode BPJS), `label()`
- `PenerbitSep::terbitkan(Kunjungan, string $diagnosaAwal): string` mengembalikan nomor SEP
- `PenerbitSep::batalkan(Sep, string $alasan): void`
- `SepLokal implements PenerbitSep` — nomor `SEP-YYYYMMDD-NNNN` lewat `NomorDokumen`
- `PenerbitanSep::terbitkan(Kunjungan, User, ?string $noRujukan): Sep` dan `::batalkan(Sep, User, string $alasan): Sep`
- `Kunjungan::sep(): HasOne`, `Kunjungan::sepBerlaku(): ?Sep`

Memenuhi aturan 78–82.

**Test:**
```
test_sep_terbit_dengan_nomor_dan_tanggal
test_sep_tidak_bisa_diterbitkan_untuk_pasien_umum
test_pesan_penolakan_menyebut_nama_penjaminnya
test_sep_wajib_menyertakan_nomor_kartu_peserta
test_sep_wajib_menyertakan_diagnosa_awal
test_satu_kunjungan_hanya_boleh_punya_satu_sep_berlaku
test_batasan_unik_menolak_sep_kedua_yang_berlaku
test_sep_yang_dibatalkan_membuka_jalan_untuk_sep_baru
test_jenis_pelayanan_rawat_jalan_saat_tidak_ada_masa_rawat
test_jenis_pelayanan_rawat_inap_saat_ada_masa_rawat
test_kelas_rawat_diambil_dari_bed_yang_ditempati
test_pembatalan_sep_wajib_beralasan
test_alasan_pembatalan_tercatat_di_audit_log
test_penerbit_sep_bisa_diganti_tanpa_menyentuh_pemanggilnya
```

Test terakhir mengikat janji bagian 2.2 spec: ia mengikat ulang `PenerbitSep`
ke penerapan ganda di dalam test dan membuktikan `PenerbitanSep` tetap bekerja.

**Migration:**
```php
Schema::create('sep', function (Blueprint $table) {
    $table->id();
    $table->string('no_sep', 30)->unique();
    $table->foreignId('kunjungan_id')->constrained('kunjungan')->cascadeOnDelete();
    $table->string('no_kartu', 30);
    $table->string('jenis_pelayanan', 2);
    $table->string('kelas_rawat', 20)->nullable();
    $table->string('diagnosa_awal', 255);
    $table->string('no_rujukan', 40)->nullable();
    $table->date('tanggal');
    $table->string('status', 20)->default('berlaku');
    $table->string('diterbitkan_dengan', 30)->default('lokal');
    $table->foreignId('diterbitkan_oleh')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    // Satu SEP berlaku per kunjungan (aturan 79). Yang batal boleh menumpuk,
    // jadi kuncinya menyertakan status.
    $table->unique(['kunjungan_id', 'status'], 'sep_berlaku_unik');
});
```

Kolom `diterbitkan_dengan` mencatat penerapan mana yang menerbitkannya (`lokal`
atau nanti `vclaim`), supaya nomor hasil simulasi tidak pernah tertukar dengan
nomor sungguhan saat kredensial akhirnya ada.

**Commit:** `feat: tambah penerbitan SEP di balik antarmuka yang bisa diganti`

---

### Task 3: Penyusunan berkas klaim

**Files:**
- Create: `app/Enums/StatusBerkasKlaim.php`, migration `create_berkas_klaim_tables`, model `BerkasKlaim`, `BerkasKlaimDiagnosa`, `BerkasKlaimProsedur`, `app/Services/PenyusunBerkasKlaim.php`
- Modify: `app/Services/NomorDokumen.php`, `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/BerkasKlaimTest.php`

**Interfaces:**
- `StatusBerkasKlaim`: `Draf`, `Diajukan`, `Disetujui`, `Ditolak`; `bisaDisunting(): bool`
- `PenyusunBerkasKlaim::susun(Kunjungan, User): BerkasKlaim`
- `::ajukan(BerkasKlaim, User): BerkasKlaim`
- `::batalkan(BerkasKlaim, User, string $alasan): BerkasKlaim`
- `::tandaiHasil(BerkasKlaim, StatusBerkasKlaim, User, ?string $catatan): BerkasKlaim`

Memenuhi aturan 83–88.

**Test:**
```
test_berkas_klaim_bernomor_dan_berstatus_draf
test_klaim_tidak_bisa_disusun_dari_kunjungan_yang_belum_selesai
test_klaim_tidak_bisa_disusun_tanpa_tagihan
test_klaim_menolak_kunjungan_tanpa_sep_dan_menyebutkan_kekurangannya
test_klaim_menolak_berkas_tanpa_diagnosa_primer_dan_menyebutkan_kekurangannya
test_pesan_kekurangan_memuat_seluruh_kekurangan_sekaligus
test_klaim_menyalin_diagnosa_primer_dan_sekunder
test_klaim_memuat_prosedur_icd9_dari_pemetaan_tindakan
test_tindakan_tanpa_pemetaan_icd9_tidak_menggagalkan_klaim
test_tindakan_tanpa_pemetaan_dicatat_sebagai_peringatan
test_klaim_menyalin_total_biaya_dari_tagihan
test_klaim_rawat_inap_menyalin_tanggal_masuk_dan_pulang
test_satu_kunjungan_hanya_boleh_punya_satu_berkas_berlaku
test_berkas_yang_sudah_diajukan_tidak_bisa_disunting
test_berkas_yang_sudah_diajukan_hanya_berubah_lewat_pembatalan_beralasan
test_pembatalan_membuka_jalan_untuk_penyusunan_ulang
test_hasil_verifikasi_disetujui_tercatat
test_hasil_verifikasi_ditolak_wajib_bercatatan
```

`test_pesan_kekurangan_memuat_seluruh_kekurangan_sekaligus` menjaga hal yang
mudah salah: penolakan yang menyebut satu kekurangan lalu menyebut kekurangan
berikutnya setelah diperbaiki memaksa petugas bolak-balik. Seluruh kekurangan
dikumpulkan dulu, baru dilaporkan bersama.

**Commit:** `feat: tambah penyusunan dan pengajuan berkas klaim`

---

### Task 4: Ekspor berkas klaim

**Files:**
- Create: `app/Services/EksporKlaim.php`
- Test: `tests/Feature/EksporKlaimTest.php`

**Interfaces:**
- `EksporKlaim::baris(BerkasKlaim): array` — satu larik datar siap ditulis
- `EksporKlaim::csv(Collection $berkas): string`

Ekspor sengaja berupa **larik datar lebih dulu**, bukan langsung teks: itu yang
membuat isinya bisa diuji tanpa mengurai CSV.

**Test:**
```
test_baris_ekspor_memuat_identitas_sep_dan_biaya
test_baris_ekspor_menggabungkan_diagnosa_sekunder_dengan_pemisah
test_csv_memuat_baris_kepala
test_csv_menuliskan_setiap_berkas_satu_baris
test_nilai_bertanda_kutip_tidak_merusak_csv
test_berkas_draf_tidak_ikut_diekspor
```

`test_nilai_bertanda_kutip_tidak_merusak_csv` menjaga cacat klasik: nama pasien
atau diagnosa yang memuat koma atau tanda kutip menggeser seluruh kolom di
berkas yang dikirim ke pihak lain.

**Commit:** `feat: tambah ekspor berkas klaim ke CSV`

---

### Task 5: Indikator rawat inap

**Files:**
- Create: `app/Services/RentangTanggal.php`, `app/Services/IndikatorRawatInap.php`
- Test: `tests/Feature/IndikatorRawatInapTest.php`

**Interfaces:**
- `RentangTanggal::dari(string|CarbonInterface $awal, string|CarbonInterface $akhir): self` — menolak rentang terbalik
- `IndikatorRawatInap::hitung(RentangTanggal): array{bor, los, toi, bto, hari_rawat, pasien_keluar, bed_tersedia}`

Memenuhi aturan 89 dan 92.

**Test:**
```
test_rentang_tanggal_terbalik_ditolak
test_rentang_satu_hari_sah
test_hari_rawat_dihitung_dari_penggal_yang_beririsan_dengan_periode
test_penggal_yang_melampaui_periode_dipotong_pada_batas_periode
test_bor_dihitung_hanya_dari_bed_aktif
test_bed_nonaktif_tidak_menambah_kapasitas
test_indikator_bernilai_nol_saat_tidak_ada_bed_aktif
test_indikator_bernilai_nol_saat_tidak_ada_pasien_keluar
test_los_dihitung_dari_pasien_yang_sudah_keluar
test_toi_tidak_pernah_bernilai_negatif
test_bto_menghitung_perputaran_bed
```

`test_penggal_yang_melampaui_periode_dipotong_pada_batas_periode` menjaga inti
perhitungannya: pasien yang masuk sebelum periode dan pulang sesudahnya hanya
menyumbang hari di dalam periode. Tanpa pemotongan, BOR bisa melampaui 100%.

`test_toi_tidak_pernah_bernilai_negatif` menjaga keadaan yang benar-benar
terjadi saat bangsal sangat penuh: hari rawat bisa melampaui kapasitas bila
ada bed yang dinonaktifkan di tengah periode, dan TOI negatif tidak bermakna.

**Commit:** `feat: tambah perhitungan indikator BOR, LOS, TOI, dan BTO`

---

### Task 6: Laporan morbiditas, pendapatan, dan rekap kunjungan

**Files:**
- Create: `app/Services/LaporanMorbiditas.php`, `app/Services/LaporanPendapatan.php`, `app/Services/LaporanKunjungan.php`
- Test: `tests/Feature/LaporanTest.php`

**Interfaces:**
- `LaporanMorbiditas::sepuluhBesar(RentangTanggal, ?bool $rawatInap = null): Collection`
- `LaporanPendapatan::perPenjamin(RentangTanggal): Collection`
- `LaporanKunjungan::perPoli(RentangTanggal): Collection`

Memenuhi aturan 90–92.

**Test:**
```
test_morbiditas_mengurutkan_dari_yang_terbanyak
test_morbiditas_hanya_menghitung_diagnosa_primer
test_morbiditas_memisahkan_rawat_jalan_dan_rawat_inap
test_morbiditas_membatasi_sepuluh_teratas
test_morbiditas_di_luar_periode_tidak_ikut_terhitung
test_pendapatan_memisahkan_lunas_menunggu_dan_ditanggung_penjamin
test_yang_ditanggung_penjamin_tidak_dihitung_sebagai_uang_diterima
test_rekap_kunjungan_memisahkan_rawat_jalan_dan_rawat_inap
test_seluruh_laporan_menolak_rentang_terbalik
```

`test_yang_ditanggung_penjamin_tidak_dihitung_sebagai_uang_diterima` menjaga
kesalahan yang mahal: menjumlahkan seluruh tagihan sebagai pendapatan membuat
manajemen mengira punya uang yang sebenarnya masih piutang klaim.

**Commit:** `feat: tambah laporan morbiditas, pendapatan, dan rekap kunjungan`

---

### Task 7: Hak akses, layar, dan menu

**Files:**
- Create: `app/Policies/SepPolicy.php`, `app/Policies/BerkasKlaimPolicy.php`, komponen Livewire `Klaim\*` dan `Laporan\*` beserta viewnya
- Modify: `routes/web.php`, `app/Support/MenuNavigasi.php`, `app/Livewire/Pendaftaran/FormKunjungan.php`
- Test: `tests/Feature/HakAksesKlaimTest.php`, `tests/Feature/LayarKlaimTest.php`, `tests/Feature/LayarLaporanTest.php`

**Kewenangan:**

| Aksi | Peran |
|---|---|
| Terbitkan/batalkan SEP | admisi |
| Susun/ajukan/batalkan berkas klaim | rekam_medis |
| Tandai hasil verifikasi | rekam_medis |
| Lihat laporan | admin, rekam_medis |
| Lihat laporan pendapatan | admin, kasir |

Rekam medis yang menyusun klaim, bukan kasir: klaim disusun dari kode diagnosa
dan prosedur, dan pengkodean adalah pekerjaan rekam medis.

**Test:**
```
test_hanya_admisi_yang_boleh_menerbitkan_sep
test_hanya_rekam_medis_yang_boleh_menyusun_klaim
test_kasir_tidak_bisa_mengajukan_klaim
test_kasir_boleh_melihat_laporan_pendapatan
test_kasir_tidak_bisa_melihat_laporan_morbiditas
test_admisi_menerbitkan_sep_lewat_layar
test_sep_tanpa_diagnosa_awal_menampilkan_pesan_di_layar
test_rekam_medis_menyusun_klaim_lewat_layar
test_berkas_kurang_lengkap_menampilkan_daftar_kekurangan_di_layar
test_layar_indikator_menampilkan_bor_los_toi_bto
test_layar_laporan_menolak_rentang_terbalik_dengan_pesan
test_tautan_menu_baru_bisa_dibuka_pemiliknya
```

**Steps:** test → gagal → policy, komponen, view, rute, menu → lulus → seluruh
suite → **sapu rute untuk kesepuluh akun** → commit
`feat: tambah layar klaim dan laporan beserta hak aksesnya`.

---

### Task 8: Seeder, alur menyeluruh, dan dokumentasi

**Files:**
- Create: `tests/Feature/AlurKlaimTest.php`
- Modify: `database/seeders/MasterSeeder.php`, `KunjunganDummySeeder.php`, `README.md`, `docs/akun-pengguna.md`

**Data dummy:** SEP terbit untuk seluruh kunjungan BPJS; sebagian kunjungan BPJS
yang sudah selesai punya berkas klaim — sebagian draf, sebagian diajukan, satu
disetujui, satu ditolak — supaya keempat status terlihat saat didemokan.

**Test alur menyeluruh:**
```
test_alur_lengkap_dari_sep_terbit_sampai_klaim_diajukan
test_klaim_rawat_inap_memuat_kelas_lama_rawat_dan_seluruh_biayanya
test_laporan_membaca_data_yang_sama_dengan_yang_dihasilkan_alurnya
```

**Steps:** test alur → seeder → `migrate:fresh --seed` → verifikasi lewat SQL →
seluruh suite → perbarui README dan dokumen akun → commit dan dorong.

---

## Ringkasan Cakupan

| Aturan (spec Fase 6 bagian 8) | Tugas |
|---|---|
| 78 SEP hanya untuk pasien berpenjamin | Task 2 |
| 79 Satu SEP berlaku per kunjungan | Task 2 |
| 80 SEP wajib nomor kartu dan diagnosa awal | Task 2 |
| 81 Jenis pelayanan mengikuti kenyataan | Task 2 |
| 82 Pembatalan SEP beralasan | Task 2 |
| 83 Klaim dari kunjungan selesai dan bertagihan | Task 3 |
| 84 Kelengkapan wajib berkas klaim | Task 3 |
| 85 Penolakan menyebut kekurangannya | Task 3 |
| 86 Berkas diajukan tidak bisa disunting | Task 3 |
| 87 Satu berkas berlaku per kunjungan | Task 3 |
| 88 Prosedur ICD-9-CM dari pemetaan tindakan | Task 1, 3 |
| 89 Indikator hanya dari bed aktif | Task 5 |
| 90 Morbiditas memisahkan rawat jalan dan inap | Task 6 |
| 91 Pendapatan memisahkan status tagihannya | Task 6 |
| 92 Rentang tanggal terbalik ditolak | Task 5, 6 |
