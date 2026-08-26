<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Nomor dokumen berformat AWALAN-YYYYMMDD-NNNN dengan urutan yang direset harian.
 */
class NomorDokumen
{
    private const AWALAN = [
        'kunjungan' => 'KJ',
        'resep' => 'RS',
        'tagihan' => 'TG',
        'kuitansi' => 'KW',
        'lab' => 'LB',
        'radiologi' => 'RD',
        'rawat_inap' => 'RI',
    ];

    public function __construct(private readonly PencatatNomor $pencatat) {}

    public function berikutnya(string $jenis, ?CarbonInterface $tanggal = null): string
    {
        if (! array_key_exists($jenis, self::AWALAN)) {
            throw new InvalidArgumentException("Jenis dokumen tidak dikenal: {$jenis}");
        }

        $tanggal ??= Carbon::today();
        $periode = $tanggal->format('Y-m-d');
        $urutan = $this->pencatat->ambil("dokumen:{$jenis}", $periode);

        return sprintf(
            '%s-%s-%04d',
            self::AWALAN[$jenis],
            $tanggal->format('Ymd'),
            $urutan
        );
    }
}
