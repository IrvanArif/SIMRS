<?php

namespace App\Services;

/**
 * Nomor rekam medis: 6 digit sekuensial global, diberikan sekali dan tidak pernah
 * dipakai ulang meskipun pasiennya dihapus (aturan 3).
 */
class NomorRekamMedis
{
    public function __construct(private readonly PencatatNomor $pencatat) {}

    public function berikutnya(): string
    {
        return str_pad((string) $this->pencatat->ambil('rm'), 6, '0', STR_PAD_LEFT);
    }
}
