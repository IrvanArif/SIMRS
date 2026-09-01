<?php

use App\Http\Controllers\AutentikasiController;
use App\Livewire\Admin\KelolaUser;
use App\Livewire\Apotek\AntreanResep;
use App\Livewire\Apotek\KartuStok;
use App\Livewire\Apotek\LayarPenyerahan;
use App\Livewire\Apotek\LayarPenyiapan;
use App\Livewire\Apotek\PenerimaanBatch;
use App\Livewire\Apotek\PeringatanStok;
use App\Livewire\Admin\PenampilAuditLog;
use App\Livewire\Kasir\DaftarTagihan;
use App\Livewire\Klaim\DaftarBerkas;
use App\Livewire\Klaim\DaftarSep;
use App\Livewire\Laporan\Indikator;
use App\Livewire\Laporan\Morbiditas;
use App\Livewire\Laporan\Pendapatan as LaporanPendapatan;
use App\Livewire\Laporan\RekapKunjungan;
use App\Livewire\Kasir\ProsesPembayaran;
use App\Livewire\Pendaftaran\CariPasien;
use App\Livewire\Pendaftaran\FormKunjungan;
use App\Livewire\Pendaftaran\FormPasien;
use App\Livewire\Pendaftaran\PapanAntrian;
use App\Livewire\Lab\AntreanOrder;
use App\Livewire\Lab\LayarEntriHasil;
use App\Livewire\Lab\LayarSampel;
use App\Livewire\Lab\LayarValidasi;
use App\Livewire\Master\DaftarDokter;
use App\Livewire\Master\DaftarPemeriksaanRadiologi;
use App\Livewire\Master\DaftarRuangBed;
use App\Livewire\Master\DaftarPoli;
use App\Livewire\Master\DaftarTarif;
use App\Livewire\Master\DaftarTindakan;
use App\Livewire\Poli\AntrianPoli;
use App\Livewire\Radiologi\AntreanOrder as AntreanOrderRadiologi;
use App\Livewire\Radiologi\LayarEkspertise;
use App\Livewire\Radiologi\LayarPelaksanaan;
use App\Livewire\RawatInap\LayarPemulangan;
use App\Livewire\RawatInap\LayarPenempatan;
use App\Livewire\RawatInap\LayarPerawatan;
use App\Livewire\RawatInap\PapanBed;
use App\Livewire\Poli\FormResep;
use App\Livewire\Poli\FormSoap;
use App\Livewire\Poli\FormVital;
use App\Livewire\RekamMedis\KoreksiPasien;
use App\Livewire\RekamMedis\PenelusuranRekamMedis;
use App\Livewire\RekamMedis\RekapKunjunganHarian;
use App\Models\Antrian;
use App\Models\BerkasKlaim;
use App\Models\Pembayaran;
use App\Services\EksporKlaim;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing', ['angka' => \App\Support\DenyutSistem::ambil()]);
})->name('landing');

Route::view('/display/antrian', 'display.antrian')->name('display.antrian');

Route::middleware('guest')->group(function () {
    Route::get('/masuk', [AutentikasiController::class, 'formMasuk'])->name('masuk');
    Route::post('/masuk', [AutentikasiController::class, 'masuk']);
});

