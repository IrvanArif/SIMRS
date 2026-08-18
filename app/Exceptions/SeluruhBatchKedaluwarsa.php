<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Dipisahkan dari StokTidakCukup karena tindak lanjutnya berbeda:
 * yang satu perlu pemesanan, yang satu perlu pemusnahan.
 */
class SeluruhBatchKedaluwarsa extends RuntimeException
{
    public static function untuk(string $namaObat): self
    {
        return new self(
            "Seluruh batch {$namaObat} sudah kedaluwarsa dan tidak boleh diserahkan. Perlu pemusnahan dan pemesanan ulang."
        );
    }
}
