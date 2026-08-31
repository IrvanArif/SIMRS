<?php

namespace App\Models;

use App\Enums\JenisPelayanan;
use App\Enums\StatusBerkasKlaim;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BerkasKlaim extends Model
{
    use HasFactory;

    protected $table = 'berkas_klaim';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => StatusBerkasKlaim::class,
            'jenis_pelayanan' => JenisPelayanan::class,
            'tanggal_masuk' => 'date',
            'tanggal_pulang' => 'date',
            'diajukan_pada' => 'datetime',
            'diverifikasi_pada' => 'datetime',
        ];
    }

    public function kunjungan(): BelongsTo
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function sep(): BelongsTo
    {
        return $this->belongsTo(Sep::class);
    }

    public function diagnosa(): HasMany
    {
        return $this->hasMany(BerkasKlaimDiagnosa::class)->orderByRaw("FIELD(jenis,'primer','sekunder')");
    }

    public function prosedur(): HasMany
    {
        return $this->hasMany(BerkasKlaimProsedur::class)->orderBy('kode');
    }

    public function scopeBerlaku(Builder $query): Builder
    {
        return $query->where('status', '!=', StatusBerkasKlaim::Batal->value);
    }

    public function scopeTerkirim(Builder $query): Builder
    {
        return $query->whereIn('status', [
            StatusBerkasKlaim::Diajukan->value,
            StatusBerkasKlaim::Disetujui->value,
            StatusBerkasKlaim::Ditolak->value,
        ]);
    }
}
