<?php

namespace App\Services;

use App\Enums\JenisDiagnosa;
use App\Enums\JenisPelayanan;
use App\Enums\StatusBerkasKlaim;
use App\Models\BerkasKlaim;
use App\Models\Kunjungan;
use App\Models\User;
use App\Support\KonteksAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PenyusunBerkasKlaim
{
    public function __construct(private readonly NomorDokumen $nomorDokumen) {}

    public function susun(Kunjungan $kunjungan, User $petugas): BerkasKlaim
    {
        if ($kunjungan->status->aktif()) {
            throw new RuntimeException(
                "Kunjungan {$kunjungan->no_kunjungan} belum selesai "
                ."(status {$kunjungan->status->label()}), jadi klaimnya belum bisa disusun."
            );
        }

        if ($kunjungan->berkasKlaim()->berlaku()->exists()) {
            throw new RuntimeException(
                "Kunjungan {$kunjungan->no_kunjungan} sudah punya berkas klaim yang berlaku."
            );
        }

        $this->pastikanLengkap($kunjungan);

        return DB::transaction(function () use ($kunjungan, $petugas) {
            $rawatInap = $kunjungan->rawatInap;
            $prosedur = $this->prosedur($kunjungan);

            $berkas = BerkasKlaim::create([
                'no_berkas' => $this->nomorDokumen->berikutnya('klaim', $kunjungan->tanggal),
                'kunjungan_id' => $kunjungan->id,
                'sep_id' => $kunjungan->sepBerlaku()->id,
                'no_kartu' => $kunjungan->no_kartu_penjamin,
                'nama_peserta' => $kunjungan->pasien->nama,
                'jenis_pelayanan' => $rawatInap === null
                    ? JenisPelayanan::RawatJalan
                    : JenisPelayanan::RawatInap,
                'kelas_rawat' => $kunjungan->sepBerlaku()->kelas_rawat,
                'tanggal_masuk' => $rawatInap?->waktu_masuk?->toDateString() ?? $kunjungan->tanggal,
                'tanggal_pulang' => $rawatInap?->waktu_pulang?->toDateString(),
                'lama_rawat' => $rawatInap === null
                    ? null
                    : app(PenghitungBiayaKamar::class)->lamaRawat($rawatInap),
                'total_biaya' => (int) $kunjungan->tagihan->total,
                'status' => StatusBerkasKlaim::Draf,
                'peringatan' => $prosedur['peringatan'],
                'disusun_oleh' => $petugas->id,
            ]);

            foreach ($kunjungan->diagnosa()->with('icd10')->get() as $diagnosa) {
                $berkas->diagnosa()->create([
                    'kode' => $diagnosa->icd10->kode,
                    'nama' => $diagnosa->icd10->nama_id,
                    'jenis' => $diagnosa->jenis->value,
                ]);
            }

            foreach ($prosedur['baris'] as $baris) {
                $berkas->prosedur()->create($baris);
            }

            return $berkas->refresh();
        });
    }

    public function ajukan(BerkasKlaim $berkas, User $petugas): BerkasKlaim
    {
        if (! $berkas->status->bisaDisunting()) {
            throw new RuntimeException(
                "Berkas {$berkas->no_berkas} berstatus {$berkas->status->label()} dan sudah tidak bisa diajukan lagi."
            );
        }

        $berkas->update([
            'status' => StatusBerkasKlaim::Diajukan,
            'diajukan_pada' => now(),
            'diajukan_oleh' => $petugas->id,
        ]);

        return $berkas->refresh();
    }

    public function batalkan(BerkasKlaim $berkas, User $petugas, string $alasan): BerkasKlaim
    {
        if (trim($alasan) === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan pembatalan berkas klaim wajib diisi.',
            ]);
        }

        if ($berkas->status === StatusBerkasKlaim::Batal) {
            throw new RuntimeException("Berkas {$berkas->no_berkas} sudah dibatalkan sebelumnya.");
        }

        return KonteksAudit::dengan(trim($alasan), function () use ($berkas) {
            $berkas->update(['status' => StatusBerkasKlaim::Batal]);

            return $berkas->refresh();
        });
    }

    /**
     * Mencatat jawaban verifikator. Penolakan wajib bercatatan — penolakan tanpa
     * alasan tidak bisa ditindaklanjuti siapa pun.
     */
    public function tandaiHasil(
        BerkasKlaim $berkas,
        StatusBerkasKlaim $hasil,
        User $petugas,
        ?string $catatan
    ): BerkasKlaim {
        if ($berkas->status !== StatusBerkasKlaim::Diajukan) {
            throw new RuntimeException(
                "Hasil verifikasi hanya bisa dicatat pada berkas yang sudah diajukan. "
                ."Berkas {$berkas->no_berkas} berstatus {$berkas->status->label()}."
            );
        }

        if (! in_array($hasil, [StatusBerkasKlaim::Disetujui, StatusBerkasKlaim::Ditolak], true)) {
            throw new RuntimeException('Hasil verifikasi hanya bisa disetujui atau ditolak.');
        }

        if ($hasil === StatusBerkasKlaim::Ditolak && trim((string) $catatan) === '') {
            throw ValidationException::withMessages([
                'catatan_verifikasi' => 'Catatan wajib diisi saat berkas ditolak.',
            ]);
        }

        $berkas->update([
            'status' => $hasil,
            'catatan_verifikasi' => trim((string) $catatan) === '' ? null : trim((string) $catatan),
            'diverifikasi_pada' => now(),
        ]);

        return $berkas->refresh();
    }

    /**
     * Aturan 85: seluruh kekurangan dikumpulkan dulu, baru dilaporkan bersama.
     * Penolakan yang menyebut satu kekurangan, lalu kekurangan berikutnya setelah
     * diperbaiki, memaksa petugas bolak-balik.
     */
    private function pastikanLengkap(Kunjungan $kunjungan): void
    {
        $kurang = [];

        if ($kunjungan->sepBerlaku() === null) {
            $kurang[] = 'SEP yang berlaku';
        }

        if ($kunjungan->tagihan === null) {
            $kurang[] = 'tagihan';
        }

        if (! $kunjungan->diagnosa()->where('jenis', JenisDiagnosa::Primer->value)->exists()) {
            $kurang[] = 'diagnosa primer';
        }

        if ($kunjungan->no_kartu_penjamin === null) {
            $kurang[] = 'nomor kartu peserta';
        }

        if ($kurang !== []) {
            throw ValidationException::withMessages([
                'berkas' => 'Berkas klaim belum lengkap. Yang masih kurang: '.implode(', ', $kurang).'.',
            ]);
        }
    }

    /**
     * Prosedur diambil dari pemetaan ICD-9-CM pada tindakan. Tindakan tanpa
     * pemetaan tidak menggagalkan klaim, tetapi dicatat sebagai peringatan supaya
     * pengkodenya tahu apa yang tidak terwakili (aturan 88).
     *
     * @return array{baris: list<array<string, mixed>>, peringatan: ?string}
     */
    private function prosedur(Kunjungan $kunjungan): array
    {
        $baris = [];
        $tanpaPemetaan = [];

        foreach ($kunjungan->tindakan()->with('tindakan.icd9')->get() as $item) {
            $icd9 = $item->tindakan->icd9;

            if ($icd9 === null) {
                $tanpaPemetaan[$item->tindakan->nama] = true;

                continue;
            }

            // Tindakan berbeda bisa memetakan ke kode yang sama; jumlahnya
            // digabungkan supaya tidak muncul dua baris berkode identik.
            $baris[$icd9->kode] ??= ['kode' => $icd9->kode, 'nama' => $icd9->nama, 'jumlah' => 0];
            $baris[$icd9->kode]['jumlah'] += (int) $item->jumlah;
        }

        return [
            'baris' => array_values($baris),
            'peringatan' => $tanpaPemetaan === [] ? null : 'Tindakan tanpa padanan ICD-9-CM: '
                .implode(', ', array_keys($tanpaPemetaan)).'.',
        ];
    }
}
