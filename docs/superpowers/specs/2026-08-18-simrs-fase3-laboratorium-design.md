# SIMRS — Desain Fase 3: Laboratorium

Tanggal: 2026-08-18
Status: disetujui, siap direncanakan
Penyusun: Irvan bersama Claude

## 1. Tujuan

Menambahkan laboratorium ke alur rawat jalan: dokter memesan pemeriksaan, analis
mengambil sampel dan mengentri hasilnya, hasil divalidasi sebelum terbaca dokter,
dan dokter menutup kunjungan setelah membacanya.

Radiologi — yang sempat digabung ke "Fase 3" pada peta awal — dipisah menjadi fase
tersendiri. Bentuk alurnya memang mirip, tetapi isi hasilnya berbeda jauh:
laboratorium menghasilkan angka bernilai rujukan, radiologi menghasilkan citra dan
bacaan naratif.

## 2. Prasyarat: penyatuan tarif dan sumber tagihan

Setelah Fase 2, sistem punya dua tabel harga (`tarif_tindakan`, `harga_obat`) dengan
dua layanan pencari yang nyaris kembar, dan `tagihan_detail` menandai sumbernya lewat
dua kolom nullable. Menambahkan laboratorium dengan pola yang sama menjadikannya tiga,
dan radiologi akan menjadikannya empat.

Penyatuan dikerjakan **sebelum** laboratorium, sebagai tugas tersendiri:

- Tabel `tarif` berkolom `jenis_layanan` (`tindakan`, `obat`, `lab`), `layanan_id`,
  `penjamin_id`, `harga`, `berlaku_mulai`, unik atas keempatnya. Isi `tarif_tindakan`
  dan `harga_obat` dimigrasikan ke sana, lalu kedua tabel lama dihapus.
- Satu `PencariTarif` melayani ketiga jenis layanan. `PencariHargaObat` dihapus.
- `tagihan_detail` mengganti dua kolom nullable dengan `sumber_tipe` dan `sumber_id`.

Aturan bisnis 1–34 tidak berubah sedikit pun, termasuk jatuh tempo ke penjamin `UMUM`
dan penyalinan tarif saat layanan dicatat. **Syarat lulus tugas ini: seluruh test yang
ada tetap hijau.** Test yang merah karena perilaku berubah berarti penyatuannya salah,
dan test itu tidak boleh dilonggarkan untuk menutupinya.

Alasan penyatuan bukan kerapian semata: modul klaim pada fase berikutnya harus membaca
seluruh komponen biaya satu kunjungan. Dengan tabel terpisah, itu berarti menggabungkan
empat sumber berbentuk berbeda; dengan tabel tunggal, satu query.

## 3. Ruang lingkup

**Termasuk:** peran analis; master pemeriksaan laboratorium beserta parameter dan
nilai rujukannya; pemesanan oleh dokter; pengambilan sampel; entri hasil; validasi;
penandaan otomatis nilai abnormal; pembebanan biaya ke tagihan kunjungan; penguncian
penyelesaian kunjungan sampai hasil keluar; koreksi hasil tervalidasi yang berjejak;
layar analis dan layar pembacaan hasil untuk dokter; seeder data contoh.

**Tidak termasuk:** radiologi, rawat inap, pemeriksaan yang dikirim ke laboratorium
rujukan luar, integrasi alat analyzer, pelacakan nomor spesimen berbarcode, dan
grafik tren hasil antar kunjungan.

## 4. Keputusan yang mengikat

**Pasien menunggu hasil.** Kunjungan tetap terbuka sampai seluruh order laboratorium
divalidasi, sehingga diagnosa dokter benar-benar berdasar hasil. Konsekuensinya kasir
ikut menunggu — mekanismenya sama dengan aturan 29 pada apotek, tetapi penjaganya
berada di penyelesaian kunjungan, bukan di pembayaran.

**Hasil berupa parameter bernilai rujukan per jenis kelamin.** Satu pemeriksaan punya
banyak parameter; tiap parameter punya satuan dan rentang rujukan yang boleh berbeda
untuk laki-laki dan perempuan. Rujukan tunggal akan menandai sebagian hasil secara
keliru — hemoglobin 13 g/dL normal bagi laki-laki tetapi tinggi bagi perempuan — dan
penanda yang kadang salah lebih berbahaya daripada tidak ada penanda.

**Empat tahap dengan validasi.** Hasil tidak sampai ke dokter sebelum divalidasi.
Salah ketik yang langsung terbaca dokter bisa mengubah diagnosa.

**Validasi boleh oleh orang yang sama dengan yang mengentri**, dengan kedua pelakunya
tetap dicatat. Melarangnya akan menghentikan pekerjaan lab yang shift malamnya hanya
punya satu analis; jejaknya tetap ada untuk ditelusuri bila terjadi masalah.

