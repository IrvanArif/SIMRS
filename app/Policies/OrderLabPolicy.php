<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Models\OrderLab;
use App\Models\User;

class OrderLabPolicy
{
    public function pesan(User $user): bool
    {
        return $user->hasRole(Peran::Dokter->value);
    }

    public function kerjakan(User $user, OrderLab $order): bool
    {
        return $user->hasRole(Peran::Analis->value) && ! $order->status->selesai();
    }

    /**
     * Policy menjawab siapa yang berwenang; apakah order ini sudah siap
     * divalidasi adalah urusan PemeriksaanLaboratorium. Menaruh aturan yang
     * sama di dua tempat membuat keduanya bisa berselisih.
     */
    public function validasi(User $user, OrderLab $order): bool
    {
        return $user->hasRole(Peran::Analis->value);
    }

    /**
     * Aturan 42: hasil hanya boleh dibaca setelah divalidasi. Analis yang
     * mengerjakannya tetap bisa melihat sebelum itu lewat layar entri.
     */
    public function baca(User $user, OrderLab $order): bool
    {
        return $user->hasAnyRole([Peran::Dokter->value, Peran::Analis->value, Peran::RekamMedis->value])
            && $order->terbacaDokter();
    }
}
