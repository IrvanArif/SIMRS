# SIMRS — Desain Fase 1: Fondasi & Rawat Jalan

Tanggal: 2026-08-18
Status: menunggu persetujuan
Penyusun: Irvan (airvanarif@gmail.com) bersama Claude

## 1. Tujuan

Membangun Sistem Informasi Manajemen Rumah Sakit berbasis Laravel yang dirancang
sebagai pengganti SIMRS yang berjalan di RS Sampel,
sekaligus menjadi portofolio dan bahan latihan sertifikasi.

Fase 1 membangun tulang punggung sistem: satu alur rawat jalan yang utuh dari
pasien mendaftar sampai tagihannya diselesaikan kasir. Semua modul di fase
berikutnya menempel pada entitas yang dibuat di fase ini.

Pengembangan tahap ini memakai **data dummy**. Tidak ada migrasi data pasien
nyata dan tidak ada integrasi ke sistem eksternal (BPJS, SATUSEHAT) di Fase 1.

## 2. Peta fase

| Fase | Isi | Status |
|---|---|---|
| 1. Fondasi + Rawat Jalan | Auth & hak akses, master data, pendaftaran & antrian, kunjungan poli, rekam medis SOAP + ICD-10, resep, kasir, audit trail | Spec ini |
| 2. Penunjang medis | Farmasi (stok & dispensing), Laboratorium, Radiologi | Belum |
| 3. Rawat Inap | Manajemen bed, visite, tindakan, billing inap | Belum |
| 4. Klaim & pelaporan | BPJS VClaim, INA-CBG, SATUSEHAT, laporan RL/SIRS | Belum |
| 5. Pendukung | Inventori, SDM, akuntansi | Belum |

Setiap fase memiliki siklus spec → rencana implementasi → implementasi sendiri.

## 3. Ruang lingkup Fase 1

**Termasuk:**

- Autentikasi dan hak akses untuk 6 peran
- Master data: poli, dokter, jadwal dokter, penjamin, tindakan, tarif per penjamin, ICD-10, obat
- Pendaftaran pasien baru dan pencarian pasien lama
- Pembuatan kunjungan rawat jalan dan penomoran antrian per poli per hari
- Input tanda vital oleh perawat
- Rekam medis SOAP, diagnosa ICD-10, dan tindakan oleh dokter
- Penulisan resep (tanpa stok dan tanpa dispensing — itu Fase 2)
- Penyusunan tagihan otomatis dan pembayaran di kasir
- Audit trail untuk seluruh perubahan data klinis dan data pasien
- Halaman display antrian untuk layar ruang tunggu
- Seeder data dummy agar sistem langsung bisa didemokan

**Tidak termasuk:**

- Rawat inap, IGD, farmasi berbasis stok, laboratorium, radiologi
- Integrasi BPJS VClaim, INA-CBG, SATUSEHAT
- Migrasi data dari SIMRS lama
- Laporan RL/SIRS Kemenkes
- Aplikasi mobile atau anjungan pendaftaran mandiri

## 4. Peran pengguna dan kewenangan

| Peran | Kewenangan |
|---|---|
| `admisi` | Cari/daftar pasien, buat kunjungan, cetak karcis antrian, batalkan kunjungan yang belum diperiksa |
| `perawat` | Lihat antrian poli, input tanda vital dan keluhan awal |
| `dokter` | Panggil antrian di polinya, isi SOAP, diagnosa, tindakan, resep, selesaikan kunjungan, koreksi rekam medis miliknya sendiri disertai alasan |
| `rekam_medis` | Koreksi data demografi pasien, penelusuran rekam medis lintas pasien, rekap kunjungan harian |
| `kasir` | Lihat tagihan, proses pembayaran, cetak kuitansi |
| `admin` | Kelola user & peran, seluruh master data, lihat audit log. Tidak berwenang mengisi atau mengubah rekam medis |

Hak akses ditegakkan di **Policy**, bukan sekadar menyembunyikan menu. Setiap
pembatasan wajib punya test yang membuktikan peran lain ditolak.

## 5. Arsitektur

**Stack:** Laravel 13, PHP 8.5, Livewire 3, Blade, Tailwind via Vite, MySQL/MariaDB,
`spatie/laravel-permission` untuk RBAC. Monolit satu codebase, tanpa frontend terpisah.

