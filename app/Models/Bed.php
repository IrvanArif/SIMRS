<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bed extends Model
{
    use HasFactory;

    protected $table = 'bed';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function ruang(): BelongsTo
    {
        return $this->belongsTo(Ruang::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(KelasKamar::class, 'kelas_kamar_id');
    }

    public function penghuni(): BelongsTo
    {
        return $this->belongsTo(RawatInap::class, 'rawat_inap_id');
    }

    public function terisi(): bool
    {
        return $this->rawat_inap_id !== null;
    }

    /**
     * Bed nonaktif bukan bed kosong: ia tidak tersedia, hanya kebetulan tidak
     * ada pasiennya.
     */
    public function scopeKosong(Builder $query): Builder
    {
        return $query->whereNull('rawat_inap_id')->where('aktif', true);
    }

    public function label(): string
    {
        return "{$this->ruang->nama} {$this->nomor}";
    }
}
