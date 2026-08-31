<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tindakan extends Model
{
    use HasFactory;

    protected $table = 'tindakan';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }


    /**
     * Padanan kode prosedur untuk klaim. Boleh kosong: tidak semua tindakan
     * punya padanan ICD-9-CM.
     */
    public function icd9(): BelongsTo
    {
        return $this->belongsTo(Icd9::class);
    }
}
