<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ruang extends Model
{
    use HasFactory;

    protected $table = 'ruang';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function bed(): HasMany
    {
        return $this->hasMany(Bed::class)->orderBy('nomor');
    }
}
