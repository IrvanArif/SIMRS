<?php

namespace App\Models;

use App\Enums\JenisLayanan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu tabel harga untuk seluruh jenis layanan. Menggantikan tarif_tindakan dan
 * harga_obat yang dulu terpisah dengan isi yang nyaris sama.
 */
class Tarif extends Model
{
    use HasFactory;

    protected $table = 'tarif';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'jenis_layanan' => JenisLayanan::class,
            'berlaku_mulai' => 'date',
        ];
    }

    public function penjamin(): BelongsTo
    {
        return $this->belongsTo(Penjamin::class);
    }

    /**
     * Layanan yang ditarifkan tidak memakai relasi polimorfik Eloquent karena
     * keempatnya berbeda tabel dan tidak pernah dimuat bersamaan — pemanggilnya
     * selalu sudah tahu jenis apa yang sedang ia tangani.
     */
    public function namaLayanan(): string
    {
        return match ($this->jenis_layanan) {
            JenisLayanan::Tindakan => Tindakan::find($this->layanan_id)?->nama ?? '—',
            JenisLayanan::Obat => Obat::find($this->layanan_id)?->nama ?? '—',
            JenisLayanan::Lab => PemeriksaanLab::find($this->layanan_id)?->nama ?? '—',
            JenisLayanan::Radiologi => PemeriksaanRadiologi::find($this->layanan_id)?->nama ?? '—',
        };
    }
}
