<?php

namespace App\Services;

use App\Enums\StatusAntrian;
use App\Enums\StatusKunjungan;
use App\Models\Antrian;
use App\Models\Dokter;
use App\Models\Kunjungan;
use App\Models\Penjamin;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PendaftaranKunjungan
{
    public function __construct(
        private readonly NomorDokumen $nomorDokumen,
        private readonly NomorAntrian $nomorAntrian,
    ) {}

    public function daftarkan(array $data, ?User $petugas = null): Kunjungan
    {
        $data['tanggal'] ??= Carbon::today()->toDateString();

        $tervalidasi = Validator::make($data, [
            'pasien_id' => ['required', 'exists:pasien,id'],
            'poli_id' => ['required', 'exists:poli,id'],
            'dokter_id' => ['required', 'exists:dokter,id'],
            'penjamin_id' => ['required', 'exists:penjamin,id'],
            'tanggal' => ['required', 'date'],
            'no_kartu_penjamin' => [
                Rule::requiredIf(fn () => Penjamin::find($data['penjamin_id'] ?? null)?->ditanggung() === true),
                'nullable', 'string', 'max:30',
            ],
        ], [
            'pasien_id.required' => 'Pasien wajib dipilih.',
            'poli_id.required' => 'Poli wajib dipilih.',
            'dokter_id.required' => 'Dokter wajib dipilih.',
            'penjamin_id.required' => 'Penjamin wajib dipilih.',
            'no_kartu_penjamin.required' => 'Nomor kartu penjamin wajib diisi untuk pasien dengan penjamin.',
        ])->validate();

        $this->pastikanDokterBertugasDiPoli($tervalidasi);
        $this->pastikanTidakAdaKunjunganAktif($tervalidasi);

        $tanggal = Carbon::parse($tervalidasi['tanggal']);

        return DB::transaction(function () use ($tervalidasi, $tanggal, $petugas) {
            $kunjungan = Kunjungan::create([
                ...$tervalidasi,
                'no_kunjungan' => $this->nomorDokumen->berikutnya('kunjungan', $tanggal),
                'jenis_kunjungan' => $this->jenisKunjungan((int) $tervalidasi['pasien_id']),
                'status' => StatusKunjungan::Terdaftar,
                'waktu_daftar' => now(),
                'didaftarkan_oleh' => $petugas?->id,
            ]);

            Antrian::create([
                'kunjungan_id' => $kunjungan->id,
                'poli_id' => $kunjungan->poli_id,
                'tanggal' => $tanggal->toDateString(),
                'nomor' => $this->nomorAntrian->berikutnya($kunjungan->poli_id, $tanggal),
                'status' => StatusAntrian::Menunggu,
            ]);

            return $kunjungan->load('antrian');
        });
    }

    public function batalkan(Kunjungan $kunjungan): void
    {
        DB::transaction(function () use ($kunjungan) {
            $terkunci = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->first();

            if ($terkunci->status !== StatusKunjungan::Terdaftar) {
                throw new RuntimeException(
                    'Kunjungan hanya bisa dibatalkan selama statusnya masih terdaftar.'
                );
            }

            $terkunci->update(['status' => StatusKunjungan::Batal]);
            $terkunci->antrian?->update(['status' => StatusAntrian::Terlewat]);
        });

        $kunjungan->refresh();
    }

    private function pastikanDokterBertugasDiPoli(array $data): void
    {
        $dokter = Dokter::find($data['dokter_id']);

        if ((int) $dokter->poli_id !== (int) $data['poli_id']) {
            throw ValidationException::withMessages([
                'dokter_id' => 'Dokter yang dipilih tidak bertugas di poli tersebut.',
            ]);
        }
    }

    private function pastikanTidakAdaKunjunganAktif(array $data): void
    {
        $ada = Kunjungan::aktif()
            ->where('pasien_id', $data['pasien_id'])
            ->where('poli_id', $data['poli_id'])
            ->whereDate('tanggal', $data['tanggal'])
            ->exists();

        if ($ada) {
            throw ValidationException::withMessages([
                'pasien_id' => 'Pasien ini masih punya kunjungan aktif di poli yang sama hari ini.',
            ]);
        }
    }

    private function jenisKunjungan(int $pasienId): string
    {
        return Kunjungan::where('pasien_id', $pasienId)->exists() ? 'lama' : 'baru';
    }
}
