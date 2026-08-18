<?php

namespace App\Support;

use Closure;

/**
 * Menandai alasan pada perubahan data klinis supaya ikut tercatat di audit log.
 */
class KonteksAudit
{
    private static ?string $alasan = null;

    public static function dengan(string $alasan, Closure $aksi): mixed
    {
        $sebelumnya = self::$alasan;
        self::$alasan = $alasan;

        try {
            return $aksi();
        } finally {
            // finally, bukan sekadar baris setelah $aksi(): satu exception tidak boleh
            // membuat perubahan berikutnya ikut tercatat dengan alasan yang salah.
            self::$alasan = $sebelumnya;
        }
    }

    public static function alasan(): ?string
    {
        return self::$alasan;
    }
}
