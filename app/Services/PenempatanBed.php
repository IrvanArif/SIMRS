<?php

namespace App\Services;

use App\Enums\JenisLayanan;
use App\Models\Bed;
use App\Models\OkupansiBed;
use App\Models\RawatInap;
use App\Models\User;
use App\Support\KonteksAudit;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PenempatanBed
{
    public function __construct(private readonly PencariTarif $pencariTarif) {}

    public function tempatkan(RawatInap $rawatInap, Bed $bed, User $petugas): OkupansiBed
    {
        $this->pastikanMasaRawatBerjalan($rawatInap);

        if ($rawatInap->okupansi()->berjalan()->exists()) {
            throw new RuntimeException(
                'Pasien ini sudah menempati sebuah bed. Untuk berpindah, pakai pemindahan bed.'
            );
        }

        return DB::transaction(function () use ($rawatInap, $bed, $petugas) {
            $okupansi = $this->bukaPenggal($rawatInap, $bed, $petugas, Carbon::today());

            if ($rawatInap->waktu_masuk === null) {
                // Perintah rawat inap saja belum berarti pasien sudah masuk;
                // yang menandai masuk adalah saat ia benar-benar menempati bed.
                $rawatInap->update(['waktu_masuk' => now()]);
            }

            return $okupansi;
        });
    }

    public function pindahkan(RawatInap $rawatInap, Bed $tujuan, User $petugas, string $alasan): OkupansiBed
    {
        if (trim($alasan) === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan pemindahan bed wajib diisi.',
            ]);
        }

        $this->pastikanMasaRawatBerjalan($rawatInap);

        $berjalan = $rawatInap->okupansi()->berjalan()->first();

        if ($berjalan === null) {
            throw new RuntimeException(
                'Pasien belum menempati bed mana pun, jadi belum ada yang bisa dipindahkan.'
            );
        }

        if ((int) $berjalan->bed_id === (int) $tujuan->id) {
            throw new RuntimeException('Pasien sudah berada di bed tersebut.');
        }

        return KonteksAudit::dengan(trim($alasan), function () use ($rawatInap, $tujuan, $petugas, $berjalan) {
            return DB::transaction(function () use ($rawatInap, $tujuan, $petugas, $berjalan) {
                // Hari peralihan menjadi milik penggal yang ditinggalkan: kamar
                // lama sudah terpakai hari itu, kamar baru baru terpakai
                // keesokan harinya.
                $this->tutupPenggal($berjalan, Carbon::today());

                return $this->bukaPenggal($rawatInap, $tujuan, $petugas, Carbon::today()->addDay());
            });
        });
    }

    /**
     * Melepas bed yang sedang ditempati. Dipakai pemulangan.
     */
    public function lepaskan(RawatInap $rawatInap, ?CarbonInterface $tanggal = null): void
    {
        $berjalan = $rawatInap->okupansi()->berjalan()->first();

        if ($berjalan === null) {
            return;
        }

        DB::transaction(function () use ($berjalan, $tanggal) {
            $this->tutupPenggal($berjalan, $tanggal ? Carbon::parse($tanggal) : Carbon::today());
        });
    }

    private function bukaPenggal(RawatInap $rawatInap, Bed $bed, User $petugas, Carbon $mulai): OkupansiBed
    {
        $terkunci = Bed::whereKey($bed->id)->lockForUpdate()->firstOrFail();

        if (! $terkunci->aktif) {
            throw new RuntimeException("Bed {$terkunci->nomor} sedang tidak aktif dan belum bisa ditempati.");
        }

        // Statusnya dibaca ulang dari basis data di dalam kunci, bukan dari objek
        // yang dibawa pemanggil: layar admisi memegang objeknya lintas permintaan.
        if ($terkunci->rawat_inap_id !== null) {
            throw new RuntimeException("Bed {$terkunci->nomor} sudah ditempati pasien lain.");
        }

        $okupansi = OkupansiBed::create([
            'rawat_inap_id' => $rawatInap->id,
            'bed_id' => $terkunci->id,
            'tarif_harian' => $this->pencariTarif->untuk(
                JenisLayanan::Kamar,
                (int) $terkunci->kelas_kamar_id,
                (int) $rawatInap->kunjungan->penjamin_id,
                $mulai
            ),
            'mulai' => $mulai,
            'ditempatkan_oleh' => $petugas->id,
        ]);

        $terkunci->update(['rawat_inap_id' => $rawatInap->id]);

        return $okupansi->refresh();
    }

    private function tutupPenggal(OkupansiBed $penggal, Carbon $selesai): void
    {
        $penggal->update(['selesai' => $selesai]);

        Bed::whereKey($penggal->bed_id)->update(['rawat_inap_id' => null]);
    }

    private function pastikanMasaRawatBerjalan(RawatInap $rawatInap): void
    {
        if (! $rawatInap->status->aktif()) {
            throw new RuntimeException(
                "Masa rawat {$rawatInap->no_rawat_inap} berstatus {$rawatInap->status->label()}."
            );
        }
    }
}