Route::middleware('auth')->group(function () {
    Route::post('/keluar', [AutentikasiController::class, 'keluar'])->name('keluar');
    Route::view('/beranda', 'beranda')->name('beranda');

    Route::middleware('role:admisi')->group(function () {
        Route::get('/pendaftaran/pasien', CariPasien::class)->name('pendaftaran.pasien');
        Route::get('/pendaftaran/pasien/baru', FormPasien::class)->name('pendaftaran.pasien.baru');
        Route::get('/pendaftaran/pasien/{pasien}/ubah', FormPasien::class)->name('pendaftaran.pasien.ubah');
        Route::get('/pendaftaran/kunjungan/{pasien}', FormKunjungan::class)->name('pendaftaran.kunjungan');
        Route::get('/pendaftaran/antrian', PapanAntrian::class)->name('pendaftaran.antrian');

        Route::get('/cetak/karcis/{antrian}', fn (Antrian $antrian) => view('cetak.karcis', compact('antrian')))
            ->name('cetak.karcis');
    });

    Route::middleware('role:perawat|dokter')->group(function () {
        Route::get('/poli/antrian', AntrianPoli::class)->name('poli.antrian');
    });

    Route::middleware('role:perawat')->group(function () {
        Route::get('/poli/vital/{kunjungan}', FormVital::class)->name('poli.vital');
    });

    Route::middleware('role:dokter')->group(function () {
        Route::get('/poli/soap/{kunjungan}', FormSoap::class)->name('poli.soap');
        Route::get('/poli/resep/{kunjungan}', FormResep::class)->name('poli.resep');
    });

    Route::middleware('role:kasir')->group(function () {
        Route::get('/kasir/tagihan', DaftarTagihan::class)->name('kasir.tagihan');
        Route::get('/kasir/bayar/{tagihan}', ProsesPembayaran::class)->name('kasir.bayar');

        Route::get('/cetak/kuitansi/{pembayaran}', fn (Pembayaran $pembayaran) => view('cetak.kuitansi', compact('pembayaran')))
            ->name('cetak.kuitansi');
    });

    Route::middleware('role:rekam_medis')->group(function () {
        Route::get('/rekam-medis/telusur', PenelusuranRekamMedis::class)->name('rekam-medis.telusur');
        Route::get('/rekam-medis/koreksi/{pasien}', KoreksiPasien::class)->name('rekam-medis.koreksi');
        Route::get('/rekam-medis/rekap', RekapKunjunganHarian::class)->name('rekam-medis.rekap');
    });

    Route::middleware('role:apoteker')->group(function () {
        Route::get('/apotek/antrean', AntreanResep::class)->name('apotek.antrean');
        Route::get('/apotek/siapkan/{resep}', LayarPenyiapan::class)->name('apotek.siapkan');
        Route::get('/apotek/serahkan/{resep}', LayarPenyerahan::class)->name('apotek.serahkan');
        Route::get('/apotek/penerimaan', PenerimaanBatch::class)->name('apotek.penerimaan');
        Route::get('/apotek/kartu-stok/{obat}', KartuStok::class)->name('apotek.kartu-stok');
        Route::get('/apotek/peringatan', PeringatanStok::class)->name('apotek.peringatan');
    });

    Route::middleware('role:analis')->group(function () {
        Route::get('/lab/antrean', AntreanOrder::class)->name('lab.antrean');
        Route::get('/lab/sampel/{order}', LayarSampel::class)->name('lab.sampel');
        Route::get('/lab/hasil/{order}', LayarEntriHasil::class)->name('lab.hasil');
        Route::get('/lab/validasi/{order}', LayarValidasi::class)->name('lab.validasi');
    });

    // Antrean dibuka juga untuk dokter: itulah satu-satunya daftar order, jadi
    // tanpa akses ini layar ekspertise tidak bisa dijangkau dari mana pun.
    Route::middleware('role:radiografer|dokter')->group(function () {
        Route::get('/radiologi/antrean', AntreanOrderRadiologi::class)->name('radiologi.antrean');
    });

    Route::middleware('role:radiografer')->group(function () {
        Route::get('/radiologi/kerjakan/{order}', LayarPelaksanaan::class)->name('radiologi.kerjakan');
    });

    // Ekspertise ditulis dokter, bukan radiografer (aturan 54).
    Route::middleware('role:dokter')->group(function () {
        Route::get('/radiologi/ekspertise/{order}', LayarEkspertise::class)->name('radiologi.ekspertise');
    });

    // Papan bed adalah satu-satunya daftar pasien rawat inap, jadi setiap peran
    // yang punya urusan dengannya harus bisa membukanya — termasuk kasir, yang
    // perlu menjelaskan rincian kamar pada tagihan.
    Route::middleware('role:admisi|perawat|dokter|kasir|rekam_medis')->group(function () {
        Route::get('/rawat-inap/papan', PapanBed::class)->name('rawat-inap.papan');
    });

    Route::middleware('role:admisi')->group(function () {
        Route::get('/rawat-inap/tempatkan/{rawatInap}', LayarPenempatan::class)->name('rawat-inap.tempatkan');
    });

    Route::middleware('role:perawat|dokter')->group(function () {
        Route::get('/rawat-inap/rawat/{rawatInap}', LayarPerawatan::class)->name('rawat-inap.rawat');
    });

    // Memulangkan berarti menetapkan diagnosa akhir dan cara pulang (aturan 68).
    Route::middleware('role:dokter')->group(function () {
        Route::get('/rawat-inap/pulangkan/{rawatInap}', LayarPemulangan::class)->name('rawat-inap.pulangkan');
    });

    Route::middleware('role:admisi|rekam_medis|kasir|admin')->group(function () {
        Route::get('/klaim/sep', DaftarSep::class)->name('klaim.sep');
    });

    Route::middleware('role:rekam_medis|kasir|admin')->group(function () {
        Route::get('/klaim/berkas', DaftarBerkas::class)->name('klaim.berkas');
    });

    // Ekspor menghasilkan berkas; siapa yang mengunggahnya ke aplikasi BPJS
    // adalah urusan proses kerja, bukan sistem ini.
    Route::middleware('role:rekam_medis|admin')->group(function () {
        Route::get('/klaim/ekspor', function (EksporKlaim $ekspor) {
            $berkas = BerkasKlaim::terkirim()->with('sep', 'diagnosa', 'prosedur')->get();
            $isi = $ekspor->csv($berkas);

            return response()->streamDownload(
                fn () => print($isi),
                'klaim-'.now()->format('Ymd-His').'.csv',
                ['Content-Type' => 'text/csv']
            );
        })->name('klaim.ekspor');
    });

    Route::middleware('role:rekam_medis|admin')->group(function () {
        Route::get('/laporan/indikator', Indikator::class)->name('laporan.indikator');
        Route::get('/laporan/morbiditas', Morbiditas::class)->name('laporan.morbiditas');
        Route::get('/laporan/kunjungan', RekapKunjungan::class)->name('laporan.kunjungan');
    });

    // Kasir ikut boleh melihat pendapatan: ia yang menerima uangnya.
    Route::middleware('role:kasir|rekam_medis|admin')->group(function () {
        Route::get('/laporan/pendapatan', LaporanPendapatan::class)->name('laporan.pendapatan');
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/master/poli', DaftarPoli::class)->name('master.poli');
        Route::get('/master/dokter', DaftarDokter::class)->name('master.dokter');
        Route::get('/master/tindakan', DaftarTindakan::class)->name('master.tindakan');
        Route::get('/master/tarif', DaftarTarif::class)->name('master.tarif');
        Route::get('/master/pemeriksaan-radiologi', DaftarPemeriksaanRadiologi::class)
            ->name('master.pemeriksaan-radiologi');
        Route::get('/master/ruang-bed', DaftarRuangBed::class)->name('master.ruang-bed');
        Route::get('/admin/user', KelolaUser::class)->name('admin.user');
        Route::get('/admin/audit', PenampilAuditLog::class)->name('admin.audit');
    });
});
