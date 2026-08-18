# SIMRS — Desain Fase 2: Farmasi

Tanggal: 2026-08-18
Status: disetujui, siap direncanakan
Penyusun: Irvan bersama Claude

## 1. Tujuan

Melengkapi alur rawat jalan Fase 1 dengan apotek: stok obat berbasis batch,
penyiapan resep, penyerahan obat ke pasien, dan pembebanan biaya obat ke tagihan.

Fase 1 meninggalkan tiga lubang yang ditutup fase ini:

1. Tabel `obat` hanya katalog — tidak punya stok, harga, batch, maupun kedaluwarsa.
2. `PenyusunTagihan` hanya membaca tindakan, sehingga **obat tidak pernah tertagih**.
3. `resep.status` selalu `dibuat`; nilai `diserahkan` tidak pernah dipakai karena
   belum ada yang menyerahkan obat.

Laboratorium dan radiologi — yang sempat digabung ke "Fase 2" pada spec Fase 1 —
dipisah menjadi fase tersendiri. Fase ini hanya farmasi.

## 2. Ruang lingkup

**Termasuk:** peran apoteker; harga obat per penjamin; penerimaan obat per batch
dengan nomor batch dan tanggal kedaluwarsa; penyiapan resep dengan alokasi FEFO;
penyerahan obat; pembebanan biaya obat ke tagihan kunjungan; kartu stok; penyesuaian
stok beralasan; peringatan stok menipis dan obat mendekati kedaluwarsa; seeder stok dummy.

**Tidak termasuk:** laboratorium, radiologi, rawat inap, pengadaan ke supplier
(surat pesanan, retur), multi-gudang beserta mutasi antar unit, obat racikan,
pemusnahan obat kedaluwarsa, dan integrasi harga Fornas/e-Katalog.

## 3. Keputusan yang mengikat

**Alur berbeda menurut penjamin.** Pasien berpenjamin (BPJS) tidak melewati kasir:
poli → apotek → pulang, dengan nilai obat tetap tercatat penuh sebagai bahan klaim.
Pasien umum melewati kasir dengan **satu tagihan** untuk tindakan dan obat sekaligus.

**Tagihan diperluas apotek, bukan disusun ulang.** Tagihan tetap terbentuk saat dokter
menyelesaikan kunjungan (aturan 12 Fase 1). Apotek menambahkan baris obat ke tagihan
itu selama belum lunas. Pengamannya dua aturan baru yang bekerja berpasangan:
aturan 29 mencegah uang lolos, aturan 30 mencegah obat lolos.

Alternatif yang ditolak: menggeser pemicu penyusunan tagihan sampai resep siap.
Sifat sekali-tulisnya lebih murni, tetapi menciptakan jeda ketika kunjungan sudah
selesai dan pasiennya belum muncul di layar kasir sama sekali. Kegagalan yang
kelihatan lebih baik daripada kegagalan yang senyap.

**Stok berbasis batch dengan FEFO.** Tiap penerimaan tercatat sebagai batch bernomor
dengan tanggal kedaluwarsa. Penyiapan mengambil batch berkedaluwarsa terdekat lebih
dulu dan boleh memecah satu baris resep ke beberapa batch.

**Harga jual per obat per penjamin, harga beli per batch.** Harga jual mengikuti pola
`tarif_tindakan` yang sudah teruji; harga beli disimpan di batch untuk menilai persediaan.

## 4. Peran

Peran ketujuh: **`apoteker`**. Berwenang menerima obat, menyiapkan dan menyerahkan
resep, menyesuaikan stok, serta melihat kartu stok. Tidak berwenang mengisi atau
mengubah rekam medis, dan tidak berwenang menerima pembayaran.

Master harga obat dikelola `admin`, sejalan dengan master tarif tindakan.

## 5. Model data

### Perluasan tabel yang sudah ada

| Tabel | Kolom baru |
|---|---|
| `obat` | `stok_minimum` (unsigned integer, default 10) |
| `resep` | `disiapkan_pada`, `disiapkan_oleh`, `diserahkan_pada`, `diserahkan_oleh` |
| `resep_detail` | `jumlah_diserahkan`, `harga_satuan` |

`resep.status` diperluas menjadi enum `StatusResep`: `dibuat` → `disiapkan` →
`diserahkan`, atau `batal`.

### Tabel baru

