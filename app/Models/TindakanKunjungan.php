<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TindakanKunjungan extends Model
{
    use HasFactory;

    protected $table = 'tindakan_kunjungan';

    protected $guarded = [];

    public function kunjungan(): BelongsTo
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function tindakan(): BelongsTo
    {
        return $this->belongsTo(Tindakan::class);
    }

    public function subtotal(): int
    {
        return (int) $this->jumlah * (int) $this->tarif_satuan;
    }
}
