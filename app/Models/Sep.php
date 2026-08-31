<?php

namespace App\Models;

use App\Enums\JenisPelayanan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sep extends Model
{
    use HasFactory;

    public const BERLAKU = 'berlaku';

    public const BATAL = 'batal';

    protected $table = 'sep';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'jenis_pelayanan' => JenisPelayanan::class,
            'tanggal' => 'date',
        ];
    }

    public function kunjungan(): BelongsTo
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function scopeBerlaku(Builder $query): Builder
    {
        return $query->where('status', self::BERLAKU);
    }

    public function berlaku(): bool
    {
        return $this->status === self::BERLAKU;
    }
}
