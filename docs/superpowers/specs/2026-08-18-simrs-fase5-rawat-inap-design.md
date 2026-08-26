# Fase 5 — Rawat Inap

## 1. Masalah

Sampai Fase 4, sistem hanya melayani pasien yang datang dan pulang di hari yang
sama. Pasien yang harus menginap tidak punya tempat sama sekali: tidak ada bed,
tidak ada catatan harian, dan tarif kamar tidak pernah masuk tagihan.

Rawat inap bukan sekadar "rawat jalan yang lebih lama". Tiga hal berubah
mendasar:

- **Ada sumber daya terbatas yang diperebutkan** — bed. Dua pasien tidak boleh
  menempati satu bed, dan itu harus dijamin basis data, bukan sekadar niat baik.
- **Biaya bertambah selama pasien belum pulang.** Tagihan tidak bisa disusun di
  akhir pemeriksaan seperti rawat jalan; ia menunggu pasien pulang.
- **Catatan klinisnya berkala, bukan sekali.** Satu SOAP tidak cukup untuk
  rawat tujuh hari.

## 2. Keputusan arsitektur pokok

**Rawat inap bukan kunjungan baru. Ia menempel pada kunjungan yang sudah ada.**

Pasien mendaftar di poli seperti biasa, dokter memeriksa, lalu dokter
memerintahkan rawat inap. Kunjungan itu **tidak ditutup**; ia tetap terbuka
sampai pasien pulang.

Konsekuensinya besar dan seluruhnya menguntungkan:

- `tindakan_kunjungan`, `resep`, `order_lab`, dan `order_radiologi` sudah
  bergantung pada `kunjungan_id`. Semuanya langsung bekerja untuk pasien rawat
  inap **tanpa satu baris perubahan pun**.
- `tagihan` sudah satu banding satu dengan kunjungan. Satu masa rawat
  menghasilkan satu tagihan, memuat kamar, tindakan, obat, lab, dan radiologi
  sekaligus.
- Rekam medis pasien tetap satu untai; rawat inap tidak menjadi pulau terpisah.

Yang ditambahkan hanyalah tabel `rawat_inap` yang menunjuk kunjungan tersebut.
Kunjungan disebut rawat inap bila punya baris `rawat_inap` — tidak perlu kolom
penanda tambahan, dan tidak ada dua sumber kebenaran yang bisa berselisih.

> Kolom `jenis_kunjungan` yang sudah ada **bukan** tempatnya: isinya `baru`/`lama`,
> yang berarti pasien baru atau pasien lama, bukan jenis layanannya.

## 3. Model bed dan okupansi

```
Ruang (bangsal)  →  Bed
   nama, lantai       nomor, kelas (vip/1/2/3), status
```

Kelas melekat pada bed, bukan pada ruang: satu bangsal lazim memuat beberapa
kelas, dan tarif kamar mengikuti kelas.

**Okupansi disimpan sebagai riwayat, bukan sebagai satu penunjuk.** Tabel
`okupansi_bed` mencatat tiap penggal masa tempat: `rawat_inap_id`, `bed_id`,
`mulai`, `selesai`. Bed yang sedang ditempati adalah penggal dengan `selesai`
kosong.

Alasannya bukan kerapian, melainkan ketepatan tagihan: pasien yang pindah dari
VIP ke Kelas 2 di hari ketiga harus ditagih dua tarif berbeda. Satu kolom
`bed_id` pada `rawat_inap` akan membuang informasi itu diam-diam.

**Penjaminan di tingkat basis data.** Selain riwayat di atas, `bed` memegang
kolom `rawat_inap_id` yang nullable dan **unik**. Selama seorang pasien
menempatinya, kolom itu terisi; unik memastikan tidak ada pasien kedua yang bisa
masuk, sekalipun dua petugas menekan tombol pada saat yang sama.

## 4. Alur

```
Dokter poli memerintahkan rawat inap
        indikasi rawat wajib diisi; kelas yang diminta dicatat
Admisi menempatkan pasien di bed
        bed dikunci; kunjungan berpindah ke status dalam_perawatan
Perawat & dokter mengisi catatan perkembangan
        setiap hari, sebanyak yang diperlukan
Tindakan, resep, lab, radiologi        berjalan seperti biasa lewat kunjungan
Pindah bed (bila perlu)                penggal lama ditutup, penggal baru dibuka
Dokter memulangkan pasien
        diagnosa akhir dan cara pulang wajib; bed dilepas
Tagihan disusun                        kamar + seluruh layanan selama dirawat
Kasir menyelesaikan                    seperti rawat jalan
```

## 5. Perhitungan lama rawat dan tarif kamar

Lama rawat dihitung dari **selisih tanggal kalender**, dan masa rawat yang
masuk-pulang di hari yang sama tetap dihitung **satu hari** — pasien memakai
kamar itu, dan kamar itu tidak bisa dijual ke orang lain hari itu.

```
hari = maksimum(1, tanggal_pulang − tanggal_masuk)
```

Bila pasien pindah bed, tiap penggal dihitung sendiri dengan tarif kelasnya, dan
hari peralihan menjadi milik penggal yang **ditinggalkan** — kamar lama sudah
terpakai hari itu, kamar baru baru terpakai keesokan harinya. Tiap penggal
minimal satu hari.

Tarif kamar memakai tabel `tarif` yang sudah ada dengan `jenis_layanan`
`kamar` dan `layanan_id` menunjuk kelasnya. Tidak ada tabel harga baru.

## 6. Catatan perkembangan

