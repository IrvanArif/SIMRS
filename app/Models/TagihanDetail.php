<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TagihanDetail extends Model
{
    use HasFactory;

    protected $table = 'tagihan_detail';

    protected $guarded = [];

    public function tagihan(): BelongsTo
    {
        return $this->belongsTo(Tagihan::class);
    }

    /**
     * Layanan asal baris ini: tindakan, obat, atau nanti pemeriksaan laboratorium.
     */
    public function sumber(): MorphTo
    {
        return $this->morphTo(null, 'sumber_tipe', 'sumber_id');
    }
}