**Struktur:**

```
app/
  Models/            entitas Eloquent
  Livewire/
    Pendaftaran/     CariPasien, FormPasien, FormKunjungan, PapanAntrian
    Poli/            AntrianPoli, FormVital, FormSoap, FormResep
    Kasir/           DaftarTagihan, ProsesPembayaran
    Master/          komponen CRUD master data
    Admin/           KelolaUser, PenampilAuditLog
  Services/          NomorRekamMedis, NomorAntrian, NomorDokumen, PenyusunTagihan
  Policies/          KunjunganPolicy, PemeriksaanPolicy, TagihanPolicy, PasienPolicy
  Observers/         PencatatAudit
  Enums/             StatusKunjungan, StatusAntrian, StatusTagihan, JenisDiagnosa
routes/
  web.php            seluruh halaman berautentikasi
  api.php            endpoint publik display antrian
```

**Prinsip pemisahan:** komponen Livewire mewakili satu layar dan tidak menyimpan
aturan bisnis. Aturan yang punya konsekuensi — penomoran rekam medis, penomoran
antrian harian, penyusunan tagihan dari tarif per penjamin — tinggal di kelas
Service sehingga bisa diuji tanpa merender UI. Komponen Livewire yang membengkak
adalah tanda aturan salah tempat.

**Tiga keputusan yang mengikat seluruh sistem:**

1. **Penomoran tahan tabrakan.** Nomor antrian dan nomor rekam medis diberikan di
   dalam transaksi database dengan penguncian baris counter, dan setiap tabel
   punya unique constraint sebagai jaring pengaman terakhir. `max() + 1` dilarang.
2. **Data klinis tidak dihapus keras.** `pasien` dan `pemeriksaan` memakai soft
   delete. Koreksi rekam medis setelah kunjungan selesai wajib menyertakan alasan
   dan tercatat di `audit_logs`.
3. **Audit trail otomatis.** Observer mencatat setiap create/update/delete pada
   entitas klinis dan data pasien: siapa, kapan, dari nilai apa ke nilai apa.

## 6. Model data

### Master

| Tabel | Kolom kunci |
|---|---|
| `users` | `name`, `email` (unik), `password`, `dokter_id` (nullable), `aktif` |
| `poli` | `kode` (unik), `nama`, `lokasi`, `aktif` |
| `dokter` | `nip` (unik), `nama`, `spesialisasi`, `no_sip`, `poli_id`, `aktif` |
| `jadwal_dokter` | `dokter_id`, `hari` (1–7), `jam_mulai`, `jam_selesai`, `kuota` |
| `penjamin` | `kode` (unik: UMUM, BPJS), `nama`, `jenis` (`tunai`\|`penjamin`), `aktif` |
| `tindakan` | `kode` (unik), `nama`, `kategori` (`administrasi`\|`konsultasi`\|`tindakan_medis`), `aktif` |
| `tarif_tindakan` | `tindakan_id`, `penjamin_id`, `tarif`, `berlaku_mulai` — unik `(tindakan_id, penjamin_id, berlaku_mulai)` |
| `icd10` | `kode` (unik), `nama_id`, `nama_en` |
| `obat` | `kode` (unik), `nama`, `satuan`, `bentuk_sediaan`, `aktif` |

### Transaksi

