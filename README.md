# SIMRS — Sistem Informasi Manajemen Rumah Sakit

Aplikasi Laravel untuk RS Sampel. Dikembangkan bertahap;
**Fase 1 (Fondasi + Rawat Jalan)**, **Fase 2 (Farmasi)**, dan **Fase 3 (Laboratorium)**
sudah selesai, mencakup satu alur utuh dari pasien mendaftar, diperiksa,
menunggu hasil laboratorium, menerima obat, sampai tagihannya diselesaikan kasir.

Tahap ini memakai **data dummy**. Belum ada migrasi data pasien nyata dan belum ada
integrasi ke sistem eksternal (BPJS VClaim, SATUSEHAT) — keduanya masuk fase berikutnya.

## Dokumen

- Spesifikasi desain: [`docs/superpowers/specs/2026-08-18-simrs-fase1-rawat-jalan-design.md`](docs/superpowers/specs/2026-08-18-simrs-fase1-rawat-jalan-design.md)
- Rencana implementasi: [`docs/superpowers/plans/2026-08-18-simrs-fase1-rawat-jalan.md`](docs/superpowers/plans/2026-08-18-simrs-fase1-rawat-jalan.md)
- Spesifikasi Fase 2 (Farmasi): [`docs/superpowers/specs/2026-08-18-simrs-fase2-farmasi-design.md`](docs/superpowers/specs/2026-08-18-simrs-fase2-farmasi-design.md)
- Rencana Fase 2: [`docs/superpowers/plans/2026-08-18-simrs-fase2-farmasi.md`](docs/superpowers/plans/2026-08-18-simrs-fase2-farmasi.md)
- Spesifikasi Fase 3 (Laboratorium): [`docs/superpowers/specs/2026-08-18-simrs-fase3-laboratorium-design.md`](docs/superpowers/specs/2026-08-18-simrs-fase3-laboratorium-design.md)
- Rencana Fase 3: [`docs/superpowers/plans/2026-08-18-simrs-fase3-laboratorium.md`](docs/superpowers/plans/2026-08-18-simrs-fase3-laboratorium.md)

## Cakupan Fase 1

| Modul | Isi |
|---|---|
| Autentikasi & hak akses | 6 peran, ditegakkan lewat Policy (bukan sekadar menu tersembunyi) |
| Master data | Poli, dokter, jadwal, penjamin, tindakan, tarif per penjamin, ICD-10, obat |
| Pendaftaran | Cari/daftar pasien, buat kunjungan, nomor antrian per poli per hari, cetak karcis |
| Poli | Tanda vital oleh perawat, SOAP + diagnosa ICD-10 + tindakan + resep oleh dokter |
| Kasir | Tagihan otomatis, pembayaran tunai/debit/QRIS, cetak kuitansi |
| Rekam medis | Penelusuran, koreksi data berjejak, rekap kunjungan harian |
| Admin | Kelola pengguna, master data, penampil audit log |
| Farmasi | Stok per batch dengan kedaluwarsa, penyiapan resep beralokasi FEFO, penyerahan obat, kartu stok, penyesuaian opname |
| Laboratorium | Master pemeriksaan berparameter dan nilai rujukan per jenis kelamin, order dokter, pengambilan sampel, entri hasil berpenanda otomatis, validasi sebelum terbaca dokter |
| Display antrian | Halaman publik tanpa login untuk layar ruang tunggu |

Fase berikutnya: radiologi → rawat inap → klaim & pelaporan (BPJS, INA-CBG,
SATUSEHAT) → inventori, SDM, akuntansi.

## Alur laboratorium

Pasien menunggu hasilnya, sehingga kunjungan tidak bisa ditutup selama masih
ada order yang belum divalidasi — diagnosanya memang harus berdasar hasil.

```
Dokter memesan       tarif disalin saat itu juga; biayanya menyusul saat
                     kunjungan diselesaikan
Analis ambil sampel  waktu dan pelakunya tercatat
Analis entri hasil   penanda rendah/normal/tinggi dihitung sistem dari rujukan
                     sesuai jenis kelamin pasien, tidak diketik petugas
Validasi             baru sejak titik ini hasilnya terbaca dokter
Dokter membaca       menutup kunjungan; tagihan memuat biaya lab
```

Rujukan dibedakan menurut jenis kelamin karena rentang normalnya memang
berbeda: hemoglobin 16 g/dL normal bagi laki-laki tetapi tinggi bagi
perempuan. Parameter tanpa rujukan yang cocok tidak ditebak — nilainya
tersimpan tanpa penanda dan kejadiannya dicatat agar masternya dilengkapi.

## Alur apotek

Alurnya berbeda menurut penjamin, dijaga sepasang aturan:

```
Dokter menulis resep      tagihan terbentuk berisi tindakan saja,
                          kasir TERKUNCI selama resep belum disiapkan
Apoteker menyiapkan       alokasi FEFO, stok berkurang, biaya obat masuk ke
                          tagihan yang sama; kunci kasir terbuka
Pasien umum               bayar di kasir, baru obat diserahkan
Pasien berpenjamin        obat langsung diserahkan tanpa ke kasir, nilai
                          tagihan tetap tercatat penuh sebagai bahan klaim
```

