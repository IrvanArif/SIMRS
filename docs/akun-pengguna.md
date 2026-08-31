# Akun Pengguna SIMRS

Daftar akun yang dibuat `PenggunaSeeder` beserta layar yang bisa dibuka
masing-masing. Sesudah masuk, menu per peran sudah tersedia di beranda dan di
bilah navigasi; alamat di bawah dicantumkan sebagai rujukan, bukan karena itu
satu-satunya cara mencapainya.

> **Semua akun di bawah adalah akun demo berisi data dummy.** Kata sandinya
> seragam dan tertulis terang di berkas ini. Jangan pakai satu pun di lingkungan
> yang memuat data pasien sungguhan. Bagian [Sebelum dipakai sungguhan](#sebelum-dipakai-sungguhan)
> menjelaskan apa yang harus diubah lebih dulu.

Kata sandi seluruh akun: **`rahasia123`**

Alamat masuk: `/masuk` (mis. `http://localhost/SIMRS/masuk`)

## Daftar akun

| Email | Nama | Peran | Tugas |
|---|---|---|---|
| `admisi@rs.test` | Petugas Admisi | admisi | Daftar pasien, buat kunjungan, cetak karcis |
| `perawat@rs.test` | Perawat Poli | perawat | Tanda vital pasien di poli |
| `dokter@rs.test` | dr. Andi Wijaya | dokter | SOAP, diagnosa, tindakan, resep, order lab & radiologi |
| `dokterradiologi@rs.test` | Dokter Radiologi | dokter | Tulis dan koreksi ekspertise radiologi |
| `analis@rs.test` | Analis Laboratorium | analis | Ambil sampel, entri hasil, validasi |
| `radiografer@rs.test` | Radiografer | radiografer | Kerjakan pencitraan, catat nomor film |
| `apoteker@rs.test` | Apoteker | apoteker | Siapkan resep, serahkan obat, kelola stok |
| `kasir@rs.test` | Kasir Rawat Jalan | kasir | Proses pembayaran, cetak kuitansi |
| `rekammedis@rs.test` | Petugas Rekam Medis | rekam_medis | Telusur rekam medis, koreksi berjejak, rekap harian |
| `admin@rs.test` | Administrator | admin | Master data, kelola pengguna, audit log |

`dokter@rs.test` tertaut ke satu dokter poli (`dokter_id`), sehingga ia hanya
boleh memeriksa kunjungan yang memang dijadwalkan padanya.
`dokterradiologi@rs.test` sengaja tidak tertaut: yang membaca film tidak
memegang poli rawat jalan, dan yang menentukan kewenangannya adalah perannya.

## Layar per peran

### Admisi — `admisi@rs.test`

| Layar | Alamat |
|---|---|
| Cari pasien | `/pendaftaran/pasien` |
| Pasien baru | `/pendaftaran/pasien/baru` |
| Ubah data pasien | `/pendaftaran/pasien/{id}/ubah` |
| Buat kunjungan | `/pendaftaran/kunjungan/{id_pasien}` |
| Papan antrian | `/pendaftaran/antrian` |
| Cetak karcis | `/cetak/karcis/{id_antrian}` |
| Papan bed rawat inap | `/rawat-inap/papan` |
| Tempatkan pasien di bed | `/rawat-inap/tempatkan/{id_rawat_inap}` |

### Perawat — `perawat@rs.test`

| Layar | Alamat |
|---|---|
| Antrian poli | `/poli/antrian` |
| Tanda vital | `/poli/vital/{id_kunjungan}` |
| Papan bed rawat inap | `/rawat-inap/papan` |
| Perawatan pasien rawat inap | `/rawat-inap/rawat/{id_rawat_inap}` |

### Dokter — `dokter@rs.test`

| Layar | Alamat |
|---|---|
| Antrian poli | `/poli/antrian` |
| SOAP, diagnosa, tindakan, order lab & radiologi | `/poli/soap/{id_kunjungan}` |
| Resep | `/poli/resep/{id_kunjungan}` |
| Antrean radiologi | `/radiologi/antrean` |
| Tulis ekspertise | `/radiologi/ekspertise/{id_order}` |

Layar SOAP hanya bisa dibuka untuk kunjungan yang **masih berjalan**. Kunjungan
yang sudah ditutup membalas 403 — rekam medis yang selesai tidak bisa disunting
biasa, koreksinya lewat layar rekam medis yang mewajibkan alasan.

### Dokter radiologi — `dokterradiologi@rs.test`

| Layar | Alamat |
|---|---|
| Antrean radiologi | `/radiologi/antrean` (pilih status **Menunggu Ekspertise**) |
| Tulis / koreksi ekspertise | `/radiologi/ekspertise/{id_order}` |

### Analis — `analis@rs.test`

| Layar | Alamat |
|---|---|
| Antrean laboratorium | `/lab/antrean` |
| Ambil sampel | `/lab/sampel/{id_order}` |
| Entri hasil | `/lab/hasil/{id_order}` |
| Validasi | `/lab/validasi/{id_order}` |

### Radiografer — `radiografer@rs.test`

| Layar | Alamat |
|---|---|
| Antrean radiologi | `/radiologi/antrean` |
| Kerjakan pencitraan | `/radiologi/kerjakan/{id_order}` |

Radiografer **tidak** bisa membuka layar ekspertise. Menyimpulkan temuan
pencitraan adalah tindakan medis, bukan tugas petugas yang mengoperasikan alat.

### Apoteker — `apoteker@rs.test`

| Layar | Alamat |
|---|---|
| Antrean resep | `/apotek/antrean` |
| Siapkan resep | `/apotek/siapkan/{id_resep}` |
| Serahkan obat | `/apotek/serahkan/{id_resep}` |
| Penerimaan batch | `/apotek/penerimaan` |
| Kartu stok | `/apotek/kartu-stok/{id_obat}` |
| Peringatan stok | `/apotek/peringatan` |

### Kasir — `kasir@rs.test`

| Layar | Alamat |
|---|---|
| Daftar tagihan | `/kasir/tagihan` |
| Proses pembayaran | `/kasir/bayar/{id_tagihan}` |
| Cetak kuitansi | `/cetak/kuitansi/{id_pembayaran}` |
| Papan bed rawat inap | `/rawat-inap/papan` |

Kasir bisa membuka papan bed karena ia harus bisa menjelaskan rincian kamar pada
tagihan, tanpa berwenang mengubah apa pun di sana.

### Rekam medis — `rekammedis@rs.test`

| Layar | Alamat |
|---|---|
| Penelusuran rekam medis | `/rekam-medis/telusur` |
| Koreksi data pasien | `/rekam-medis/koreksi/{id_pasien}` |
| Rekap kunjungan harian | `/rekam-medis/rekap` |
| Papan bed rawat inap | `/rawat-inap/papan` |

### Admin — `admin@rs.test`

| Layar | Alamat |
|---|---|
| Master poli | `/master/poli` |
| Master dokter | `/master/dokter` |
| Master tindakan | `/master/tindakan` |
| Master tarif | `/master/tarif` |
| Master pemeriksaan radiologi | `/master/pemeriksaan-radiologi` |
| Master ruang dan bed | `/master/ruang-bed` |
| Kelola pengguna | `/admin/user` |
| Audit log | `/admin/audit` |

### Tanpa login

| Layar | Alamat |
|---|---|
| Halaman muka | `/` |
| Display antrian ruang tunggu | `/display/antrian` |

## Membuat ulang akunnya

```bash
php artisan migrate:fresh --seed     # basis data kosong, lalu seluruh data dummy
php artisan db:seed --class=PenggunaSeeder   # hanya akunnya
```

`PenggunaSeeder` **menolak berjalan di luar lingkungan `local` dan `testing`**.
Penjagaan itu ada supaya kata sandi seragam ini tidak pernah masuk ke server
sungguhan lewat perintah seed yang tidak sengaja terpanggil.

## Menu hanya menampilkan yang memang bisa dibuka

Menu disusun dari satu daftar di `app/Support/MenuNavigasi.php`, dan ada test
yang membuka **setiap tautan yang terlihat** oleh **setiap peran** untuk memastikan
jawabannya 200. Menu yang menampilkan tautan berujung 403 lebih buruk daripada
tidak ada menu: pengguna diundang ke pintu yang terkunci.

Kebalikannya juga diuji — yang disembunyikan memang ditolak, bukan sekadar
disembunyikan.

Satu perkecualian yang perlu diketahui: **dokter radiologi tidak mendapat tautan
poli maupun papan bed.** Ia berperan `dokter` tetapi tidak memegang poli, jadi
daftar pasien poli tidak berisi seorang pun yang bisa ia periksa.

## Hak akses ditegakkan di belakang layar, bukan di menu

Menyembunyikan tautan bukan pengamanan. Setiap layar dijaga dua lapis:

1. **Middleware peran** pada rutenya — mengetik alamat langsung tetap membalas 403.
2. **Policy** pada aksinya — mis. `OrderRadiologiPolicy::ekspertise` menolak
   radiografer meski ia entah bagaimana sampai ke layarnya.

Silakan uji sendiri: masuk sebagai `radiografer@rs.test`, lalu buka
`/kasir/tagihan`. Jawabannya 403.

## Sebelum dipakai sungguhan

Berkas ini menggambarkan **lingkungan demo**. Sebelum sistem menyentuh data
pasien sungguhan, yang berikut wajib dibereskan:

- **Ganti seluruh kata sandi.** Satu kata sandi untuk sepuluh akun hanya layak
  untuk demo. Setiap petugas harus punya akun dan kata sandi sendiri — audit log
  mencatat siapa melakukan apa, dan catatan itu tak ada artinya bila akunnya dipakai bersama.
- **Hapus akun demo yang tidak terpakai.** Akun bernama peran (`kasir@rs.test`)
  diganti akun bernama orang.
- **Nyalakan HTTPS.** Kata sandi yang melintas tanpa enkripsi bisa disadap di
  jaringan rumah sakit sendiri.
- **Setel `APP_ENV=production` dan `APP_DEBUG=false`.** Selain mematikan halaman
  galat yang membocorkan isi kode, penyetelan ini juga yang membuat
  `PenggunaSeeder` menolak berjalan dan menyembunyikan petunjuk kredensial di halaman muka.
- **Pertimbangkan kebijakan kata sandi dan penguncian akun** — panjang minimum,
  batas percobaan gagal, dan pengubahan berkala.

Sampai kelimanya selesai, sistem ini adalah purwarupa yang berjalan, bukan
sistem produksi.
