<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Kode prosedur ICD-9-CM. Klaim menuntut kode prosedur di samping diagnosa;
 * tanpa ini berkas klaim tidak akan lolos verifikasi.
 */
class Icd9 extends Model
{
    use HasFactory;

    protected $table = 'icd9';

    protected $guarded = [];
}
