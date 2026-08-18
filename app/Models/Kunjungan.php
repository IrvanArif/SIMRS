<?php

namespace App\Models;

use App\Enums\StatusKunjungan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kunjungan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'kunjungan';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'status' => StatusKunjungan::class,
            'waktu_daftar' => 'datetime',
            'waktu_selesai' => 'datetime',
        ];
    }

    public function pasien(): BelongsTo
    {
        return $this->belongsTo(Pasien::class);
    }

    public function poli(): BelongsTo
    {
        return $this->belongsTo(Poli::class);
    }

    public function dokter(): BelongsTo
    {
        return $this->belongsTo(Dokter::class);
    }

    public function penjamin(): BelongsTo
    {
        return $this->belongsTo(Penjamin::class);
    }

    public function antrian(): HasOne
    {
        return $this->hasOne(Antrian::class);
    }

    public function pemeriksaan(): HasOne
    {
        return $this->hasOne(Pemeriksaan::class);
    }

    public function diagnosa(): HasMany
    {
        return $this->hasMany(Diagnosa::class);
    }

    public function tindakan(): HasMany
    {
        return $this->hasMany(TindakanKunjungan::class);
    }

    public function resep(): HasOne
    {
        return $this->hasOne(Resep::class);
    }

    public function tagihan(): HasOne
    {
        return $this->hasOne(Tagihan::class);
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            StatusKunjungan::Selesai->value,
            StatusKunjungan::Batal->value,
        ]);
    }
}
