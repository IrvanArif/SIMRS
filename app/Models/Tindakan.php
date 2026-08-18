<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tindakan extends Model
{
    use HasFactory;

    protected $table = 'tindakan';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function tarif(): HasMany
    {
        return $this->hasMany(TarifTindakan::class);
    }
}
