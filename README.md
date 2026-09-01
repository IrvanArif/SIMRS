# SIMRS — Sistem Informasi Manajemen Rumah Sakit

Aplikasi Laravel untuk RS Sampel. Dikembangkan bertahap;
**Fase 1 (Fondasi + Rawat Jalan)** sampai **Fase 6 (Klaim dan Pelaporan)** sudah
selesai, mencakup satu alur utuh dari pasien mendaftar, diperiksa, menunggu hasil
laboratorium dan pencitraan, menginap bila perlu, menerima obat, sampai tagihannya
diselesaikan kasir — lalu diklaimkan ke penjamin dan dilaporkan ke manajemen.

Tahap ini memakai **data dummy**. Belum ada migrasi data pasien nyata dan belum ada
integrasi ke sistem eksternal (BPJS VClaim, SATUSEHAT) — keduanya masuk fase berikutnya.

## Dokumen

- Spesifikasi desain: [`docs/superpowers/specs/2026-08-18-simrs-fase1-rawat-jalan-design.md`](docs/superpowers/specs/2026-08-18-simrs-fase1-rawat-jalan-design.md)
- Rencana implementasi: [`docs/superpowers/plans/2026-08-18-simrs-fase1-rawat-jalan.md`](docs/superpowers/plans/2026-08-18-simrs-fase1-rawat-jalan.md)
- Spesifikasi Fase 2 (Farmasi): [`docs/superpowers/specs/2026-08-18-simrs-fase2-farmasi-design.md`](docs/superpowers/specs/2026-08-18-simrs-fase2-farmasi-design.md)
- Rencana Fase 2: [`docs/superpowers/plans/2026-08-18-simrs-fase2-farmasi.md`](docs/superpowers/plans/2026-08-18-simrs-fase2-farmasi.md)
- Spesifikasi Fase 3 (Laboratorium): [`docs/superpowers/specs/2026-08-18-simrs-fase3-laboratorium-design.md`](docs/superpowers/specs/2026-08-18-simrs-fase3-laboratorium-design.md)
- Rencana Fase 3: [`docs/superpowers/plans/2026-08-18-simrs-fase3-laboratorium.md`](docs/superpowers/plans/2026-08-18-simrs-fase3-laboratorium.md)
- Spesifikasi Fase 4 (Radiologi): [`docs/superpowers/specs/2026-08-18-simrs-fase4-radiologi-design.md`](docs/superpowers/specs/2026-08-18-simrs-fase4-radiologi-design.md)
- Rencana Fase 4: [`docs/superpowers/plans/2026-08-18-simrs-fase4-radiologi.md`](docs/superpowers/plans/2026-08-18-simrs-fase4-radiologi.md)
- Spesifikasi Fase 5 (Rawat Inap): [`docs/superpowers/specs/2026-08-18-simrs-fase5-rawat-inap-design.md`](docs/superpowers/specs/2026-08-18-simrs-fase5-rawat-inap-design.md)
- Rencana Fase 5: [`docs/superpowers/plans/2026-08-18-simrs-fase5-rawat-inap.md`](docs/superpowers/plans/2026-08-18-simrs-fase5-rawat-inap.md)
- Spesifikasi Fase 6 (Klaim dan Pelaporan): [`docs/superpowers/specs/2026-08-18-simrs-fase6-klaim-pelaporan-design.md`](docs/superpowers/specs/2026-08-18-simrs-fase6-klaim-pelaporan-design.md)
- Rencana Fase 6: [`docs/superpowers/plans/2026-08-18-simrs-fase6-klaim-pelaporan.md`](docs/superpowers/plans/2026-08-18-simrs-fase6-klaim-pelaporan.md)
- Akun pengguna dan layar per peran: [`docs/akun-pengguna.md`](docs/akun-pengguna.md)

## Cakupan Fase 1

