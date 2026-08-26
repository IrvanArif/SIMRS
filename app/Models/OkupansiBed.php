<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Satu penggal masa tempat. Riwayatnya disimpan berpenggal, bukan sebagai satu
 * penunjuk, karena pasien yang pindah dari VIP ke Kelas 2 di hari ketiga harus
 * ditagih dua tarif berbeda.
 */
class OkupansiBed extends Model
{
    use HasFactory;

    protected $table = 'okupansi_bed';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'mulai' => 'date',
            'selesai' => 'date',
        ];
    }

    public function rawatInap(): BelongsTo
    {
        return $this->belongsTo(RawatInap::class);
    }

    public function bed(): BelongsTo
    {
        return $this->belongsTo(Bed::class);
    }

    public function scopeBerjalan(Builder $query): Builder
    {
        return $query->whereNull('selesai');
    }

    /**
     * Lama penggal dalam hari kalender, minimal satu (aturan 71): kamar yang
     * dipakai setengah hari tetap tidak bisa dijual ke orang lain hari itu.
     *
     * Penggal yang belum ditutup dihitung sampai hari ini, supaya rincian
     * sementara bisa dibaca kapan saja.
     */
    public function hari(?Carbon $sampai = null): int
    {
        $akhir = $this->selesai ?? $sampai ?? Carbon::today();

        return max(1, $this->mulai->diffInDays($akhir));
    }

    public function subtotal(?Carbon $sampai = null): int
    {
        return $this->hari($sampai) * (int) $this->tarif_harian;
    }
}