| Tabel | Kolom kunci |
|---|---|
| `harga_obat` | `obat_id`, `penjamin_id`, `harga`, `berlaku_mulai` — unik `(obat_id, penjamin_id, berlaku_mulai)` |
| `batch_obat` | `obat_id`, `no_batch`, `tanggal_kedaluwarsa`, `jumlah_awal`, `jumlah_tersisa`, `harga_beli`, `diterima_pada`, `diterima_oleh` — unik `(obat_id, no_batch)` |
| `pengambilan_batch` | `resep_detail_id`, `batch_obat_id`, `jumlah`, `harga_beli` |
| `mutasi_stok` | `batch_obat_id`, `obat_id`, `jenis`, `jumlah`, `stok_sesudah`, `resep_id` (nullable), `catatan`, `dilakukan_oleh`, `created_at` |

`jenis` pada `mutasi_stok` bertipe enum `JenisMutasiStok`: `masuk`, `keluar`,
`pengembalian`, `penyesuaian`.

`pengambilan_batch` ada karena satu baris resep bisa ditarik dari lebih dari satu
batch. Tanpa tabel itu, pertanyaan "batch mana yang diterima pasien X" tidak akan
pernah bisa dijawab — padahal itu justru pertanyaan yang muncul saat sebuah batch
ditarik dari peredaran.

## 6. Layanan

| Kelas | Tanggung jawab |
|---|---|
| `PenerimaanObat` | Membuat batch baru dan mencatat mutasi masuk |
| `PencariHargaObat` | Harga menurut penjamin, jatuh tempo ke UMUM (pola aturan 13) |
| `PenyiapanResep` | Alokasi FEFO, pengurangan stok, penyalinan harga, pembebanan ke tagihan |
| `PenyerahanObat` | Penyerahan ke pasien beserta penjaga pelunasan |
| `PenyesuaianStok` | Koreksi hasil opname, wajib beralasan |

Perubahan pada kode Fase 1:

- `PenyusunTagihan` mendapat method `tambahObat(Resep $resep): void`.
- `ProsesPembayaran` mendapat penjaga: menolak melunasi tagihan yang kunjungannya
  masih punya resep berstatus `dibuat`.

## 7. Alur

```
Dokter menulis resep               resep berstatus "dibuat"
Dokter menyelesaikan kunjungan     tagihan terbentuk berisi tindakan saja;
                                   kasir melihat pasien, tombol bayar terkunci

Apoteker menyiapkan resep          alokasi FEFO dari batch
                                   stok berkurang, mutasi tercatat
                                   harga disalin ke resep_detail
                                   baris obat ditambahkan ke tagihan
                                   resep berstatus "disiapkan"; kunci kasir terbuka

Pasien umum    kasir menerima pembayaran tindakan + obat
               apoteker menyerahkan obat, resep berstatus "diserahkan"

Pasien BPJS    apoteker langsung menyerahkan obat tanpa melewati kasir
               tagihan tetap tercatat penuh sebagai bahan klaim
```

## 8. Aturan bisnis

Melanjutkan penomoran spec Fase 1. Setiap aturan wajib punya test yang membuktikannya.

21. Batch wajib punya nomor batch dan tanggal kedaluwarsa; nomor batch unik per obat.
22. Batch yang tanggal kedaluwarsanya sudah lewat tidak boleh dialokasikan ke pasien.
23. Penyiapan resep mengambil batch dengan tanggal kedaluwarsa terdekat lebih dahulu, dan boleh memecah satu baris resep ke beberapa batch.
24. Stok tidak boleh menjadi negatif; penyiapan ditolak seluruhnya bila stok yang layak pakai kurang dari jumlah yang diresepkan — tidak ada penyiapan sebagian.
25. Setiap perubahan stok tercatat di `mutasi_stok` beserta pelakunya dan sisa stok sesudahnya.
26. Harga obat disalin ke `resep_detail.harga_satuan` saat penyiapan; perubahan master harga tidak mengubah resep yang sudah disiapkan.
27. Harga dipilih menurut penjamin kunjungan. Bila penjamin tersebut belum punya harga, dipakai harga penjamin `UMUM` dan kejadian itu dicatat ke log.
28. Biaya obat ditambahkan ke tagihan kunjungan saat resep disiapkan. Tagihan yang sudah lunas tidak dapat ditambahi baris.
29. Tagihan tidak dapat dilunasi selama kunjungannya masih punya resep berstatus `dibuat`.
30. Obat pasien berpenjamin jenis `tunai` hanya boleh diserahkan setelah tagihannya lunas. Obat pasien berpenjamin jenis `penjamin` boleh diserahkan tanpa menunggu kasir.
31. Resep yang sudah diserahkan tidak dapat disiapkan ulang maupun diubah.
32. Pembatalan penyiapan mengembalikan seluruh jumlah ke batch asalnya dan mencatat mutasi `pengembalian`.
33. Penyesuaian stok wajib menyertakan alasan dan tercatat di audit log.
34. Obat yang jumlah tersisanya di bawah `stok_minimum` muncul di daftar peringatan apoteker.

