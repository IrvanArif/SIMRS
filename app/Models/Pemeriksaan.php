<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pemeriksaan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pemeriksaan';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'suhu' => 'float',
            'berat_badan' => 'float',
            'waktu_perawat' => 'datetime',
            'waktu_dokter' => 'datetime',
        ];
    }

    public function kunjungan(): BelongsTo
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function soapLengkap(): bool
    {
        return filled($this->subjective)
            && filled($this->objective)
            && filled($this->assessment)
            && filled($this->plan);
    }
}
