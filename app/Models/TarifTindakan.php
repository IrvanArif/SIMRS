<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TarifTindakan extends Model
{
    use HasFactory;

    protected $table = 'tarif_tindakan';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['berlaku_mulai' => 'date'];
    }

    public function tindakan(): BelongsTo
    {
        return $this->belongsTo(Tindakan::class);
    }

    public function penjamin(): BelongsTo
    {
        return $this->belongsTo(Penjamin::class);
    }
}