| Tabel | Kolom kunci |
|---|---|
| `pasien` | `no_rm` (unik), `nik` (unik, 16 digit), `nama`, `tempat_lahir`, `tanggal_lahir`, `jenis_kelamin` (`L`\|`P`), `alamat`, `rt`, `rw`, `kelurahan`, `kecamatan`, `kabupaten`, `no_hp`, `pekerjaan`, `agama`, `status_perkawinan`, `nama_penanggung_jawab`, `hubungan_penanggung_jawab`, soft delete |
| `kunjungan` | `no_kunjungan` (unik), `pasien_id`, `poli_id`, `dokter_id`, `penjamin_id`, `no_kartu_penjamin`, `jenis_kunjungan` (`baru`\|`lama`), `tanggal`, `status`, `waktu_daftar`, `waktu_selesai`, `didaftarkan_oleh`, soft delete |
| `antrian` | `kunjungan_id`, `poli_id`, `tanggal`, `nomor`, `status`, `waktu_panggil` — unik `(poli_id, tanggal, nomor)` |
| `pemeriksaan` | `kunjungan_id` (unik), `sistolik`, `diastolik`, `nadi`, `suhu`, `respirasi`, `berat_badan`, `tinggi_badan`, `keluhan_awal`, `alergi`, `subjective`, `objective`, `assessment`, `plan`, `dicatat_perawat_id`, `dicatat_dokter_id`, `waktu_perawat`, `waktu_dokter`, soft delete |
| `diagnosa` | `kunjungan_id`, `icd10_id`, `jenis` (`primer`\|`sekunder`), `catatan` — unik `(kunjungan_id, icd10_id)` |
| `tindakan_kunjungan` | `kunjungan_id`, `tindakan_id`, `jumlah`, `tarif_satuan` (salinan tarif saat itu), `dilakukan_oleh` |
| `resep` | `no_resep` (unik), `kunjungan_id`, `dokter_id`, `status` (`dibuat`\|`diserahkan`) |
| `resep_detail` | `resep_id`, `obat_id`, `jumlah`, `aturan_pakai`, `catatan` |
| `tagihan` | `no_tagihan` (unik), `kunjungan_id` (unik), `penjamin_id`, `total`, `ditanggung_penjamin`, `ditagihkan_ke_pasien`, `status`, `disusun_pada` |
| `tagihan_detail` | `tagihan_id`, `deskripsi`, `jumlah`, `tarif_satuan`, `subtotal`, `tindakan_kunjungan_id` |
| `pembayaran` | `tagihan_id`, `no_kuitansi` (unik), `metode` (`tunai`\|`debit`\|`qris`), `nominal`, `kembalian`, `kasir_id`, `waktu_bayar` |
| `audit_logs` | `user_id`, `aksi`, `model_tipe`, `model_id`, `perubahan` (JSON sebelum/sesudah), `alasan`, `ip`, `user_agent`, `created_at` |

### Status

- `kunjungan.status`: `terdaftar` → `diperiksa_perawat` → `diperiksa_dokter` → `selesai`; atau `batal`
- `antrian.status`: `menunggu` → `dipanggil` → `dilayani` → `selesai`; atau `terlewat`
- `tagihan.status`: `belum_bayar` → `lunas`; atau `ditanggung_penjamin`; atau `batal`

### Format nomor

| Nomor | Format | Contoh |
|---|---|---|
| Rekam medis | 6 digit sekuensial global, nol di depan, tidak pernah dipakai ulang | `000137` |
| Antrian | disimpan sebagai bilangan bulat; ditampilkan sebagai kode poli + 3 digit, dimulai dari 1 setiap hari per poli | `UMU-001` |
| Kunjungan | `KJ-YYYYMMDD-NNNN` sekuensial harian | `KJ-20260818-0042` |
| Resep | `RS-YYYYMMDD-NNNN` | `RS-20260818-0018` |
| Tagihan | `TG-YYYYMMDD-NNNN` | `TG-20260818-0031` |
| Kuitansi | `KW-YYYYMMDD-NNNN` | `KW-20260818-0029` |

## 7. Alur utama

```
Admisi    cari pasien (NIK / nama / no. RM)
          ├─ tidak ada  → daftar pasien baru, sistem memberi no. RM
          └─ ada        → pakai data yang ada
          → buat kunjungan (poli, dokter, penjamin, no. kartu bila BPJS)
          → sistem membuat antrian bernomor untuk poli & tanggal itu
          → cetak karcis antrian

Perawat   buka antrian poli → panggil pasien → input tanda vital & keluhan awal
          → status kunjungan menjadi "diperiksa_perawat"

Dokter    buka antrian polinya → panggil nomor berikutnya
          → isi SOAP, diagnosa ICD-10 (satu primer, sekunder opsional),
            tindakan yang dilakukan, resep bila perlu
          → selesaikan kunjungan

Sistem    saat kunjungan diselesaikan, tagihan disusun otomatis dari seluruh
          tindakan_kunjungan dikalikan tarif sesuai penjamin pasien

Kasir     penjamin Umum → terima pembayaran, cetak kuitansi, status "lunas"
          penjamin BPJS → tagihan ditandai "ditanggung_penjamin",
                          ditagihkan_ke_pasien = 0, nilai total tetap tercatat penuh
```

