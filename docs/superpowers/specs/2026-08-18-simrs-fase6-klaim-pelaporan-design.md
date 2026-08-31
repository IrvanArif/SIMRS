# Fase 6 — Klaim dan Pelaporan

## 1. Masalah

Lima fase pertama membuat rumah sakit bisa melayani pasien. Fase ini membuat
rumah sakit bisa **dibayar** dan **dipertanggungjawabkan**.

Dua kebutuhan yang berbeda tetapi bersumber dari data yang sama:

- **Klaim.** Pasien BPJS tidak membayar di kasir; rumah sakit menagih ke BPJS.
  Tagihan yang sudah ada berstatus `ditanggung_penjamin` dan berhenti di situ —
  tidak ada satu pun berkas yang bisa dikirimkan.
- **Pelaporan.** Rumah sakit wajib melaporkan kegiatannya, dan manajemennya perlu
  tahu apakah bangsalnya terpakai, berapa lama pasien dirawat, penyakit apa yang
  paling sering datang, dan dari mana pendapatannya.

## 2. Tiga keputusan yang diambil di muka

Ketiganya membatasi cakupan secara tegas. Saya menuliskannya di depan supaya bisa
dibantah sebelum kodenya ditulis, bukan setelahnya.

### 2.1 Pengelompokan INA-CBG tidak ditiru

INA-CBG dikelompokkan oleh *grouper* resmi Kemenkes — perangkat lunak berlisensi
dengan aturan dan bobot yang diperbarui berkala. Menirunya di sini akan
menghasilkan **angka rupiah yang salah** dan terlihat meyakinkan, yang jauh lebih
berbahaya daripada tidak ada angka sama sekali.

Yang dikerjakan SIMRS adalah **menyusun berkas klaim yang lengkap dan sah** —
identitas, penjamin, diagnosa, prosedur, tanggal, dan biaya — lalu mengekspornya.
Pengelompokan dan penetapan tarifnya terjadi di grouper.

### 2.2 Integrasi BPJS VClaim dan SATUSEHAT berhenti di batas antarmuka

Keduanya menuntut kredensial terdaftar dan titik akhir yang hanya bisa diuji
dengan kredensial itu. Menulis klien HTTP yang tidak pernah bisa dijalankan lalu
menyebutnya selesai adalah kebohongan yang rapi.

Karena itu penerbitan SEP disembunyikan di balik antarmuka `PenerbitSep`, dengan
satu penerapan yang benar-benar berjalan: **`SepLokal`**, yang menerbitkan nomor
SEP sendiri secara deterministik dan aman balapan. Saat kredensial tersedia,
penerapan kedua yang memanggil VClaim ditambahkan **tanpa menyentuh satu pun
pemanggilnya**.

Bagian 7 memuat daftar tepat apa yang harus dikerjakan penerapan sungguhan itu.

### 2.3 Kode prosedur ICD-9-CM ditambahkan, karena klaim mustahil tanpanya

Sampai Fase 5, sistem hanya menyimpan diagnosa ICD-10. Klaim menuntut **kode
prosedur** juga. Tanpa itu berkas klaim tidak akan pernah lolos verifikasi, jadi
masternya ditambahkan di fase ini dan dipetakan ke tindakan yang sudah ada.

## 3. SEP — Surat Eligibilitas Peserta

SEP adalah bukti bahwa pasien berhak dijamin untuk pelayanan tertentu. Ia terbit
di awal, bukan di akhir: tanpa SEP, pelayanan pasien BPJS tidak terjamin.

```
Kunjungan pasien berpenjamin
        ↓
Admisi menerbitkan SEP     nomor, tanggal, jenis pelayanan, kelas rawat,
                           diagnosa awal, nomor rujukan
        ↓
Pelayanan berjalan         seperti biasa
        ↓
Pasien pulang / kunjungan selesai
        ↓
Berkas klaim disusun       merujuk SEP-nya
```

Satu kunjungan hanya boleh punya satu SEP yang berlaku. SEP bisa dibatalkan
dengan alasan, dan pembatalannya berjejak.

## 4. Berkas klaim

Berkas klaim disusun dari kunjungan yang **sudah selesai** dan **sudah punya
tagihan**. Isinya:

