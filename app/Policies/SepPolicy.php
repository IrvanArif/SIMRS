<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Models\Sep;
use App\Models\User;

class SepPolicy
{
    /**
     * SEP terbit di meja pendaftaran, bersamaan dengan pengecekan kepesertaan —
     * di situlah nomor kartu dan rujukan pasien berada.
     */
    public function terbitkan(User $user): bool
    {
        return $user->hasRole(Peran::Admisi->value);
    }

    public function batalkan(User $user, Sep $sep): bool
    {
        return $user->hasRole(Peran::Admisi->value) && $sep->berlaku();
    }

    public function lihat(User $user): bool
    {
        return $user->hasAnyRole([
            Peran::Admisi->value, Peran::RekamMedis->value, Peran::Kasir->value, Peran::Admin->value,
        ]);
    }
}
