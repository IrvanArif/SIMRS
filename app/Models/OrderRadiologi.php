<?php

namespace App\Models;

use App\Enums\StatusOrderRadiologi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderRadiologi extends Model
{
    use HasFactory;

    protected $table = 'order_radiologi';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => StatusOrderRadiologi::class,
            'waktu_dikerjakan' => 'datetime',
            'waktu_ekspertise' => 'datetime',
        ];
    }

    public function kunjungan(): BelongsTo
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function dokter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dokter_id');
    }

    public function radiografer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dikerjakan_oleh');
    }

    public function penulisEkspertise(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditulis_oleh');
    }

    public function detail(): HasMany
    {
        return $this->hasMany(OrderRadiologiDetail::class, 'order_radiologi_id');
    }

    /**
     * Order yang masih menahan penyelesaian kunjungan (aturan 50).
     */
    public function scopeBelumSelesai(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            StatusOrderRadiologi::Selesai->value,
            StatusOrderRadiologi::Batal->value,
        ]);
    }

    /**
     * Aturan 55: hasil terbaca dokter pengirim hanya setelah ekspertise ditulis.
     * Citra yang sudah diambil tapi belum dibaca bukan hasil — menyimpulkan sendiri
     * dari gambar tanpa ekspertise justru yang ingin dicegah.
     */
    public function terbacaDokter(): bool
    {
        return $this->status === StatusOrderRadiologi::Selesai;
    }

    public function totalTarif(): int
    {
        return (int) $this->detail()->sum('tarif_satuan');
    }
}
