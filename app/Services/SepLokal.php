<?php

namespace App\Services;

use App\Kontrak\PenerbitSep;
use App\Models\Kunjungan;
use App\Models\Sep;

/**
 * Penerbit SEP untuk lingkungan tanpa kredensial BPJS. Nomornya diterbitkan
 * sendiri lewat PencatatNomor sehingga aman balapan dan deterministik.
 *
 * Ia bukan tiruan VClaim: tidak memeriksa keaktifan peserta, tidak memvalidasi
 * rujukan, dan tidak menghubungi siapa pun. Nomornya berawalan SEP dan ditandai
 * `lokal` pada barisnya supaya tidak pernah disangka nomor sungguhan.
 */
class SepLokal implements PenerbitSep
{
    public function __construct(private readonly NomorDokumen $nomorDokumen) {}

    public function terbitkan(Kunjungan $kunjungan, string $diagnosaAwal): string
    {
        return $this->nomorDokumen->berikutnya('sep', $kunjungan->tanggal);
    }

    public function batalkan(Sep $sep, string $alasan): void
    {
        // Tidak ada pihak luar yang perlu diberi tahu; status barisnya diubah
        // oleh PenerbitanSep.
    }

    public function nama(): string
    {
        return 'lokal';
    }
}
