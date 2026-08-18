<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Enums\StatusKunjungan;
use App\Models\Kunjungan;
use App\Models\User;

class KunjunganPolicy
{
    public function periksa(User $user, Kunjungan $kunjungan): bool
    {
        if (! $user->hasRole(Peran::Dokter->value) || $user->dokter === null) {
            return false;
        }

        return (int) $user->dokter->poli_id === (int) $kunjungan->poli_id;
    }

    public function daftarkan(User $user): bool
    {
        return $user->hasRole(Peran::Admisi->value);
    }

    public function batalkan(User $user, Kunjungan $kunjungan): bool
    {
        return $user->hasRole(Peran::Admisi->value)
            && $kunjungan->status === StatusKunjungan::Terdaftar;
    }
}