## 5. Peran

Peran kedelapan: **`analis`**. Berwenang mengambil sampel, mengentri hasil, dan
memvalidasi. Tidak berwenang mengisi rekam medis, menyiapkan obat, maupun menerima
pembayaran.

Master pemeriksaan, parameter, dan nilai rujukan dikelola `admin`.

## 6. Model data

### Master

| Tabel | Kolom kunci |
|---|---|
| `pemeriksaan_lab` | `kode` (unik), `nama`, `kategori` (`hematologi`, `kimia_klinik`, `urinalisis`, `imunologi`, `mikrobiologi`), `aktif` |
| `parameter_lab` | `pemeriksaan_lab_id`, `kode`, `nama`, `satuan`, `urutan` — unik `(pemeriksaan_lab_id, kode)` |
| `rujukan_lab` | `parameter_lab_id`, `jenis_kelamin` (`L`, `P`, `semua`), `nilai_min`, `nilai_maks` — unik `(parameter_lab_id, jenis_kelamin)` |

### Transaksi

| Tabel | Kolom kunci |
|---|---|
| `order_lab` | `no_order` (unik), `kunjungan_id`, `dokter_id`, `status`, `catatan_klinis`, `waktu_sampel`, `diambil_oleh`, `waktu_hasil`, `dientri_oleh`, `waktu_validasi`, `divalidasi_oleh` |
| `order_lab_detail` | `order_lab_id`, `pemeriksaan_lab_id`, `tarif_satuan` — unik `(order_lab_id, pemeriksaan_lab_id)` |
| `hasil_lab` | `order_lab_detail_id`, `parameter_lab_id`, `nilai` (decimal), `penanda` (`rendah`, `normal`, `tinggi`, atau kosong), `catatan` — unik `(order_lab_detail_id, parameter_lab_id)` |

`order_lab.status` bertipe enum `StatusOrderLab`: `dipesan` → `sampel_diambil` →
`hasil_dientri` → `divalidasi`, atau `batal`.

`hasil_lab.penanda` bertipe enum `PenandaHasil` yang boleh bernilai null bila tidak
ada rujukan yang cocok.

## 7. Layanan

| Kelas | Tanggung jawab |
|---|---|
| `PemesananLab` | Membuat order, menyalin tarif, membebankan ke tagihan |
| `PengambilanSampel` | Menandai sampel terambil beserta pelakunya |
| `EntriHasilLab` | Menyimpan nilai dan menghitung penandanya |
| `ValidasiHasilLab` | Melepas hasil agar terbaca dokter |
| `PenandaNilai` | Menentukan rendah/normal/tinggi dari rujukan sesuai jenis kelamin |

Perubahan pada kode yang sudah ada: `PemeriksaanKlinis::selesaikan()` menolak selama
kunjungan masih punya order laboratorium yang belum divalidasi dan belum dibatalkan.

## 8. Alur

```
Dokter memesan          order berstatus "dipesan"; tarif disalin dan biayanya
                        langsung masuk tagihan kunjungan
Analis ambil sampel     "sampel_diambil" beserta waktu dan pelakunya
Analis entri hasil      "hasil_dientri"; penanda dihitung sistem dari rujukan
                        sesuai jenis kelamin pasien
Validasi                "divalidasi"; sejak titik ini hasilnya terbaca dokter
Dokter membaca          menyelesaikan kunjungan
```

## 9. Aturan bisnis

Melanjutkan penomoran spec Fase 2. Setiap aturan wajib punya test yang membuktikannya.

35. Order laboratorium wajib memuat minimal satu pemeriksaan.
36. Biaya laboratorium ditambahkan ke tagihan kunjungan saat order dibuat, dengan tarif disalin ke `order_lab_detail.tarif_satuan`; perubahan master tarif tidak mengubah order lama.
37. Kunjungan tidak dapat diselesaikan selama masih ada order laboratorium yang belum divalidasi dan belum dibatalkan.
38. Hasil hanya dapat dientri setelah sampel dinyatakan terambil.
39. Nilai hasil wajib berupa angka.
40. Penanda rendah/normal/tinggi dihitung sistem dari rujukan sesuai jenis kelamin pasien, tidak diketik petugas.
41. Bila tidak ada rujukan untuk jenis kelamin pasien, dipakai rujukan `semua`. Bila itu pun tidak ada, penandanya dikosongkan dan kejadiannya dicatat ke log — tidak ditebak.
42. Hasil hanya terbaca dokter setelah divalidasi.
43. Validasi boleh dilakukan oleh petugas yang sama dengan yang mengentri hasil, dan kedua pelakunya wajib tercatat.
44. Hasil yang sudah divalidasi tidak dapat diubah langsung; koreksi wajib menyertakan alasan dan tercatat di audit log.
45. Pembatalan order sebelum sampel diambil mencabut biayanya dari tagihan.
46. Order yang sampelnya sudah diambil hanya dapat dibatalkan dengan alasan, dan biayanya tetap tertagih karena bahan serta waktu kerjanya sudah terpakai.

