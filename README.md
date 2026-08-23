# SIMRS — Sistem Informasi Manajemen Rumah Sakit

Aplikasi Laravel untuk RS Sampel. Dikembangkan bertahap;
**Fase 1 (Fondasi + Rawat Jalan)** dan **Fase 2 (Farmasi)** sudah selesai, mencakup
satu alur utuh dari pasien mendaftar sampai obatnya diserahkan apotek dan
tagihannya diselesaikan kasir.

Tahap ini memakai **data dummy**. Belum ada migrasi data pasien nyata dan belum ada
integrasi ke sistem eksternal (BPJS VClaim, SATUSEHAT) — keduanya masuk fase berikutnya.

## Dokumen

- Spesifikasi desain: [`docs/superpowers/specs/2026-08-18-simrs-fase1-rawat-jalan-design.md`](docs/superpowers/specs/2026-08-18-simrs-fase1-rawat-jalan-design.md)
- Rencana implementasi: [`docs/superpowers/plans/2026-08-18-simrs-fase1-rawat-jalan.md`](docs/superpowers/plans/2026-08-18-simrs-fase1-rawat-jalan.md)
- Spesifikasi Fase 2 (Farmasi): [`docs/superpowers/specs/2026-08-18-simrs-fase2-farmasi-design.md`](docs/superpowers/specs/2026-08-18-simrs-fase2-farmasi-design.md)
- Rencana Fase 2: [`docs/superpowers/plans/2026-08-18-simrs-fase2-farmasi.md`](docs/superpowers/plans/2026-08-18-simrs-fase2-farmasi.md)

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
| Display antrian | Halaman publik tanpa login untuk layar ruang tunggu |

Fase berikutnya: laboratorium dan radiologi → rawat inap → klaim & pelaporan
(BPJS, INA-CBG, SATUSEHAT) → inventori, SDM, akuntansi.

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

Data klinis tidak pernah dihapus keras (soft delete), dan seluruh perubahan pada
`pasien`, `kunjungan`, `pemeriksaan`, `diagnosa`, serta `tagihan` tercatat di
`audit_logs` beserta pelakunya — termasuk alasan bila berupa koreksi.
