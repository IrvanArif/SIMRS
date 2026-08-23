<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParameterLab extends Model
{
    use HasFactory;

    protected $table = 'parameter_lab';

    protected $guarded = [];

    public function pemeriksaan(): BelongsTo
    {
        return $this->belongsTo(PemeriksaanLab::class, 'pemeriksaan_lab_id');
    }

    public function rujukan(): HasMany
    {
        return $this->hasMany(RujukanLab::class, 'parameter_lab_id');
    }
}
