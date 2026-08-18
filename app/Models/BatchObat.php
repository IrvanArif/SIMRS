<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class BatchObat extends Model
{
    use HasFactory;

    protected $table = 'batch_obat';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal_kedaluwarsa' => 'date',
            'diterima_pada' => 'datetime',
        ];
    }

    public function obat(): BelongsTo
    {
        return $this->belongsTo(Obat::class);
    }

    public function mutasi(): HasMany
    {
        return $this->hasMany(MutasiStok::class, 'batch_obat_id');
    }

    /**
     * Batch yang masih bersisa dan belum kedaluwarsa (aturan 22).
     */
    public function scopeLayakPakai(Builder $query, ?CarbonInterface $tanggal = null): Builder
    {
        $tanggal ??= Carbon::today();

        return $query->where('jumlah_tersisa', '>', 0)
            ->whereDate('tanggal_kedaluwarsa', '>=', $tanggal->toDateString());
    }

    public function kedaluwarsa(?CarbonInterface $tanggal = null): bool
    {
        return $this->tanggal_kedaluwarsa->lt($tanggal ?? Carbon::today());
    }
}