| Modul | Isi |
|---|---|
| Autentikasi & hak akses | 9 peran, ditegakkan lewat Policy (bukan sekadar menu tersembunyi) |
| Master data | Poli, dokter, jadwal, penjamin, tindakan, tarif per penjamin, ICD-10, obat |
| Pendaftaran | Cari/daftar pasien, buat kunjungan, nomor antrian per poli per hari, cetak karcis |
| Poli | Tanda vital oleh perawat, SOAP + diagnosa ICD-10 + tindakan + resep oleh dokter |
| Kasir | Tagihan otomatis, pembayaran tunai/debit/QRIS, cetak kuitansi |
| Rekam medis | Penelusuran, koreksi data berjejak, rekap kunjungan harian |
| Admin | Kelola pengguna, master data, penampil audit log |
| Farmasi | Stok per batch dengan kedaluwarsa, penyiapan resep beralokasi FEFO, penyerahan obat, kartu stok, penyesuaian opname |
| Laboratorium | Master pemeriksaan berparameter dan nilai rujukan per jenis kelamin, order dokter, pengambilan sampel, entri hasil berpenanda otomatis, validasi sebelum terbaca dokter |
| Radiologi | Master pemeriksaan lima modalitas beserta instruksi persiapan, order berindikasi klinis wajib, pelaksanaan pencitraan bernomor film oleh radiografer, ekspertise naratif oleh dokter |
| Rawat inap | Master ruang, kelas, dan bed; perintah rawat inap berindikasi wajib; papan bed; penempatan dan pemindahan berpenggal; catatan perkembangan terintegrasi; pemulangan berdiagnosa akhir; biaya kamar per penggal |
| Klaim | SEP di balik antarmuka yang bisa diganti, master ICD-9-CM, penyusunan berkas klaim berpemeriksaan kelengkapan, pengajuan dan verifikasi, ekspor CSV |
| Pelaporan | Indikator BOR/LOS/TOI/BTO, sepuluh besar penyakit, pendapatan per penjamin, rekap kunjungan per poli |
| Navigasi | Menu per peran disusun dari satu daftar, dengan test yang membuktikan tiap tautan benar-benar bisa dibuka pemiliknya |
| Display antrian | Halaman publik tanpa login untuk layar ruang tunggu |

Fase berikutnya: inventori non-obat, SDM, dan akuntansi. Integrasi sungguhan ke
BPJS VClaim dan SATUSEHAT menunggu kredensial — batas antarmukanya sudah siap.

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

## Klaim dan pelaporan

**Tiga batasan diambil di muka, dan ketiganya disengaja.**

**Pengelompokan INA-CBG tidak ditiru.** Ia dikerjakan *grouper* resmi Kemenkes —
perangkat lunak berlisensi dengan bobot yang diperbarui berkala. Menirunya di
sini akan menghasilkan angka rupiah yang salah dan terlihat meyakinkan, yang jauh
lebih berbahaya daripada tidak ada angka. Yang dikerjakan SIMRS adalah menyusun
berkas klaim yang lengkap lalu mengekspornya; pengelompokannya terjadi di grouper.

**Integrasi BPJS VClaim berhenti di batas antarmuka.** Ia menuntut kredensial
terdaftar yang hanya bisa diuji dengan kredensial itu; menulis klien HTTP yang
tak pernah bisa dijalankan lalu menyebutnya selesai adalah kebohongan yang rapi.
Penerbitan SEP disembunyikan di balik antarmuka `PenerbitSep`, dengan satu
penerapan yang benar-benar berjalan — `SepLokal`. Tiap SEP menyimpan penerbitnya
(`lokal`) supaya nomor hasil simulasi tidak pernah tertukar dengan nomor sungguhan.
Apa yang harus dikerjakan penerapan VClaim dirinci di spec Fase 6 bagian 7, dan
satu test mengikat janjinya: ia mengikat ulang antarmukanya ke penerapan ganda
dan membuktikan seluruh alur tetap bekerja tanpa satu baris pun berubah.

**Kode prosedur ICD-9-CM ditambahkan** karena klaim mustahil tanpanya. Tindakan
yang tidak punya padanan — konsultasi dan administrasi memang tidak punya — tidak
menggagalkan klaim, tetapi dicatat sebagai peringatan pada berkasnya.

