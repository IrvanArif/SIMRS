<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengambilanBatch extends Model
{
    protected $table = 'pengambilan_batch';

    protected $guarded = [];

    public function resepDetail(): BelongsTo
    {
        return $this->belongsTo(ResepDetail::class, 'resep_detail_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(BatchObat::class, 'batch_obat_id');
    }
}
