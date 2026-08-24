<?php

namespace App\Models;

use App\Enums\PenandaHasil;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HasilLab extends Model
{
    use HasFactory;

    protected $table = 'hasil_lab';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['nilai' => 'float', 'penanda' => PenandaHasil::class];
    }

    public function orderDetail(): BelongsTo
    {
        return $this->belongsTo(OrderLabDetail::class, 'order_lab_detail_id');
    }

    public function parameter(): BelongsTo
    {
        return $this->belongsTo(ParameterLab::class, 'parameter_lab_id');
    }

    public function abnormal(): bool
    {
        return $this->penanda?->abnormal() ?? false;
    }
}
