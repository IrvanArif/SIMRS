<?php

namespace App\Models;

use App\Enums\JenisDiagnosa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Diagnosa extends Model
{
    use HasFactory;

    protected $table = 'diagnosa';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['jenis' => JenisDiagnosa::class];
    }

    public function kunjungan(): BelongsTo
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function icd10(): BelongsTo
    {
        return $this->belongsTo(Icd10::class);
    }
}