Aturan pertama mencegah uang lolos, aturan kedua mencegah obat lolos.

## Menyiapkan

```bash
# 1. Database
mysql -u <user> -p -e "CREATE DATABASE simrs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u <user> -p -e "CREATE DATABASE simrs_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Dependensi
composer install
npm install && npm run build

# 3. Konfigurasi
cp .env.example .env
php artisan key:generate
# isi DB_USERNAME dan DB_PASSWORD di .env

# 4. Migrasi dan data dummy
php artisan migrate:fresh --seed

# 5. Jalankan
php artisan serve
```

## Menyajikan lewat Apache

Untuk pengembangan lokal, akar proyek memuat `index.php` dan `.htaccess` agar
`http://localhost/SIMRS/` bisa langsung dibuka. Keduanya **hanya berjalan saat
`APP_ENV=local`** dan menolak melayani apa pun di luar itu.

Di server sungguhan, arahkan `DocumentRoot` langsung ke `public/` lalu hapus
kedua berkas tersebut. Menyajikan akar proyek lewat web membuat `.env` dan
folder internal hanya terlindungi oleh `.htaccess` — dan itu lenyap begitu
`AllowOverride` dimatikan.

Laravel juga harus bisa menulis ke `storage/` dan `bootstrap/cache`:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
```

## Akun demo

Seluruhnya berkata sandi `rahasia123`.

| Email | Peran | Bisa apa |
|---|---|---|
| `admisi@rs.test` | Admisi | Daftar pasien, buat kunjungan, cetak karcis |
| `perawat@rs.test` | Perawat | Input tanda vital |
| `dokter@rs.test` | Dokter | SOAP, diagnosa, tindakan, resep, selesaikan kunjungan |
| `rekammedis@rs.test` | Rekam Medis | Telusur rekam medis, koreksi data, rekap harian |
| `apoteker@rs.test` | Apoteker | Siapkan dan serahkan obat, terima batch, kelola stok |
| `analis@rs.test` | Analis | Ambil sampel, entri hasil, validasi |
| `kasir@rs.test` | Kasir | Proses pembayaran, cetak kuitansi |
| `admin@rs.test` | Admin | Master data, kelola pengguna, audit log |

> **Peringatan:** kata sandi seragam ini hanya untuk pengembangan lokal.
> `PenggunaSeeder` menolak berjalan di luar `APP_ENV=local`. Sebelum dipakai di
> rumah sakit, seluruh akun wajib dibuat ulang dengan kata sandi masing-masing.

Halaman display antrian bisa dibuka tanpa login di `/display/antrian`.

## Pengujian

```bash
php artisan test
```

Pengembangan memakai TDD: test ditulis lebih dulu dan harus gagal sebelum
implementasinya ada. Nama test berbahasa Indonesia, mengikuti gaya proyek
sebelumnya (`test_nik_kurang_dari_16_digit_ditolak`). Database uji terpisah
(`simrs_test`) dan setiap aturan bisnis di bagian 8 spesifikasi punya test yang
membuktikannya.

## Catatan arsitektur

Komponen Livewire mewakili satu layar dan **tidak menyimpan aturan bisnis**.
Aturan yang punya konsekuensi tinggal di `app/Services`:

- `PencatatNomor` — satu-satunya tempat nomor urut dikeluarkan, dengan penguncian
  baris di dalam transaksi. `max() + 1` dilarang di seluruh proyek.
- `NomorRekamMedis`, `NomorAntrian`, `NomorDokumen` — penomoran RM, antrian, dan dokumen.
- `PendaftaranPasien`, `PendaftaranKunjungan` — validasi dan pembuatan data pasien/kunjungan.
- `PemeriksaanKlinis` — vital, SOAP, diagnosa, penyelesaian kunjungan, koreksi berjejak.
- `PencariTarif`, `TindakanPelayanan` — tarif per penjamin dan penyalinannya ke tindakan.
- `PenyusunTagihan`, `ProsesPembayaran` — penyusunan tagihan dan pembayaran.
- `PencariHargaObat`, `PenerimaanObat` — harga obat per penjamin dan penerimaan batch.
- `PenyiapanResep`, `PenyerahanObat`, `PenyesuaianStok` — alokasi FEFO, penyerahan obat, koreksi opname.
- `PemesananLab`, `PemeriksaanLaboratorium`, `PenandaNilai` — order lab, alur sampel sampai validasi, penandaan nilai abnormal.

Sejak Fase 3, seluruh harga tinggal di satu tabel `tarif` berkolom jenis layanan,
dan `tagihan_detail` menyimpan sumbernya secara polimorfik. Dengan begitu rincian
biaya satu kunjungan bisa dijumlahkan per jenis layanan dalam satu query — bentuk
yang dibutuhkan modul klaim pada fase berikutnya.

Data klinis tidak pernah dihapus keras (soft delete), dan seluruh perubahan pada
`pasien`, `kunjungan`, `pemeriksaan`, `diagnosa`, serta `tagihan` tercatat di
`audit_logs` beserta pelakunya — termasuk alasan bila berupa koreksi.