## 10. Penanganan kesalahan

- **Entri hasil sebelum sampel diambil** ditolak dengan pesan yang menyebut tahap yang harus dilalui lebih dulu.
- **Nilai bukan angka** ditolak per parameter, menyebut nama parameternya, bukan menolak seluruh formulir tanpa keterangan.
- **Parameter tanpa rujukan yang cocok** tidak menggagalkan entri; nilainya tersimpan tanpa penanda dan kejadiannya dicatat agar admin melengkapi master rujukan.
- **Dokter menyelesaikan kunjungan saat hasil belum keluar** ditolak dengan pesan yang menyebut nomor order yang ditunggu.
- **Dua analis memvalidasi order yang sama** dicegah dengan penguncian baris di dalam transaksi.
- **Koreksi hasil tervalidasi tanpa alasan** ditolak sebelum apa pun tersimpan.

## 11. Pengujian

TDD dengan gaya yang sama: test ditulis lebih dulu dan harus gagal sebelum
implementasinya ada, nama test berbahasa Indonesia.

Test yang wajib ada, minimal:

- `test_order_lab_wajib_memuat_minimal_satu_pemeriksaan`
- `test_biaya_lab_masuk_ke_tagihan_saat_order_dibuat`
- `test_perubahan_master_tarif_tidak_mengubah_order_yang_sudah_dibuat`
- `test_kunjungan_tidak_bisa_diselesaikan_saat_hasil_lab_belum_divalidasi`
- `test_kunjungan_bisa_diselesaikan_setelah_seluruh_order_divalidasi`
- `test_hasil_tidak_bisa_dientri_sebelum_sampel_diambil`
- `test_nilai_bukan_angka_ditolak_dengan_menyebut_parameternya`
- `test_penanda_rendah_normal_tinggi_dihitung_dari_rujukan`
- `test_rujukan_berbeda_untuk_laki_laki_dan_perempuan`
- `test_parameter_tanpa_rujukan_tersimpan_tanpa_penanda`
- `test_hasil_belum_divalidasi_tidak_terbaca_dokter`
- `test_validasi_oleh_petugas_yang_sama_dicatat_kedua_pelakunya`
- `test_koreksi_hasil_tervalidasi_wajib_beralasan_dan_tercatat_di_audit`
- `test_pembatalan_sebelum_sampel_mencabut_biaya_dari_tagihan`
- `test_pembatalan_setelah_sampel_tetap_menagihkan_biaya`
- `test_analis_tidak_bisa_membuka_form_soap`
- `test_dokter_tidak_bisa_mengentri_hasil_lab`

## 12. Layar

**Analis:** antrean order (per status) · pengambilan sampel · entri hasil dengan
rujukan tertampil di sebelah kolom isian · validasi.

**Dokter:** pemesanan lab dari layar SOAP · pembacaan hasil, dengan nilai abnormal
ditandai.

**Admin:** master pemeriksaan, parameter, dan nilai rujukan.

Layar entri hasil menampilkan rentang rujukan tepat di sebelah kolom nilai, sehingga
analis melihat kewajarannya saat mengetik — bukan setelah tersimpan.

## 13. Seeder

Sepuluh pemeriksaan yang paling sering dipesan di rawat jalan (darah rutin, gula darah
sewaktu, gula darah puasa, kolesterol total, asam urat, fungsi ginjal, fungsi hati,
urinalisis, widal, tes kehamilan) beserta parameter dan rujukannya, dengan rujukan
berbeda laki-laki dan perempuan pada parameter yang memang berbeda. Tarif untuk kedua
penjamin. Beberapa order dummy tersebar pada keempat status supaya tiap layar analis
punya isi saat didemokan.

## 14. Kriteria selesai

1. Seluruh test lulus dan setiap aturan di bagian 9 punya test yang membuktikannya.
2. Seluruh test Fase 1 dan Fase 2 tetap hijau setelah penyatuan tarif.
3. Satu pasien dapat ditelusuri dari dokter memesan lab, sampel diambil, hasil dientri dan divalidasi, dokter membaca, sampai kunjungan ditutup dan tagihannya memuat biaya lab.
4. Nilai abnormal tertandai benar untuk pasien laki-laki maupun perempuan pada parameter yang rujukannya berbeda.
5. Analis tidak dapat mengakses layar klinis, apotek, maupun kasir, dibuktikan oleh test.
6. `php artisan migrate:fresh --seed` menghasilkan laboratorium yang langsung bisa didemokan.
