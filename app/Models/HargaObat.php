<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HargaObat extends Model
{
    use HasFactory;

    protected $table = 'harga_obat';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['berlaku_mulai' => 'date'];
    }

    public function obat(): BelongsTo
    {
        return $this->belongsTo(Obat::class);
    }

    public function penjamin(): BelongsTo
    {
        return $this->belongsTo(Penjamin::class);
    }
}
