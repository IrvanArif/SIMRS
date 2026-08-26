<?php

namespace App\Services;

use App\Enums\JenisDiagnosa;
use App\Enums\StatusKunjungan;
use App\Models\Diagnosa;
use App\Models\Kunjungan;
use App\Models\Pemeriksaan;
use App\Models\User;
use App\Support\KonteksAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PemeriksaanKlinis
{
    public function __construct(private readonly PenyusunTagihan $penyusunTagihan) {}

    public function catatVital(Kunjungan $kunjungan, array $data, User $perawat): Pemeriksaan
    {
        $this->pastikanKunjunganMasihBerjalan($kunjungan);

        $tervalidasi = Validator::make($data, [
            'sistolik' => ['required', 'integer', 'between:50,300'],
            'diastolik' => ['required', 'integer', 'between:30,200'],
            'nadi' => ['required', 'integer', 'between:20,250'],
            'suhu' => ['required', 'numeric', 'between:30,45'],
            'respirasi' => ['required', 'integer', 'between:5,80'],
            'berat_badan' => ['required', 'numeric', 'between:0.5,400'],
            'tinggi_badan' => ['required', 'integer', 'between:20,250'],
            'keluhan_awal' => ['required', 'string'],
            'alergi' => ['nullable', 'string', 'max:255'],
        ], [
            'suhu.between' => 'Suhu tubuh di luar rentang wajar (30–45 °C).',
            'sistolik.integer' => 'Tekanan darah sistolik harus berupa angka.',
            'keluhan_awal.required' => 'Keluhan awal wajib diisi.',
        ])->validate();

        return DB::transaction(function () use ($kunjungan, $tervalidasi, $perawat) {
            $pemeriksaan = Pemeriksaan::updateOrCreate(
                ['kunjungan_id' => $kunjungan->id],
                [...$tervalidasi, 'dicatat_perawat_id' => $perawat->id, 'waktu_perawat' => now()]
            );

            $kunjungan->update(['status' => StatusKunjungan::DiperiksaPerawat]);

            return $pemeriksaan;
        });
    }

    public function catatSoap(Kunjungan $kunjungan, array $data, User $dokter): Pemeriksaan
    {
        $this->pastikanKunjunganMasihBerjalan($kunjungan);

        $tervalidasi = Validator::make($data, $this->aturanSoap(), [
            'subjective.required' => 'Bagian Subjective wajib diisi.',
            'objective.required' => 'Bagian Objective wajib diisi.',
            'assessment.required' => 'Bagian Assessment wajib diisi.',
            'plan.required' => 'Bagian Plan wajib diisi.',
        ])->validate();

        return DB::transaction(function () use ($kunjungan, $tervalidasi, $dokter) {
            $pemeriksaan = Pemeriksaan::updateOrCreate(
                ['kunjungan_id' => $kunjungan->id],
                [...$tervalidasi, 'dicatat_dokter_id' => $dokter->id, 'waktu_dokter' => now()]
            );

            $kunjungan->update(['status' => StatusKunjungan::DiperiksaDokter]);

            return $pemeriksaan;
        });
    }

    public function tambahDiagnosa(
        Kunjungan $kunjungan,
        int $icd10Id,
        JenisDiagnosa $jenis,
        ?string $catatan = null
    ): Diagnosa {
        $this->pastikanKunjunganMasihBerjalan($kunjungan);

        if ($kunjungan->diagnosa()->where('icd10_id', $icd10Id)->exists()) {
            throw ValidationException::withMessages([
                'icd10_id' => 'Kode diagnosa ini sudah tercatat pada kunjungan tersebut.',
            ]);
        }

        if ($jenis === JenisDiagnosa::Primer
            && $kunjungan->diagnosa()->where('jenis', JenisDiagnosa::Primer->value)->exists()) {
            throw ValidationException::withMessages([
                'jenis' => 'Kunjungan ini sudah punya diagnosa primer. Hapus dulu yang lama bila ingin mengganti.',
            ]);
        }

        return $kunjungan->diagnosa()->create([
            'icd10_id' => $icd10Id,
            'jenis' => $jenis,
            'catatan' => $catatan,
        ]);
    }

    public function selesaikan(Kunjungan $kunjungan, User $dokter): Kunjungan
    {
        $this->pastikanKunjunganMasihBerjalan($kunjungan);

        $pemeriksaan = $kunjungan->pemeriksaan;

        if ($pemeriksaan === null || ! $pemeriksaan->soapLengkap()) {
            throw new RuntimeException(
                'Kunjungan belum bisa diselesaikan: SOAP harus terisi lengkap.'
            );
        }

        if (! $kunjungan->diagnosa()->where('jenis', JenisDiagnosa::Primer->value)->exists()) {
            throw new RuntimeException(
                'Kunjungan belum bisa diselesaikan: diagnosa primer belum ditetapkan.'
            );
        }

        // Aturan 74: masa rawat inap punya penutupnya sendiri. Menutup lewat
        // sini akan melewatkan pelepasan bed dan pencatatan cara pulang.
        if ($kunjungan->sedangDirawatInap()) {
            throw new RuntimeException(
                'Kunjungan ini sedang rawat inap. Penutupnya adalah pemulangan pasien, '
                .'bukan penyelesaian kunjungan poli.'
            );
        }

        // Aturan 37: kunjungan ditutup setelah hasil keluar, supaya diagnosanya
        // benar-benar berdasar hasil — bukan ditulis sambil menunggu.
        $orderMenunggu = $kunjungan->orderLab()->belumSelesai()->first();

        if ($orderMenunggu !== null) {
            throw new RuntimeException(
                "Kunjungan belum bisa diselesaikan: hasil order {$orderMenunggu->no_order} belum divalidasi."
            );
        }

        // Aturan 50: alasan yang sama berlaku untuk radiologi — diagnosanya harus
        // berdasar bacaan dokter radiologi, bukan dugaan sambil menunggu.
        $radiologiMenunggu = $kunjungan->orderRadiologi()->belumSelesai()->first();

        if ($radiologiMenunggu !== null) {
            throw new RuntimeException(
                "Kunjungan belum bisa diselesaikan: ekspertise order {$radiologiMenunggu->no_order} belum ditulis."
            );
        }

        return DB::transaction(function () use ($kunjungan) {
            $kunjungan->update([
                'status' => StatusKunjungan::Selesai,
                'waktu_selesai' => now(),
            ]);

            $this->penyusunTagihan->susun($kunjungan->refresh());

            return $kunjungan->refresh();
        });
    }

    public function koreksi(Kunjungan $kunjungan, array $data, User $dokter, string $alasan): Pemeriksaan
    {
        if (trim($alasan) === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan koreksi wajib diisi.',
            ]);
        }

        $pemeriksaan = $kunjungan->pemeriksaan;

        if ($pemeriksaan->dicatat_dokter_id !== $dokter->id) {
            throw new RuntimeException(
                'Koreksi rekam medis hanya boleh dilakukan oleh dokter yang mencatatnya.'
            );
        }

        $tervalidasi = Validator::make($data, $this->aturanSoap())->validate();

        return KonteksAudit::dengan($alasan, function () use ($pemeriksaan, $tervalidasi) {
            $pemeriksaan->update($tervalidasi);

            return $pemeriksaan->refresh();
        });
    }

    private function aturanSoap(): array
    {
        return [
            'subjective' => ['required', 'string'],
            'objective' => ['required', 'string'],
            'assessment' => ['required', 'string'],
            'plan' => ['required', 'string'],
        ];
    }

    private function pastikanKunjunganMasihBerjalan(Kunjungan $kunjungan): void
    {
        if (! $kunjungan->status->aktif()) {
            throw new RuntimeException(
                'Kunjungan yang sudah selesai atau dibatalkan tidak bisa diubah lagi.'
            );
        }
    }
}
