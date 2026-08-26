<?php

namespace App\Models;

use App\Enums\CaraPulang;
use App\Enums\StatusRawatInap;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RawatInap extends Model
{
    use HasFactory;

    protected $table = 'rawat_inap';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => StatusRawatInap::class,
            'cara_pulang' => CaraPulang::class,
            'waktu_masuk' => 'datetime',
            'waktu_pulang' => 'datetime',
        ];
    }

    public function kunjungan(): BelongsTo
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function dokter(): BelongsTo
    {
        return $this->belongsTo(Dokter::class);
    }

    public function kelasDiminta(): BelongsTo
    {
        return $this->belongsTo(KelasKamar::class, 'kelas_diminta_id');
    }

    public function diagnosaAkhir(): BelongsTo
    {
        return $this->belongsTo(Icd10::class, 'diagnosa_akhir_id');
    }

    public function bed(): HasOne
    {
        return $this->hasOne(Bed::class);
    }

    public function okupansi(): HasMany
    {
        return $this->hasMany(OkupansiBed::class)->orderBy('id');
    }

    public function bedSekarang(): ?Bed
    {
        return $this->okupansi()->berjalan()->first()?->bed;
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('status', StatusRawatInap::Dirawat->value);
    }

    public function pasien(): ?Pasien
    {
        return $this->kunjungan?->pasien;
    }
}
