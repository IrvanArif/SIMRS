<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Enums\StatusBerkasKlaim;
use App\Models\BerkasKlaim;
use App\Models\User;

class BerkasKlaimPolicy
{
    /**
     * Rekam medis yang menyusun klaim, bukan kasir: klaim disusun dari kode
     * diagnosa dan prosedur, dan pengkodean adalah pekerjaan rekam medis.
     */
    public function susun(User $user): bool
    {
        return $user->hasRole(Peran::RekamMedis->value);
    }

    public function ajukan(User $user, BerkasKlaim $berkas): bool
    {
        return $user->hasRole(Peran::RekamMedis->value) && $berkas->status->bisaDisunting();
    }

    public function batalkan(User $user, BerkasKlaim $berkas): bool
    {
        return $user->hasRole(Peran::RekamMedis->value)
            && $berkas->status !== StatusBerkasKlaim::Batal;
    }

    public function verifikasi(User $user, BerkasKlaim $berkas): bool
    {
        return $user->hasRole(Peran::RekamMedis->value)
            && $berkas->status === StatusBerkasKlaim::Diajukan;
    }

    public function lihat(User $user): bool
    {
        return $user->hasAnyRole([
            Peran::RekamMedis->value, Peran::Kasir->value, Peran::Admin->value,
        ]);
    }
}
