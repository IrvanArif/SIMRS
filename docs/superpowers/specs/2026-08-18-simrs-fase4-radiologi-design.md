# SIMRS — Desain Fase 4: Radiologi

Tanggal: 2026-08-18
Status: disetujui untuk dieksekusi
Penyusun: Irvan bersama Claude

## 1. Tujuan

Menambahkan radiologi ke alur rawat jalan: dokter memesan pemeriksaan pencitraan,
radiografer mengerjakannya, dokter radiologi menulis ekspertise, dan dokter pengirim
membaca hasilnya sebelum menutup kunjungan.

## 2. Ruang lingkup

**Termasuk:** peran radiografer; master pemeriksaan radiologi beserta modalitasnya;
pemesanan oleh dokter; pelaksanaan pencitraan berikut pencatatan nomor film; penulisan
ekspertise oleh dokter radiologi; pembacaan oleh dokter pengirim; pembebanan biaya ke
tagihan; penguncian penyelesaian kunjungan sampai ekspertise selesai; koreksi ekspertise
yang berjejak; layar radiografer dan dokter radiologi; seeder data contoh.

**Tidak termasuk:** penyimpanan dan penampilan citra (DICOM/PACS), integrasi alat
pencitraan, penjadwalan slot alat, dosis radiasi kumulatif pasien, dan radiologi
intervensi.

## 3. Keputusan yang mengikat

**Pola alur mengikuti laboratorium.** Kunjungan tertahan sampai hasil selesai; tarif
disalin saat order dibuat sedangkan biayanya masuk tagihan saat kunjungan diselesaikan;
hasil tidak terbaca dokter pengirim sebelum ekspertise ditulis; order yang dibatalkan
sebelum dikerjakan tidak ditagihkan, yang dibatalkan setelah dikerjakan tetap
ditagihkan karena film dan waktu alatnya sudah terpakai.

**Hasil berupa narasi, bukan angka.** Radiologi menghasilkan temuan dan kesan dalam
kalimat. Tidak ada parameter, tidak ada nilai rujukan, dan tidak ada penanda otomatis —
memaksakan penanda numerik pada bacaan radiologi akan salah kaprah.

**Dua peran yang berbeda pekerjaan.** `radiografer` mengerjakan pencitraan dan mencatat
nomor filmnya. **Ekspertise ditulis dokter radiologi**, memakai peran `dokter` yang sudah
ada dengan penugasan di poli Radiologi. Pembagian ini bukan formalitas: radiografer tidak
berwenang menyimpulkan temuan, dan dokter tidak boleh menyatakan pencitraan sudah
dikerjakan.

**Citra tidak disimpan sebagai berkas.** Sistem mencatat nomor film dan lokasi arsipnya.
Integrasi PACS adalah proyek tersendiri; menyimpan setengahnya di sini menambah beban
tanpa manfaat yang bisa dipakai.

## 4. Peran

Peran kesembilan: **`radiografer`**. Berwenang mengerjakan pemeriksaan pencitraan dan
mencatat nomor film. Tidak berwenang menulis ekspertise, mengisi rekam medis pasien,
menyiapkan obat, mengerjakan laboratorium, maupun menerima pembayaran.

Ekspertise ditulis pengguna berperan `dokter`. Master pemeriksaan radiologi dikelola
`admin`.

## 5. Model data

### Master

| Tabel | Kolom kunci |
|---|---|
| `pemeriksaan_radiologi` | `kode` (unik), `nama`, `modalitas` (`rontgen`, `usg`, `ct_scan`, `mri`, `mammografi`), `persiapan` (instruksi persiapan pasien, nullable), `aktif` |

### Transaksi

| Tabel | Kolom kunci |
|---|---|
| `order_radiologi` | `no_order` (unik), `kunjungan_id`, `dokter_id`, `status`, `indikasi_klinis`, `waktu_dikerjakan`, `dikerjakan_oleh`, `no_film`, `waktu_ekspertise`, `ditulis_oleh` |
| `order_radiologi_detail` | `order_radiologi_id`, `pemeriksaan_radiologi_id`, `tarif_satuan` — unik `(order_radiologi_id, pemeriksaan_radiologi_id)` |
| `ekspertise_radiologi` | `order_radiologi_detail_id` (unik), `temuan`, `kesan`, `saran` (nullable) |

`order_radiologi.status` bertipe enum `StatusOrderRadiologi`: `dipesan` → `dikerjakan` →
`selesai`, atau `batal`. Status `selesai` berarti ekspertise sudah ditulis.

## 6. Layanan

| Kelas | Tanggung jawab |
|---|---|
| `PemesananRadiologi` | Membuat order, menyalin tarif, membatalkan order |
| `PelaksanaanRadiologi` | Menandai pencitraan dikerjakan beserta nomor filmnya |
| `EkspertiseRadiologi` | Menulis dan mengoreksi bacaan dokter radiologi |

Perubahan pada kode yang sudah ada: `PemeriksaanKlinis::selesaikan()` juga menolak
selama kunjungan masih punya order radiologi yang belum selesai dan belum dibatalkan;
`PenyusunTagihan::susun()` ikut memasukkan baris radiologi.

## 7. Alur

```
Dokter memesan          order berstatus "dipesan"; tarif disalin saat itu juga
Radiografer mengerjakan "dikerjakan" beserta nomor film, waktu, dan pelakunya
Dokter radiologi        menulis temuan dan kesan; order berstatus "selesai"
                        dan hasilnya terbaca dokter pengirim
Dokter pengirim         membaca ekspertise, menutup kunjungan
```

