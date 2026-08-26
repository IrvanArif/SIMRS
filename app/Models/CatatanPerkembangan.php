<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catatan perkembangan pasien terintegrasi: perawat dan dokter menulis ke
 * berkas yang sama, dan perannya tercatat pada tiap barisnya.
 */
class CatatanPerkembangan extends Model
{
    use HasFactory;

    protected $table = 'catatan_perkembangan';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['waktu' => 'datetime'];
    }

    public function rawatInap(): BelongsTo
    {
        return $this->belongsTo(RawatInap::class);
    }

    public function penulis(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditulis_oleh');
    }
}
