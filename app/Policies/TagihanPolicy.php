<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Models\Tagihan;
use App\Models\User;

class TagihanPolicy
{
    public function lihat(User $user, Tagihan $tagihan): bool
    {
        return $user->hasAnyRole([Peran::Kasir->value, Peran::Admin->value, Peran::RekamMedis->value]);
    }

    public function proses(User $user, Tagihan $tagihan): bool
    {
        return $user->hasRole(Peran::Kasir->value);
    }
}
