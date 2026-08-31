<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Models\RawatInap;
use App\Models\User;

class RawatInapPolicy
{
    /**
     * Memerintahkan rawat inap adalah keputusan medis: ia menentukan pasien
     * menginap, dan sejak itu biaya kamar berjalan.
     */
    public function perintahkan(User $user): bool
    {
        return $user->hasRole(Peran::Dokter->value);
    }

    /**
     * Menempatkan pasien di bed adalah pekerjaan admisi — ia yang tahu bed mana
     * kosong dan mengatur perputarannya.
     */
    public function tempatkan(User $user, RawatInap $rawatInap): bool
    {
        return $user->hasRole(Peran::Admisi->value) && $rawatInap->status->aktif();
    }

    public function rawat(User $user, RawatInap $rawatInap): bool
    {
        return $user->hasAnyRole([Peran::Dokter->value, Peran::Perawat->value])
            && $rawatInap->status->aktif();
    }

    /**
     * Memulangkan berarti menetapkan diagnosa akhir dan cara pulang; keduanya
     * keputusan medis, bukan administratif.
     */
    public function pulangkan(User $user, RawatInap $rawatInap): bool
    {
        return $user->hasRole(Peran::Dokter->value) && $rawatInap->status->aktif();
    }

    /**
     * Kasir ikut boleh melihat karena ia harus bisa menjelaskan rincian kamar
     * pada tagihan, tanpa berwenang mengubah apa pun.
     */
    public function lihat(User $user, RawatInap $rawatInap): bool
    {
        return $user->hasAnyRole([
            Peran::Dokter->value, Peran::Perawat->value, Peran::Admisi->value,
            Peran::RekamMedis->value, Peran::Kasir->value,
        ]);
    }
}
