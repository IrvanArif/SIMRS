<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PemeriksaanLab extends Model
{
    use HasFactory;

    protected $table = 'pemeriksaan_lab';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function parameter(): HasMany
    {
        return $this->hasMany(ParameterLab::class)->orderBy('urutan');
    }
}
