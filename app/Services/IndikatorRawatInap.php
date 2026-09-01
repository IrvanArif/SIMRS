<?php

namespace App\Services;

use App\Enums\StatusRawatInap;
use App\Models\Bed;
use App\Models\OkupansiBed;
use App\Models\RawatInap;
use Illuminate\Support\Carbon;

/**
 * BOR, LOS, TOI, dan BTO — indikator baku rumah sakit Indonesia.
 *
 * Bisa dihitung tepat karena okupansi disimpan berpenggal: hari rawat dihitung
 * dari irisan tiap penggal dengan periodenya, bukan dari selisih tanggal masuk
 * dan pulang. Pasien yang masuk sebelum periode dan pulang sesudahnya hanya
 * menyumbang hari di dalam periode — tanpa pemotongan itu, BOR bisa melampaui 100%.
 */
class IndikatorRawatInap
{
    /**
     * @return array{
     *     bed_tersedia: int, hari_rawat: int, pasien_keluar: int,
     *     bor: float, los: float, toi: float, bto: float
     * }
     */
    public function hitung(RentangTanggal $rentang): array
    {
        // Bed nonaktif bukan kapasitas: bed rusak tidak bisa ditempati siapa pun
        // (aturan 89).
        $bedTersedia = Bed::where('aktif', true)->count();
        $hariRawat = $this->hariRawat($rentang);
        $pasienKeluar = $this->pasienKeluar($rentang);

        $hariTersedia = $bedTersedia * $rentang->hari();

        return [
            'bed_tersedia' => $bedTersedia,
            'hari_rawat' => $hariRawat,
            'pasien_keluar' => $pasienKeluar,
            'bor' => $hariTersedia === 0 ? 0.0 : round($hariRawat / $hariTersedia * 100, 2),
            'los' => $pasienKeluar === 0 ? 0.0 : round($hariRawat / $pasienKeluar, 2),
            // TOI bisa keluar negatif saat bed dinonaktifkan setelah dipakai:
            // kapasitasnya menyusut sementara hari rawatnya sudah tercatat.
            // Bed menganggur bernilai negatif tidak bermakna, jadi dijepit di nol.
            'toi' => $pasienKeluar === 0
                ? 0.0
                : round(max(0, $hariTersedia - $hariRawat) / $pasienKeluar, 2),
            'bto' => $bedTersedia === 0 ? 0.0 : round($pasienKeluar / $bedTersedia, 2),
        ];
    }

    /**
     * Jumlah hari kamar terpakai di dalam periode, dari irisan tiap penggal
     * okupansi dengan periodenya.
     */
    private function hariRawat(RentangTanggal $rentang): int
    {
        $batasAtas = $rentang->batasAtas();
        $hariIni = Carbon::today();

        $penggal = OkupansiBed::where('mulai', '<', $batasAtas)
            ->where(function ($q) use ($rentang) {
                $q->whereNull('selesai')->orWhere('selesai', '>=', $rentang->awal);
            })
            ->get();

        $total = 0;

        foreach ($penggal as $satu) {
            // Penggal yang masih berjalan dianggap berakhir hari ini, atau di
            // batas periode bila periodenya sudah lewat — pasiennya memang
            // menempati kamar itu sepanjang periode tersebut.
            $selesai = $satu->selesai ?? $hariIni->copy()->min($batasAtas);

            $mulaiIris = $satu->mulai->copy()->max($rentang->awal);
            $selesaiIris = $selesai->copy()->min($batasAtas);

            $hari = (int) $mulaiIris->diffInDays($selesaiIris);

            // Penggal sehari — masuk dan pindah/pulang di tanggal yang sama —
            // tetap terhitung satu hari selama tanggalnya jatuh di periode ini.
            if ($hari <= 0) {
                $hari = $satu->mulai->betweenIncluded($rentang->awal, $rentang->akhir) ? 1 : 0;
            }

            $total += max(0, $hari);
        }

        return $total;
    }

    private function pasienKeluar(RentangTanggal $rentang): int
    {
        return RawatInap::where('status', StatusRawatInap::Pulang->value)
            ->whereBetween('waktu_pulang', [
                $rentang->awal->copy()->startOfDay(),
                $rentang->akhir->copy()->endOfDay(),
            ])
            ->count();
    }
}
