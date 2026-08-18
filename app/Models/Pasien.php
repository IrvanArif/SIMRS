<?php

namespace App\Models;

use App\Enums\JenisKelamin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pasien extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pasien';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
            'jenis_kelamin' => JenisKelamin::class,
        ];
    }

    public function kunjungan(): HasMany
    {
        return $this->hasMany(Kunjungan::class);
    }

    public function scopeCari(Builder $query, string $kata): Builder
    {
        return $query->where(function (Builder $q) use ($kata) {
            $q->where('nama', 'like', "%{$kata}%")
                ->orWhere('nik', $kata)
                ->orWhere('no_rm', $kata);
        });
    }

    public function umur(): int
    {
        return $this->tanggal_lahir->age;
    }
}
