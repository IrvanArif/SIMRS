<?php

use App\Http\Controllers\AutentikasiController;
use App\Livewire\Admin\KelolaUser;
use App\Livewire\Admin\PenampilAuditLog;
use App\Livewire\Kasir\DaftarTagihan;
use App\Livewire\Kasir\ProsesPembayaran;
use App\Livewire\Pendaftaran\CariPasien;
use App\Livewire\Pendaftaran\FormKunjungan;
use App\Livewire\Pendaftaran\FormPasien;
use App\Livewire\Pendaftaran\PapanAntrian;
use App\Livewire\Master\DaftarDokter;
use App\Livewire\Master\DaftarPoli;
use App\Livewire\Master\DaftarTarif;
use App\Livewire\Master\DaftarTindakan;
use App\Livewire\Poli\AntrianPoli;
use App\Livewire\Poli\FormResep;
use App\Livewire\Poli\FormSoap;
use App\Livewire\Poli\FormVital;
use App\Livewire\RekamMedis\KoreksiPasien;
use App\Livewire\RekamMedis\PenelusuranRekamMedis;
use App\Livewire\RekamMedis\RekapKunjunganHarian;
use App\Models\Antrian;
use App\Models\Pembayaran;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

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

    Route::middleware('role:admin')->group(function () {
        Route::get('/master/poli', DaftarPoli::class)->name('master.poli');
        Route::get('/master/dokter', DaftarDokter::class)->name('master.dokter');
        Route::get('/master/tindakan', DaftarTindakan::class)->name('master.tindakan');
        Route::get('/master/tarif', DaftarTarif::class)->name('master.tarif');
        Route::get('/admin/user', KelolaUser::class)->name('admin.user');
        Route::get('/admin/audit', PenampilAuditLog::class)->name('admin.audit');
    });
});