Tabel `catatan_perkembangan` berbentuk SOAP, banyak baris per masa rawat,
masing-masing mencatat penulis dan waktunya.

Perawat dan dokter sama-sama boleh menulis, dan perannya dicatat pada barisnya.
Ini mencerminkan CPPT: satu berkas dibaca bersama, bukan dua buku terpisah yang
tidak pernah saling bertemu.

Catatan yang sudah tersimpan **tidak bisa disunting biasa** — sama seperti
rekam medis lain di sistem ini. Perubahannya lewat koreksi beralasan yang
tercatat di audit log.

## 7. Batasan yang sengaja diambil

- **Tidak ada penjadwalan perawat, visite, atau shift.** Itu manajemen SDM,
  bukan rekam medis.
- **Tidak ada gizi, laundry, atau permintaan makanan.** Unit penunjang
  non-klinis di luar cakupan.
- **Tidak ada ICU dengan pemantauan berkelanjutan.** Bed ICU boleh ada sebagai
  kelas, tetapi pemantauan tanda vital per jam adalah proyek tersendiri.
- **Uang muka (deposit) belum ada.** Ia mempengaruhi arus kas, bukan kebenaran
  tagihan; menyusul bila diperlukan.

## 8. Aturan bisnis

Melanjutkan penomoran spec Fase 4. Setiap aturan wajib punya test yang membuktikannya.

59. Perintah rawat inap hanya boleh diterbitkan pengguna berperan `dokter`, dan wajib menyertakan indikasi rawat.
60. Satu kunjungan hanya boleh punya satu masa rawat inap.
61. Perintah rawat inap tidak bisa diterbitkan pada kunjungan yang sudah selesai atau dibatalkan.
62. Satu bed hanya boleh ditempati satu pasien pada satu waktu, dijamin batasan unik di basis data.
63. Penempatan pasien pada bed yang sudah terisi ditolak, bahkan bila dua petugas melakukannya bersamaan.
64. Pasien yang sudah menempati bed tidak bisa ditempatkan lagi tanpa dipulangkan atau dipindahkan lebih dulu.
65. Pindah bed menutup penggal okupansi lama dan membuka penggal baru; bed lama dilepas pada saat yang sama.
66. Catatan perkembangan wajib memuat keempat unsur SOAP dan mencatat penulis beserta perannya.
67. Koreksi catatan perkembangan wajib menyertakan alasan dan tercatat di audit log.
68. Pemulangan wajib menyertakan diagnosa akhir dan cara pulang.
69. Pasien tidak bisa dipulangkan selama masih ada order laboratorium atau radiologi yang belum selesai.
70. Pemulangan melepaskan bed sehingga bisa ditempati pasien berikutnya.
71. Lama rawat dihitung dari selisih tanggal kalender, minimal satu hari.
72. Tarif kamar dihitung per penggal okupansi menurut kelas bed yang ditempati saat itu.
73. Tagihan rawat inap memuat biaya kamar bersama seluruh tindakan, obat, lab, dan radiologi selama dirawat.
74. Kunjungan yang sedang dirawat inap tidak bisa diselesaikan lewat alur rawat jalan; penutupnya adalah pemulangan.
75. Rincian biaya sementara bisa dilihat kapan saja selama pasien dirawat, tanpa membuat tagihan.

## 9. Penanganan kesalahan

- **Menempatkan pasien di bed terisi** ditolak dengan pesan yang menyebut nomor bednya, bukan penolakan tanpa keterangan.
- **Dua petugas menempatkan pasien di bed yang sama** dicegah penguncian baris di dalam transaksi, dengan batasan unik sebagai jaring pengaman terakhir.
- **Memulangkan pasien saat hasil penunjang belum keluar** ditolak dengan pesan yang menyebut nomor ordernya.
- **Memulangkan tanpa diagnosa akhir atau cara pulang** ditolak sebelum apa pun tersimpan.
- **Menutup kunjungan rawat inap lewat layar poli** ditolak dengan pesan yang mengarahkan ke pemulangan.
- **Tarif kamar untuk suatu kelas belum diisi** memakai tarif penjamin `UMUM` dan mencatat peringatan, sama seperti layanan lain.

## 10. Pengujian

TDD dengan gaya yang sama. Test yang wajib ada, minimal:

- `test_perintah_rawat_inap_wajib_menyertakan_indikasi`
- `test_satu_kunjungan_hanya_boleh_punya_satu_masa_rawat`
- `test_bed_terisi_tidak_bisa_ditempati_pasien_lain`
- `test_batasan_unik_basis_data_menolak_okupansi_ganda`
- `test_pindah_bed_menutup_penggal_lama_dan_melepas_bednya`
- `test_catatan_perkembangan_wajib_soap_lengkap`
- `test_koreksi_catatan_wajib_beralasan`
- `test_pemulangan_wajib_diagnosa_akhir_dan_cara_pulang`
- `test_pasien_tidak_bisa_pulang_saat_hasil_penunjang_belum_keluar`
- `test_pemulangan_melepaskan_bed`
- `test_lama_rawat_sehari_saat_masuk_dan_pulang_di_tanggal_sama`
- `test_tarif_kamar_dihitung_per_penggal_menurut_kelasnya`
- `test_tagihan_rawat_inap_memuat_kamar_tindakan_obat_lab_dan_radiologi`
- `test_kunjungan_rawat_inap_tidak_bisa_ditutup_lewat_alur_poli`
- `test_rincian_sementara_tidak_membuat_tagihan`