```
Admisi terbitkan SEP     di awal kunjungan; tanpa itu pelayanan tidak terjamin
Pelayanan berjalan       seperti biasa
Kunjungan/masa rawat selesai
Rekam medis susun klaim  kelengkapannya diperiksa saat penyusunan
Ajukan                   yang sudah diajukan tidak bisa disunting
Catat hasil verifikasi   disetujui, atau ditolak dengan catatan
Ekspor CSV               diunggah ke aplikasi BPJS di luar sistem ini
```

Kelengkapan diperiksa **saat penyusunan**, dan seluruh kekurangan dilaporkan
sekaligus — penolakan yang menyebut satu kekurangan lalu kekurangan berikutnya
memaksa petugas bolak-balik, dan berkas yang ditolak verifikator berminggu-minggu
kemudian jauh lebih mahal.

**Empat laporan**, seluruhnya dari data yang sudah ada:

| Indikator | Arti |
|---|---|
| BOR | seberapa penuh bangsalnya |
| LOS | rata-rata lama dirawat |
| TOI | rata-rata bed menganggur |
| BTO | berapa kali satu bed berganti pasien |

Keempatnya bisa dihitung tepat karena okupansi disimpan berpenggal: hari rawat
diambil dari irisan tiap penggal dengan periodenya. Pasien yang masuk sebelum
periode dan pulang sesudahnya hanya menyumbang hari di dalam periode — tanpa
pemotongan itu, BOR bisa melampaui 100%. Bed nonaktif tidak dihitung sebagai
kapasitas: bed rusak bukan kapasitas.

Laporan pendapatan memisahkan yang lunas, yang menunggu kasir, dan yang
ditanggung penjamin. Pemisahan itu bukan kerapian: menjumlahkan seluruh tagihan
sebagai pendapatan membuat manajemen mengira punya uang yang sebenarnya masih
piutang klaim.

## Alur rawat inap

**Rawat inap bukan kunjungan baru — ia menempel pada kunjungan yang sudah ada.**
Pasien mendaftar di poli seperti biasa, dokter memeriksa, lalu memerintahkan
rawat inap. Kunjungan itu tidak ditutup; ia tetap terbuka sampai pasien pulang.

Keputusan itu membuat tindakan, resep, laboratorium, dan radiologi langsung
bekerja untuk pasien rawat inap tanpa satu baris perubahan pun, karena semuanya
memang sudah bergantung pada kunjungan. Satu masa rawat pun menghasilkan satu
tagihan yang memuat kamar sekaligus seluruh layanannya.

```
Dokter memerintahkan   indikasi rawat wajib; kelas yang diminta dicatat
Admisi menempatkan     bed dikunci; kunjungan menjadi "dalam perawatan"
Perawat & dokter       catatan perkembangan SOAP, sebanyak yang diperlukan
Pindah bed bila perlu  penggal lama ditutup, penggal baru dibuka
Dokter memulangkan     diagnosa akhir dan cara pulang wajib; bed dilepas
Kasir menyelesaikan    tagihan memuat kamar, tindakan, obat, lab, radiologi
```

**Satu bed satu pasien, dijamin basis data.** Kolom `bed.rawat_inap_id` bersifat
unik, jadi dua pasien mustahil menempati satu bed sekalipun dua petugas menekan
tombol pada milidetik yang sama. Penguncian baris melindungi dari balapan;
batasan uniklah yang menolak jalur tulis yang belum terbayang.

**Okupansi disimpan berpenggal, bukan sebagai satu penunjuk.** Alasannya bukan
kerapian melainkan ketepatan tagihan: pasien yang pindah dari VIP ke Kelas 2 di
hari ketiga harus ditagih dua tarif berbeda. Penggal berupa selang setengah
terbuka `[mulai, selesai)`, sehingga jumlah hari seluruh penggal persis sama
dengan lama rawatnya — tanpa itu, satu hari hilang tanpa jejak setiap kali
pasien berpindah kamar.

Lama rawat dihitung dari selisih tanggal kalender, minimal satu hari: kamar yang
dipakai setengah hari tetap tidak bisa dijual ke orang lain hari itu.

**Aturan apotek dilonggarkan khusus untuk pasien rawat inap.** Aturan 30 menahan
obat sampai tagihan lunas — benar untuk pasien yang akan berjalan keluar pintu,
tetapi mustahil dipenuhi pasien yang tagihannya baru terbit saat ia pulang.
Obatnya diserahkan selama dirawat, dan biayanya dipungut saat tagihan disusun.

