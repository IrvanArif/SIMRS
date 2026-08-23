<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Enums\StatusResep;
use App\Models\Resep;
use App\Models\User;

class ResepPolicy
{
    public function siapkan(User $user, Resep $resep): bool
    {
        return $user->hasRole(Peran::Apoteker->value) && $resep->status->bisaDisiapkan();
    }

    public function serahkan(User $user, Resep $resep): bool
    {
        return $user->hasRole(Peran::Apoteker->value)
            && in_array($resep->status, [StatusResep::Disiapkan, StatusResep::Dibuat], true);
    }

    /**
     * Tanpa parameter model kedua supaya bisa dipanggil sebagai
     * Gate::allows('kelolaStok', Resep::class) dari layar yang tidak punya
     * resep tertentu, seperti penerimaan batch dan kartu stok.
     */
    public function kelolaStok(User $user): bool
    {
        return $user->hasRole(Peran::Apoteker->value);
    }
}
