<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EkspertiseRadiologi extends Model
{
    use HasFactory;

    protected $table = 'ekspertise_radiologi';

    protected $guarded = [];

    public function orderDetail(): BelongsTo
    {
        return $this->belongsTo(OrderRadiologiDetail::class, 'order_radiologi_detail_id');
    }
}
