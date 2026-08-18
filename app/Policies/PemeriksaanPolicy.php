<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Models\Pemeriksaan;
use App\Models\User;

class PemeriksaanPolicy
{
    public function ubah(User $user, Pemeriksaan $pemeriksaan): bool
    {
        if ($user->hasRole(Peran::Perawat->value)) {
            return $pemeriksaan->kunjungan->status->aktif();
        }

        if (! $user->hasRole(Peran::Dokter->value) || $user->dokter === null) {
            return false;
        }

        if ($pemeriksaan->kunjungan->status->aktif()) {
            return (int) $user->dokter->poli_id === (int) $pemeriksaan->kunjungan->poli_id;
        }

        // Setelah kunjungan selesai, hanya dokter yang mencatatnya yang boleh mengoreksi.
        return $pemeriksaan->dicatat_dokter_id === $user->id;
    }
}
