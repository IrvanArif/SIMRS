<?php

namespace App\Services;

use App\Models\Pasien;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PendaftaranPasien
{
    public function __construct(private readonly NomorRekamMedis $nomorRekamMedis) {}

    public function daftarkan(array $data): Pasien
    {
        $tervalidasi = Validator::make($data, $this->aturan(), $this->pesan())->validate();

        return DB::transaction(function () use ($tervalidasi) {
            $tervalidasi['no_rm'] = $this->nomorRekamMedis->berikutnya();

            return Pasien::create($tervalidasi);
        });
    }

    public function perbarui(Pasien $pasien, array $data): Pasien
    {
        $aturan = $this->aturan();
        $aturan['nik'] = ['required', 'digits:16', Rule::unique('pasien', 'nik')->ignore($pasien->id)];

        $tervalidasi = Validator::make($data, $aturan, $this->pesan())->validate();

        $pasien->update($tervalidasi);

        return $pasien->refresh();
    }

    /**
     * Dipakai ulang komponen Livewire supaya aturan validasi hanya ada di satu tempat.
     */
    public function aturan(): array
    {
        return [
            'nik' => ['required', 'digits:16', 'unique:pasien,nik'],
            'nama' => ['required', 'string', 'max:100'],
            'tempat_lahir' => ['nullable', 'string', 'max:60'],
            'tanggal_lahir' => ['required', 'date', 'before_or_equal:today'],
            'jenis_kelamin' => ['required', 'in:L,P'],
            'alamat' => ['required', 'string', 'max:255'],
            'rt' => ['nullable', 'string', 'max:3'],
            'rw' => ['nullable', 'string', 'max:3'],
            'kelurahan' => ['nullable', 'string', 'max:60'],
            'kecamatan' => ['nullable', 'string', 'max:60'],
            'kabupaten' => ['nullable', 'string', 'max:60'],
            'no_hp' => ['nullable', 'string', 'max:20'],
            'pekerjaan' => ['nullable', 'string', 'max:60'],
            'agama' => ['nullable', 'string', 'max:20'],
            'status_perkawinan' => ['nullable', 'string', 'max:20'],
            'nama_penanggung_jawab' => ['nullable', 'string', 'max:100'],
            'hubungan_penanggung_jawab' => ['nullable', 'string', 'max:30'],
        ];
    }

    private function pesan(): array
    {
        return [
            'nik.required' => 'NIK wajib diisi.',
            'nik.digits' => 'NIK harus terdiri dari 16 angka.',
            'nik.unique' => 'NIK ini sudah terdaftar atas nama pasien lain.',
            'nama.required' => 'Nama pasien wajib diisi.',
            'tanggal_lahir.required' => 'Tanggal lahir wajib diisi.',
            'tanggal_lahir.before_or_equal' => 'Tanggal lahir tidak boleh melewati hari ini.',
            'jenis_kelamin.in' => 'Jenis kelamin hanya boleh L atau P.',
            'alamat.required' => 'Alamat wajib diisi.',
        ];
    }
}
