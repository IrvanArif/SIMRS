<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResepDetail extends Model
{
    use HasFactory;

    protected $table = 'resep_detail';

    protected $guarded = [];

    public function resep(): BelongsTo
    {
        return $this->belongsTo(Resep::class);
    }

    public function obat(): BelongsTo
    {
        return $this->belongsTo(Obat::class);
    }

    public function pengambilan(): HasMany
    {
        return $this->hasMany(PengambilanBatch::class, 'resep_detail_id');
    }

    public function subtotal(): int
    {
        return (int) $this->jumlah_diserahkan * (int) $this->harga_satuan;
    }
}
