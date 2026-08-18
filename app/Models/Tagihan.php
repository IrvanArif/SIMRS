<?php

namespace App\Models;

use App\Enums\StatusTagihan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tagihan extends Model
{
    use HasFactory;

    protected $table = 'tagihan';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => StatusTagihan::class, 'disusun_pada' => 'datetime'];
    }

    public function kunjungan(): BelongsTo
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function penjamin(): BelongsTo
    {
        return $this->belongsTo(Penjamin::class);
    }

    public function detail(): HasMany
    {
        return $this->hasMany(TagihanDetail::class);
    }

    public function pembayaran(): HasMany
    {
        return $this->hasMany(Pembayaran::class);
    }
}