| Bagian | Sumber |
|---|---|
| Identitas peserta | `pasien`, `kunjungan.no_kartu_penjamin` |
| SEP | `sep` |
| Jenis pelayanan | ada tidaknya `rawat_inap` |
| Tanggal masuk dan pulang | `kunjungan`, `rawat_inap` |
| Kelas rawat | penggal okupansi terakhir |
| Diagnosa primer dan sekunder | `diagnosa` → ICD-10 |
| Prosedur | `tindakan_kunjungan` → ICD-9-CM |
| Rincian biaya | `tagihan_detail` |
| Dokter penanggung jawab | `kunjungan.dokter` |

**Kelengkapan diperiksa saat penyusunan, bukan saat pengiriman.** Berkas yang
kurang ditolak dengan menyebut apa yang kurang, karena berkas yang ditolak
verifikator BPJS berminggu-minggu kemudian jauh lebih mahal.

Status berkas: `draf` → `diajukan` → `disetujui` / `ditolak`. Yang sudah diajukan
tidak bisa disunting; perubahannya lewat pembatalan beralasan lalu penyusunan ulang.

## 5. Pelaporan

Empat laporan, seluruhnya dihitung dari data yang sudah ada.

### 5.1 Indikator rawat inap

Indikator baku yang dipakai rumah sakit Indonesia, kini bisa dihitung tepat
karena okupansi disimpan berpenggal:

| Indikator | Rumus | Arti |
|---|---|---|
| **BOR** | hari rawat ÷ (bed tersedia × hari periode) × 100% | seberapa penuh bangsalnya |
| **LOS** | hari rawat ÷ pasien keluar | rata-rata lama dirawat |
| **TOI** | (bed × hari − hari rawat) ÷ pasien keluar | rata-rata bed menganggur |
| **BTO** | pasien keluar ÷ bed tersedia | berapa kali satu bed berganti pasien |

Bed yang **nonaktif tidak dihitung sebagai bed tersedia** — bed rusak bukan
kapasitas.

### 5.2 Morbiditas — sepuluh besar penyakit

Diagnosa primer dikelompokkan per kode ICD-10 dalam suatu periode, dipisah antara
rawat jalan dan rawat inap karena keduanya menjawab pertanyaan berbeda.

### 5.3 Pendapatan per penjamin

Total tagihan per penjamin per periode, dipisah antara yang sudah lunas, yang
menunggu kasir, dan yang ditanggung penjamin. Yang ditanggung penjamin bukan
uang yang sudah diterima — ia piutang sampai klaimnya dibayar.

### 5.4 Rekapitulasi kunjungan

Jumlah kunjungan per poli per periode, dipisah rawat jalan dan rawat inap.

## 6. Batasan yang sengaja diambil

- **Tidak ada pengiriman berkas otomatis.** Ekspor menghasilkan berkas; siapa
  yang mengunggahnya ke aplikasi BPJS adalah urusan proses kerja, bukan sistem ini.
- **Tidak ada rekonsiliasi pembayaran klaim.** Setelah klaim disetujui, pencocokan
  uang masuk adalah pekerjaan akuntansi — fase tersendiri.
- **Tidak ada laporan RL 1–5 lengkap.** Formatnya banyak dan berubah; empat
  laporan di atas yang benar-benar dipakai harian didahulukan.
- **Tidak ada FHIR/SATUSEHAT.** Lihat 2.2.

## 7. Apa yang harus dikerjakan penerapan VClaim sungguhan

Ditulis di sini supaya penerusnya tidak perlu menebak:

1. Autentikasi dengan `X-cons-id`, `X-timestamp`, dan `X-signature` (HMAC-SHA256
   atas `consId&timestamp`), serta `user_key`.
2. Badan jawaban terenkripsi AES-256-CBC dengan kunci turunan
   `consId + secretKey + timestamp`, lalu dikompres LZ-String.
3. `POST /SEP/2.0/insert` untuk menerbitkan, `DELETE /SEP/2.0/delete` untuk membatalkan.
4. Galat dikembalikan dengan `metaData.code` selain `200` — kodenya harus
   diteruskan apa adanya ke pengguna, bukan diringkas menjadi "gagal".
5. Seluruh panggilan menuntut waktu tunggu dan penanganan kegagalan jaringan;
   kegagalan **tidak boleh** membuat pelayanan pasien terhenti.

