<?php

namespace App\Models;

use App\Enums\StatusAntrian;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Antrian extends Model
{
    use HasFactory;

    protected $table = 'antrian';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'status' => StatusAntrian::class,
            'waktu_panggil' => 'datetime',
        ];
    }

    public function kunjungan(): BelongsTo
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function poli(): BelongsTo
    {
        return $this->belongsTo(Poli::class);
    }

    public function kode(): string
    {
        return $this->poli->kode.'-'.str_pad((string) $this->nomor, 3, '0', STR_PAD_LEFT);
    }
}