Biaya sementara bisa dibaca kapan saja selama pasien dirawat, dihitung dari
sumber yang sama dengan tagihan akhir supaya keduanya tidak bisa berselisih.

## Alur radiologi

Seperti laboratorium, kunjungan tertahan sampai hasilnya dibaca. Bedanya
hasil pencitraan bukan angka: tidak ada nilai rujukan dan tidak ada penanda
otomatis — yang menentukan artinya adalah kalimat dokter.

```
Dokter memesan       indikasi klinis wajib diisi; tanpa itu pasien menerima
                     radiasi tanpa alasan yang tercatat
Radiografer          menandai pencitraan dikerjakan disertai nomor film;
                     tanpa nomor itu citranya tidak bisa ditemukan lagi
Dokter radiologi     menulis temuan dan kesan (saran opsional)
                     baru sejak titik ini hasilnya terbaca dokter pengirim
Dokter pengirim      menutup kunjungan; tagihan memuat biaya radiologi
```

**Radiografer dan dokter dipisah dengan sengaja.** Radiografer mengoperasikan
alat dan mengarsipkan citranya; menyimpulkan apa arti citra itu adalah tindakan
medis, jadi hanya pengguna berperan `dokter` yang boleh menulis ekspertise.
Keduanya tercatat terpisah pada ordernya (`dikerjakan_oleh` dan `ditulis_oleh`),
sehingga saat hasilnya dipersoalkan di kemudian hari jelas siapa mengerjakan apa.

Bacaan yang sudah ditulis tidak bisa ditimpa diam-diam: perubahannya wajib
melewati koreksi beralasan yang tercatat di audit log, dan barisnya dikunci
selama transaksi supaya dua dokter tidak bisa saling menimpa.

Order yang dibatalkan sebelum dikerjakan tidak ditagihkan; yang dibatalkan
setelah dikerjakan tetap ditagihkan, karena film dan waktu alatnya sudah terpakai.

**Citra tidak disimpan sebagai berkas.** Yang dicatat hanya nomor film dan
lokasi arsipnya. Menyimpan dan menampilkan citra DICOM adalah pekerjaan PACS,
proyek tersendiri di luar cakupan SIMRS ini.

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
| `radiografer@rs.test` | Radiografer | Antrean radiologi, tandai pencitraan dikerjakan |
| `dokterradiologi@rs.test` | Dokter | Tulis dan koreksi ekspertise radiologi |
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
- `PemesananRadiologi`, `PelaksanaanRadiologi`, `PenulisanEkspertise` — order radiologi, pelaksanaan pencitraan, penulisan dan koreksi ekspertise.
- `PerintahRawatInap`, `PenempatanBed`, `CatatanHarian`, `PemulanganPasien`, `PenghitungBiayaKamar` — perintah rawat inap, okupansi bed berpenggal, catatan perkembangan, pemulangan, dan biaya kamar.
- `PenerbitanSep`, `PenyusunBerkasKlaim`, `EksporKlaim` — SEP, berkas klaim, dan ekspornya. `App\Kontrak\PenerbitSep` adalah batas ke penerbit luar; `SepLokal` penerapan bawaannya.
- `IndikatorRawatInap`, `LaporanMorbiditas`, `LaporanPendapatan`, `LaporanKunjungan`, `RentangTanggal` — keempat laporan beserta penjagaan rentang tanggalnya.

Sejak Fase 3, seluruh harga tinggal di satu tabel `tarif` berkolom jenis layanan,
dan `tagihan_detail` menyimpan sumbernya secara polimorfik. Dengan begitu rincian
biaya satu kunjungan bisa dijumlahkan per jenis layanan dalam satu query — bentuk
yang dibutuhkan modul klaim pada fase berikutnya.

Data klinis tidak pernah dihapus keras (soft delete), dan seluruh perubahan pada
`pasien`, `kunjungan`, `pemeriksaan`, `diagnosa`, serta `tagihan` tercatat di
`audit_logs` beserta pelakunya — termasuk alasan bila berupa koreksi.
