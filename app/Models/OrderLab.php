<?php

namespace App\Models;

use App\Enums\StatusOrderLab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderLab extends Model
{
    use HasFactory;

    protected $table = 'order_lab';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => StatusOrderLab::class,
            'waktu_sampel' => 'datetime',
            'waktu_hasil' => 'datetime',
            'waktu_validasi' => 'datetime',
        ];
    }

    public function kunjungan(): BelongsTo
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function detail(): HasMany
    {
        return $this->hasMany(OrderLabDetail::class, 'order_lab_id');
    }

    /**
     * Order yang masih menahan penyelesaian kunjungan (aturan 37).
     */
    public function scopeBelumSelesai(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            StatusOrderLab::Divalidasi->value,
            StatusOrderLab::Batal->value,
        ]);
    }

    /**
     * Aturan 42: hasil baru boleh dibaca dokter setelah divalidasi.
     */
    public function terbacaDokter(): bool
    {
        return $this->status === StatusOrderLab::Divalidasi;
    }

    public function totalTarif(): int
    {
        return (int) $this->detail()->sum('tarif_satuan');
    }
}
