<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RujukanLab extends Model
{
    use HasFactory;

    protected $table = 'rujukan_lab';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['nilai_min' => 'float', 'nilai_maks' => 'float'];
    }

    public function parameter(): BelongsTo
    {
        return $this->belongsTo(ParameterLab::class, 'parameter_lab_id');
    }

    public function rentang(): string
    {
        return rtrim(rtrim(number_format($this->nilai_min, 2, ',', '.'), '0'), ',')
            .' – '
            .rtrim(rtrim(number_format($this->nilai_maks, 2, ',', '.'), '0'), ',');
    }
}