Nilai tagihan pasien BPJS dicatat penuh meskipun pasien tidak membayar. Angka
itulah yang menjadi bahan klaim di Fase 4, jadi tidak boleh hilang di Fase 1.

## 8. Aturan bisnis

Setiap aturan di bawah wajib punya test yang membuktikannya.

1. NIK harus tepat 16 digit angka dan unik antar pasien.
2. Tanggal lahir tidak boleh melewati hari ini.
3. Nomor rekam medis diberikan sekali saat pasien didaftarkan dan tidak pernah dipakai ulang, termasuk bila pasien dihapus (soft delete).
4. Nomor antrian dimulai dari 1 setiap hari untuk setiap poli.
5. Dua kunjungan pada poli dan tanggal yang sama tidak boleh mendapat nomor antrian yang sama, bahkan pada pendaftaran serentak.
6. Satu pasien tidak boleh punya lebih dari satu kunjungan aktif di poli yang sama pada hari yang sama. Kunjungan disebut aktif selama statusnya bukan `selesai` dan bukan `batal`.
7. Kunjungan dengan penjamin berjenis `penjamin` wajib mengisi nomor kartu.
8. Dokter hanya dapat memanggil dan memeriksa antrian pada poli tempat ia bertugas.
9. Setiap kunjungan wajib punya tepat satu diagnosa primer sebelum bisa diselesaikan.
10. Kunjungan tidak dapat diselesaikan bila SOAP belum diisi. SOAP dianggap terisi bila keempat bagian `subjective`, `objective`, `assessment`, dan `plan` tidak kosong.
11. Setelah kunjungan berstatus `selesai`, data klinisnya terkunci; koreksi hanya oleh dokter yang mencatatnya, wajib menyertakan alasan, dan tercatat di audit log.
12. Tagihan disusun otomatis satu kali saat kunjungan diselesaikan; tarif disalin ke `tindakan_kunjungan.tarif_satuan` agar perubahan master tarif di kemudian hari tidak mengubah tagihan lama.
13. Tarif dipilih berdasarkan penjamin kunjungan. Bila tarif untuk penjamin tersebut tidak ada, dipakai tarif penjamin `UMUM` dan kejadian itu dicatat ke log.
14. Tagihan pasien berpenjamin BPJS berstatus `ditanggung_penjamin` dengan `ditagihkan_ke_pasien` = 0, sedangkan `total` tetap berisi nilai penuh.
15. Satu tagihan tidak dapat dibayar dua kali.
16. Pembayaran tunai tidak boleh kurang dari yang ditagihkan dan kembaliannya dihitung sistem; pembayaran debit dan QRIS harus tepat sama dengan yang ditagihkan sehingga kembaliannya selalu nol.
17. Kunjungan hanya dapat dibatalkan selama statusnya masih `terdaftar`.
18. Admin tidak berwenang mengisi maupun mengubah rekam medis.
19. Setiap perubahan pada `pasien`, `pemeriksaan`, `diagnosa`, dan `tagihan` tercatat di `audit_logs` beserta pelakunya.
20. Halaman display antrian dapat diakses tanpa login, dan hanya menampilkan nomor antrian, poli, serta nama dokter — tidak menampilkan nama pasien maupun data klinis.

## 9. Penanganan kesalahan

- **Validasi input** memakai pesan berbahasa Indonesia dan divalidasi ulang di sisi server, bukan hanya di komponen Livewire.
- **Tabrakan penomoran** ditangani dengan transaksi + penguncian baris counter, lalu percobaan ulang terbatas. Bila tetap gagal, petugas melihat pesan "Nomor sedang dipakai, silakan ulangi" — bukan halaman error 500.
- **Perubahan status ganda** (dua petugas menyelesaikan kunjungan yang sama, dua kasir membayar tagihan yang sama) dicegah dengan pengecekan status di dalam transaksi yang mengunci baris.
- **Akses tanpa hak** ditolak Policy dengan HTTP 403 dan pesan yang menjelaskan peran mana yang berwenang.
- **Kesalahan tak terduga** dicatat ke log lengkap dengan konteks (user, kunjungan, layar), sementara pengguna melihat halaman ramah tanpa stack trace ketika `APP_DEBUG=false`.
- **Data master yang hilang** (misalnya tarif belum diisi) tidak menghentikan pelayanan: sistem memakai nilai jatuh-tempo yang didefinisikan di aturan 13 dan mencatat peringatan agar admin menindaklanjuti.

