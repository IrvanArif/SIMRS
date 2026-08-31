<?php

namespace App\Services;

use App\Enums\JenisPelayanan;
use App\Kontrak\PenerbitSep;
use App\Models\Kunjungan;
use App\Models\Sep;
use App\Models\User;
use App\Support\KonteksAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PenerbitanSep
{
    public function __construct(private readonly PenerbitSep $penerbit) {}

    public function terbitkan(
        Kunjungan $kunjungan,
        User $petugas,
        string $diagnosaAwal,
        ?string $noRujukan = null
    ): Sep {
        // Aturan 78: SEP adalah bukti penjaminan. Pasien tunai tidak dijamin
        // siapa pun, jadi tidak ada yang bisa dibuktikan.
        if (! $kunjungan->penjamin->ditanggung()) {
            throw new RuntimeException(
                "Kunjungan ini berpenjamin {$kunjungan->penjamin->nama}, bukan penjamin yang menerbitkan SEP."
            );
        }

        Validator::make([
            'no_kartu' => $kunjungan->no_kartu_penjamin,
            'diagnosa_awal' => trim($diagnosaAwal),
        ], [
            'no_kartu' => ['required', 'string', 'max:30'],
            'diagnosa_awal' => ['required', 'string', 'max:255'],
        ], [
            'no_kartu.required' => 'Nomor kartu peserta belum terisi pada kunjungan ini.',
            'diagnosa_awal.required' => 'Diagnosa awal wajib diisi.',
        ])->validate();

        if ($kunjungan->sep()->berlaku()->exists()) {
            throw new RuntimeException(
                "Kunjungan {$kunjungan->no_kunjungan} sudah punya SEP yang berlaku."
            );
        }

        return DB::transaction(function () use ($kunjungan, $petugas, $diagnosaAwal, $noRujukan) {
            $rawatInap = $kunjungan->rawatInap;

            return Sep::create([
                'no_sep' => $this->penerbit->terbitkan($kunjungan, trim($diagnosaAwal)),
                'kunjungan_id' => $kunjungan->id,
                'no_kartu' => $kunjungan->no_kartu_penjamin,
                // Aturan 81: mengikuti kenyataan, bukan pilihan petugas.
                'jenis_pelayanan' => $rawatInap === null
                    ? JenisPelayanan::RawatJalan
                    : JenisPelayanan::RawatInap,
                'kelas_rawat' => $rawatInap?->bedSekarang()?->kelas->nama
                    ?? $rawatInap?->kelasDiminta->nama,
                'diagnosa_awal' => trim($diagnosaAwal),
                'no_rujukan' => $noRujukan,
                'tanggal' => $kunjungan->tanggal,
                'status' => Sep::BERLAKU,
                'diterbitkan_dengan' => $this->penerbit->nama(),
                'diterbitkan_oleh' => $petugas->id,
            ]);
        });
    }

    public function batalkan(Sep $sep, User $petugas, string $alasan): Sep
    {
        if (trim($alasan) === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan pembatalan SEP wajib diisi.',
            ]);
        }

        if (! $sep->berlaku()) {
            throw new RuntimeException("SEP {$sep->no_sep} sudah dibatalkan sebelumnya.");
        }

        return KonteksAudit::dengan(trim($alasan), function () use ($sep, $alasan) {
            return DB::transaction(function () use ($sep, $alasan) {
                $this->penerbit->batalkan($sep, trim($alasan));

                $sep->update(['status' => Sep::BATAL]);

                return $sep->refresh();
            });
        });
    }
}
