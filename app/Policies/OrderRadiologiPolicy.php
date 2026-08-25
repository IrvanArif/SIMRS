<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Models\OrderRadiologi;
use App\Models\User;

class OrderRadiologiPolicy
{
    public function pesan(User $user): bool
    {
        return $user->hasRole(Peran::Dokter->value);
    }

    public function kerjakan(User $user, OrderRadiologi $order): bool
    {
        return $user->hasRole(Peran::Radiografer->value) && ! $order->status->selesai();
    }

    /**
     * Aturan 54: radiografer mengoperasikan alatnya, dokter yang menyimpulkan.
     * Pemisahan ini bukan formalitas — menyimpulkan temuan adalah tindakan medis.
     *
     * Apakah ordernya sudah siap diekspertise adalah urusan PenulisanEkspertise;
     * policy hanya menjawab siapa yang berwenang.
     */
    public function ekspertise(User $user, OrderRadiologi $order): bool
    {
        return $user->hasRole(Peran::Dokter->value);
    }

    /**
     * Aturan 55: citra yang sudah diambil tapi belum dibaca bukan hasil.
     */
    public function baca(User $user, OrderRadiologi $order): bool
    {
        return $user->hasAnyRole([
            Peran::Dokter->value, Peran::Radiografer->value, Peran::RekamMedis->value,
        ]) && $order->terbacaDokter();
    }
}