## 10. Strategi pengujian

Pengembangan memakai TDD: test ditulis lebih dulu dan harus gagal sebelum kode
implementasinya ada. Nama test berbahasa Indonesia, mengikuti gaya proyek
sebelumnya.

**Unit test** untuk Service: `NomorRekamMedis`, `NomorAntrian`, `NomorDokumen`, `PenyusunTagihan`.

**Feature test** untuk setiap alur peran. Test yang wajib ada, minimal:

- `test_pendaftaran_pasien_baru_mendapat_nomor_rekam_medis_berurutan`
- `test_nik_kurang_dari_16_digit_ditolak`
- `test_nik_yang_sudah_terdaftar_ditolak`
- `test_antrian_pertama_pada_hari_itu_mendapat_nomor_1`
- `test_nomor_antrian_mulai_dari_1_lagi_pada_hari_berikutnya`
- `test_sepuluh_pendaftaran_serentak_menghasilkan_sepuluh_nomor_antrian_berbeda`
- `test_pasien_tidak_bisa_punya_dua_kunjungan_aktif_di_poli_yang_sama`
- `test_dokter_tidak_bisa_memanggil_antrian_poli_lain`
- `test_kunjungan_tanpa_diagnosa_primer_tidak_bisa_diselesaikan`
- `test_kasir_tidak_bisa_membuka_form_soap`
- `test_admin_tidak_bisa_mengubah_rekam_medis`
- `test_tagihan_disusun_dari_tindakan_dikali_tarif_sesuai_penjamin`
- `test_tagihan_pasien_bpjs_ditagihkan_nol_tapi_total_tetap_tercatat_penuh`
- `test_tagihan_yang_sudah_lunas_tidak_bisa_dibayar_ulang`
- `test_perubahan_diagnosa_setelah_kunjungan_selesai_wajib_menyertakan_alasan`
- `test_perubahan_data_pasien_tercatat_di_audit_log`
- `test_display_antrian_tidak_menampilkan_nama_pasien`

Database uji terpisah `simrs_test` dengan `RefreshDatabase`.

## 11. Lingkungan

- Database aplikasi: **`simrs`**; database uji: **`simrs_test`**. Kredensial mengikuti `.env` (pengguna MySQL `irvan`).
- Aplikasi dijalankan dari `/var/www/html/SIMRS`, diakses lewat `php artisan serve` saat pengembangan.
- Aset dibangun dengan Vite; Tailwind untuk styling.
- Cetak karcis antrian dan kuitansi memakai print CSS, belum memerlukan pustaka PDF.

**Seeder data dummy:**

- 6 user, satu untuk tiap peran, dengan kata sandi seragam. Seeder ini hanya boleh dijalankan pada `APP_ENV=local`; sebelum dipakai di rumah sakit, seluruh kata sandi wajib diganti
- 5 poli (Umum, Gigi, Anak, Kandungan, Penyakit Dalam)
- 10 dokter lengkap dengan jadwal praktik
- 2 penjamin: Umum dan BPJS
- ~30 tindakan dengan tarif berbeda untuk tiap penjamin
- ~200 kode ICD-10 yang paling sering dipakai di layanan rawat jalan
- ~50 obat
- 100 pasien dummy beserta riwayat kunjungan, sebagian sudah selesai dan tertagih

## 12. Kriteria selesai Fase 1

Fase 1 dianggap selesai ketika:

1. Seluruh test lulus dan setiap aturan bisnis di bagian 8 punya test yang membuktikannya.
2. Satu pasien dummy dapat ditelusuri utuh dari pendaftaran, antrian, vital, SOAP, diagnosa, resep, sampai kuitansi tercetak — untuk penjamin Umum maupun BPJS.
3. Setiap peran hanya dapat mengakses layar yang menjadi kewenangannya, dibuktikan oleh test.
4. Audit log memperlihatkan jejak perubahan data pasien dan data klinis beserta pelakunya.
5. Halaman display antrian berjalan tanpa login dan tidak membocorkan data pasien.
6. `php artisan migrate:fresh --seed` menghasilkan sistem yang langsung bisa didemokan.