## 9. Penanganan kesalahan

- **Stok kurang** ditolak dengan pesan yang menyebut nama obat, jumlah diminta, dan jumlah tersedia — apoteker perlu tahu selisihnya, bukan sekadar "gagal".
- **Seluruh batch kedaluwarsa** ditolak dengan pesan berbeda dari "stok kurang", karena tindak lanjutnya berbeda: yang satu perlu pemesanan, yang satu perlu pemusnahan.
- **Dua apoteker menyiapkan resep yang sama** dicegah dengan penguncian baris resep di dalam transaksi; yang kalah menerima pesan bahwa resep sudah disiapkan petugas lain.
- **Kasir melunasi saat obat belum siap** ditolak dengan pesan yang menyebut nomor resep yang ditunggu.
- **Harga obat belum diisi** memakai harga UMUM dan mencatat peringatan, sama seperti tarif tindakan.
- **Penyiapan gagal di tengah jalan** membatalkan seluruh transaksi; stok tidak boleh berkurang sebagian.

## 10. Pengujian

TDD dengan gaya yang sama: test ditulis lebih dulu dan harus gagal sebelum
implementasinya ada, nama test berbahasa Indonesia.

Test yang wajib ada, minimal:

- `test_penerimaan_obat_menambah_stok_dan_mencatat_mutasi_masuk`
- `test_nomor_batch_ganda_untuk_obat_yang_sama_ditolak`
- `test_penyiapan_mengambil_batch_yang_paling_dekat_kedaluwarsa`
- `test_satu_baris_resep_bisa_ditarik_dari_dua_batch`
- `test_batch_kedaluwarsa_tidak_ikut_dialokasikan`
- `test_stok_kurang_menolak_penyiapan_dan_tidak_mengubah_stok`
- `test_penyiapan_menyalin_harga_sesuai_penjamin_kunjungan`
- `test_perubahan_master_harga_tidak_mengubah_resep_yang_sudah_disiapkan`
- `test_biaya_obat_masuk_ke_tagihan_kunjungan_yang_sama`
- `test_tagihan_tidak_bisa_dilunasi_saat_resep_belum_disiapkan`
- `test_obat_pasien_umum_tidak_bisa_diserahkan_sebelum_lunas`
- `test_obat_pasien_bpjs_bisa_diserahkan_tanpa_ke_kasir`
- `test_resep_yang_sudah_diserahkan_tidak_bisa_disiapkan_ulang`
- `test_pembatalan_penyiapan_mengembalikan_stok_ke_batch_asal`
- `test_penyesuaian_stok_tanpa_alasan_ditolak`
- `test_apoteker_tidak_bisa_membuka_form_soap`
- `test_dokter_tidak_bisa_menyiapkan_resep`

## 11. Layar apoteker

Antrean resep (berstatus `dibuat`) · Penyiapan resep, menampilkan alokasi FEFO
sebelum dikonfirmasi · Penyerahan obat · Penerimaan batch · Kartu stok per obat ·
Peringatan stok menipis dan obat mendekati kedaluwarsa.

Master harga obat menjadi layar `admin`, sejalan dengan master tarif.

## 12. Seeder tambahan

Harga jual untuk 50 obat pada kedua penjamin (harga BPJS sekitar 70% harga umum),
dua batch per obat dengan tanggal kedaluwarsa berbeda supaya FEFO terlihat bekerja,
satu batch yang sengaja dibuat kedaluwarsa sebagai bahan uji, dan beberapa obat
bersaldo di bawah `stok_minimum` supaya layar peringatan tidak kosong saat didemokan.

## 13. Kriteria selesai

1. Seluruh test lulus dan setiap aturan di bagian 8 punya test yang membuktikannya.
2. Satu pasien umum dapat ditelusuri dari resep dokter, penyiapan apotek, pembayaran kasir, sampai obat diserahkan — dengan biaya obat muncul di kuitansi yang sama.
3. Satu pasien BPJS dapat menerima obat tanpa melewati kasir, dan nilai obatnya tetap tercatat penuh pada tagihan.
4. Kartu stok sebuah obat memperlihatkan seluruh mutasi masuk dan keluar beserta pelakunya.
5. Apoteker tidak dapat mengakses layar klinis maupun kasir, dibuktikan oleh test.
6. `php artisan migrate:fresh --seed` menghasilkan apotek yang langsung bisa didemokan.
