<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderRadiologiDetail extends Model
{
    use HasFactory;

    protected $table = 'order_radiologi_detail';

    protected $guarded = [];

    public function order(): BelongsTo
    {
        return $this->belongsTo(OrderRadiologi::class, 'order_radiologi_id');
    }

    public function pemeriksaan(): BelongsTo
    {
        return $this->belongsTo(PemeriksaanRadiologi::class, 'pemeriksaan_radiologi_id');
    }

    public function ekspertise(): HasOne
    {
        return $this->hasOne(EkspertiseRadiologi::class, 'order_radiologi_detail_id');
    }
}
