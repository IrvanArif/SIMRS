<?php

namespace App\Models;

use App\Enums\StatusResep;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Resep extends Model
{
    use HasFactory;

    protected $table = 'resep';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => StatusResep::class,
            'disiapkan_pada' => 'datetime',
            'diserahkan_pada' => 'datetime',
        ];
    }

    public function kunjungan(): BelongsTo
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function dokter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dokter_id');
    }

    public function detail(): HasMany
    {
        return $this->hasMany(ResepDetail::class);
    }
}