## 8. Aturan bisnis

Melanjutkan penomoran spec Fase 3. Setiap aturan wajib punya test yang membuktikannya.

47. Order radiologi wajib memuat minimal satu pemeriksaan, dan satu pemeriksaan hanya boleh muncul sekali dalam satu order.
48. Order wajib menyertakan indikasi klinis — pencitraan tanpa indikasi berarti pasien menerima radiasi tanpa alasan yang tercatat.
49. Tarif disalin ke `order_radiologi_detail.tarif_satuan` saat order dibuat; biayanya masuk tagihan saat kunjungan diselesaikan.
50. Kunjungan tidak dapat diselesaikan selama masih ada order radiologi yang belum selesai dan belum dibatalkan.
51. Pencitraan hanya dapat ditandai dikerjakan pada order berstatus `dipesan`, dan wajib menyertakan nomor film.
52. Ekspertise hanya dapat ditulis setelah pencitraan dikerjakan.
53. Ekspertise wajib memuat temuan dan kesan; saran bersifat opsional.
54. Ekspertise hanya boleh ditulis pengguna berperan `dokter`; radiografer tidak berwenang menulisnya.
55. Hasil terbaca dokter pengirim hanya setelah ekspertise ditulis.
56. Koreksi ekspertise yang sudah ditulis wajib menyertakan alasan dan tercatat di audit log.
57. Order yang dibatalkan sebelum dikerjakan tidak ditagihkan; yang dibatalkan setelah dikerjakan tetap ditagihkan karena film dan waktu alatnya sudah terpakai.
58. Pembatalan order wajib menyertakan alasan.

## 9. Penanganan kesalahan

- **Menandai dikerjakan tanpa nomor film** ditolak dengan pesan yang menyebut nomor film sebagai syarat, bukan penolakan tanpa keterangan.
- **Menulis ekspertise sebelum pencitraan dikerjakan** ditolak dengan pesan yang menyebut tahap yang harus dilalui lebih dulu.
- **Dokter menyelesaikan kunjungan saat ekspertise belum ada** ditolak dengan pesan yang menyebut nomor order yang ditunggu.
- **Dua petugas mengerjakan order yang sama** dicegah dengan penguncian baris di dalam transaksi.
- **Koreksi ekspertise tanpa alasan** ditolak sebelum apa pun tersimpan.
- **Tarif radiologi belum diisi** memakai tarif penjamin `UMUM` dan mencatat peringatan, sama seperti layanan lain.

## 10. Pengujian

TDD dengan gaya yang sama. Test yang wajib ada, minimal:

- `test_order_radiologi_wajib_memuat_minimal_satu_pemeriksaan`
- `test_order_wajib_menyertakan_indikasi_klinis`
- `test_tarif_disalin_saat_order_dibuat`
- `test_pencitraan_wajib_menyertakan_nomor_film`
- `test_ekspertise_tidak_bisa_ditulis_sebelum_pencitraan_dikerjakan`
- `test_ekspertise_wajib_memuat_temuan_dan_kesan`
- `test_radiografer_tidak_bisa_menulis_ekspertise`
- `test_hasil_belum_terbaca_dokter_sebelum_ekspertise_ditulis`
- `test_kunjungan_tidak_bisa_diselesaikan_saat_ekspertise_belum_ada`
- `test_kunjungan_bisa_diselesaikan_setelah_ekspertise_ditulis`
- `test_koreksi_ekspertise_wajib_beralasan_dan_tercatat_di_audit`
- `test_biaya_radiologi_masuk_ke_tagihan_saat_kunjungan_diselesaikan`
- `test_order_yang_dibatalkan_sebelum_dikerjakan_tidak_ditagihkan`
- `test_order_yang_dibatalkan_setelah_dikerjakan_tetap_ditagihkan`
- `test_radiografer_tidak_bisa_membuka_layar_kasir`

## 11. Layar

**Radiografer:** antrean order (per status) · layar pelaksanaan berisi indikasi klinis,
instruksi persiapan, dan isian nomor film.

**Dokter radiologi:** antrean order yang menunggu ekspertise · layar penulisan ekspertise
berisi temuan, kesan, dan saran.

**Dokter pengirim:** pemesanan radiologi dari layar SOAP · pembacaan ekspertise yang
sudah ditulis.

**Admin:** master pemeriksaan radiologi.

## 12. Seeder

Dua belas pemeriksaan tersering di rawat jalan lintas modalitas (rontgen toraks, rontgen
abdomen, rontgen ekstremitas, rontgen panoramik gigi, USG abdomen, USG kandungan, USG
tiroid, CT scan kepala, CT scan toraks, CT scan abdomen, MRI kepala, mammografi) beserta
tarif kedua penjamin. Order dummy tersebar pada ketiga status supaya tiap layar punya isi.

## 13. Kriteria selesai

1. Seluruh test lulus dan setiap aturan di bagian 8 punya test yang membuktikannya.
2. Seluruh test Fase 1–3 tetap hijau.
3. Satu pasien dapat ditelusuri dari dokter memesan, radiografer mengerjakan, dokter radiologi menulis ekspertise, sampai kunjungan ditutup dan tagihannya memuat biaya radiologi.
4. Radiografer tidak dapat menulis ekspertise maupun mengakses layar klinis, apotek, laboratorium, dan kasir — dibuktikan oleh test.
5. `php artisan migrate:fresh --seed` menghasilkan radiologi yang langsung bisa didemokan.
