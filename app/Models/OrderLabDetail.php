<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderLabDetail extends Model
{
    use HasFactory;

    protected $table = 'order_lab_detail';

    protected $guarded = [];

    public function order(): BelongsTo
    {
        return $this->belongsTo(OrderLab::class, 'order_lab_id');
    }

    public function pemeriksaan(): BelongsTo
    {
        return $this->belongsTo(PemeriksaanLab::class, 'pemeriksaan_lab_id');
    }

    public function hasil(): HasMany
    {
        return $this->hasMany(HasilLab::class, 'order_lab_detail_id');
    }
}