Antarmuka `PenerbitSep` sudah berbentuk demikian sehingga penerapannya tinggal
mengisi, dan `SepLokal` tetap dipakai di lingkungan pengembangan.

## 8. Aturan bisnis

Melanjutkan penomoran spec Fase 5. Setiap aturan wajib punya test yang membuktikannya.

78. SEP hanya bisa diterbitkan untuk kunjungan berpenjamin, bukan pasien umum.
79. Satu kunjungan hanya boleh punya satu SEP yang berlaku, dijamin batasan basis data.
80. SEP wajib menyertakan nomor kartu peserta dan diagnosa awal.
81. Jenis pelayanan SEP mengikuti kenyataan: rawat inap bila ada masa rawat, selain itu rawat jalan.
82. Pembatalan SEP wajib beralasan dan tercatat di audit log.
83. Berkas klaim hanya bisa disusun dari kunjungan yang sudah selesai dan sudah bertagihan.
84. Berkas klaim wajib memuat SEP, diagnosa primer, dan sedikitnya satu prosedur atau satu baris biaya.
85. Kelengkapan berkas diperiksa saat penyusunan, dan penolakannya menyebut apa yang kurang.
86. Berkas yang sudah diajukan tidak bisa disunting; perubahannya lewat pembatalan beralasan.
87. Satu kunjungan hanya boleh punya satu berkas klaim yang berlaku.
88. Kode prosedur ICD-9-CM diambil dari pemetaan tindakan; tindakan tanpa pemetaan tidak menggagalkan klaim, tetapi dicatat sebagai peringatan.
89. BOR, LOS, TOI, dan BTO dihitung hanya dari bed yang aktif.
90. Laporan morbiditas memisahkan rawat jalan dan rawat inap.
91. Laporan pendapatan memisahkan yang lunas, yang menunggu kasir, dan yang ditanggung penjamin.
92. Seluruh laporan menerima rentang tanggal, dan rentang terbalik ditolak.

## 9. Penanganan kesalahan

- **Menerbitkan SEP untuk pasien umum** ditolak dengan pesan yang menyebut penjaminnya.
- **Menyusun klaim dari kunjungan yang belum selesai** ditolak dengan pesan yang menyebut statusnya.
- **Berkas kurang lengkap** ditolak dengan daftar tepat apa yang kurang, bukan "data tidak lengkap".
- **Menyunting berkas yang sudah diajukan** ditolak dengan pesan yang mengarahkan ke pembatalan.
- **Rentang tanggal terbalik** ditolak sebelum satu kueri pun berjalan.
- **Periode tanpa satu pun bed aktif** menghasilkan indikator bernilai nol, bukan pembagian dengan nol.

## 10. Pengujian

TDD dengan gaya yang sama. Test yang wajib ada, minimal:

- `test_sep_tidak_bisa_diterbitkan_untuk_pasien_umum`
- `test_satu_kunjungan_hanya_boleh_punya_satu_sep_berlaku`
- `test_sep_wajib_menyertakan_nomor_kartu_dan_diagnosa_awal`
- `test_jenis_pelayanan_sep_mengikuti_ada_tidaknya_rawat_inap`
- `test_pembatalan_sep_wajib_beralasan_dan_tercatat`
- `test_klaim_tidak_bisa_disusun_dari_kunjungan_yang_belum_selesai`
- `test_klaim_menolak_berkas_tanpa_diagnosa_primer_dan_menyebutkan_kekurangannya`
- `test_klaim_memuat_prosedur_icd9_dari_pemetaan_tindakan`
- `test_tindakan_tanpa_pemetaan_icd9_tidak_menggagalkan_klaim`
- `test_berkas_yang_sudah_diajukan_tidak_bisa_disunting`
- `test_bor_dihitung_hanya_dari_bed_aktif`
- `test_indikator_bernilai_nol_saat_tidak_ada_bed_aktif`
- `test_los_dihitung_dari_pasien_yang_sudah_keluar`
- `test_laporan_morbiditas_memisahkan_rawat_jalan_dan_rawat_inap`
- `test_laporan_pendapatan_memisahkan_lunas_menunggu_dan_ditanggung_penjamin`
- `test_rentang_tanggal_terbalik_ditolak`
