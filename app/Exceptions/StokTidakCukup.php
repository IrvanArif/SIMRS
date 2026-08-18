<?php

namespace App\Exceptions;

use RuntimeException;

class StokTidakCukup extends RuntimeException
{
    public static function untuk(string $namaObat, int $diminta, int $tersedia): self
    {
        return new self(
            "Stok {$namaObat} tidak cukup: diminta {$diminta}, tersedia {$tersedia}."
        );
    }
}
