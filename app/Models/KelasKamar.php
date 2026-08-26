<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KelasKamar extends Model
{
    use HasFactory;

    protected $table = 'kelas_kamar';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }
}
