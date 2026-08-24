<?php

namespace App\Providers;

use App\Models\BatchObat;
use App\Models\Diagnosa;
use App\Models\HasilLab;
use App\Models\Kunjungan;
use App\Models\OrderLab;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Tagihan;
use App\Observers\PencatatAudit;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ($this->modelTerauditkan() as $model) {
            $model::observe(PencatatAudit::class);
        }
    }

    /**
     * Model yang setiap perubahannya wajib berjejak (aturan 19).
     * Tambahkan model klinis baru ke daftar ini saat dibuat.
     */
    private function modelTerauditkan(): array
    {
        return [
            Pasien::class, Kunjungan::class, Pemeriksaan::class,
            Diagnosa::class, Tagihan::class, BatchObat::class, OrderLab::class,
            HasilLab::class,
        ];
    }
}
