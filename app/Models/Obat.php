<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Obat extends Model
{
    use HasFactory;

    protected $table = 'obat';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function harga(): HasMany
    {
        return $this->hasMany(HargaObat::class);
    }

    public function batch(): HasMany
    {
        return $this->hasMany(BatchObat::class);
    }

    public function stokTersedia(): int
    {
        return (int) $this->batch()->layakPakai()->sum('jumlah_tersisa');
    }

    /**
     * Obat yang stok layak pakainya di bawah stok_minimum (aturan 34).
     * Obat tanpa batch sama sekali ikut terjaring karena stoknya nol.
     */
    public function scopeMenipis(Builder $query): Builder
    {
        return $query->whereRaw(
            '(SELECT COALESCE(SUM(jumlah_tersisa), 0) FROM batch_obat
              WHERE batch_obat.obat_id = obat.id
                AND batch_obat.jumlah_tersisa > 0
                AND batch_obat.tanggal_kedaluwarsa >= CURDATE()) < obat.stok_minimum'
        );
    }
}
