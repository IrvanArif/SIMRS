<?php

namespace App\Services;

use App\Enums\StatusOrderRadiologi;
use App\Models\OrderRadiologi;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PelaksanaanRadiologi
{
    public function kerjakan(OrderRadiologi $order, string $noFilm, User $radiografer): OrderRadiologi
    {
        if (trim($noFilm) === '') {
            // Aturan 51: tanpa nomor film, citra yang sudah diambil tidak bisa
            // ditemukan lagi di arsip.
            throw ValidationException::withMessages([
                'no_film' => 'Nomor film wajib diisi.',
            ]);
        }

        if (! $order->status->bisaDikerjakan()) {
            throw new RuntimeException(
                "Order {$order->no_order} berstatus {$order->status->label()} dan tidak bisa dikerjakan."
            );
        }

        return DB::transaction(function () use ($order, $noFilm, $radiografer) {
            $terkunci = OrderRadiologi::whereKey($order->id)->lockForUpdate()->first();

            if (! $terkunci->status->bisaDikerjakan()) {
                throw new RuntimeException('Order ini baru saja dikerjakan petugas lain.');
            }

            $terkunci->update([
                'status' => StatusOrderRadiologi::Dikerjakan,
                'no_film' => trim($noFilm),
                'waktu_dikerjakan' => now(),
                'dikerjakan_oleh' => $radiografer->id,
            ]);

            return $terkunci->refresh();
        });
    }
}
