<?php

namespace App\Livewire\Pendaftaran;

use App\Models\Pasien;
use App\Services\PendaftaranPasien;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class FormPasien extends Component
{
    public ?Pasien $pasien = null;

    public string $nik = '';
    public string $nama = '';
    public string $tempat_lahir = '';
    public string $tanggal_lahir = '';
    public string $jenis_kelamin = '';
    public string $alamat = '';
    public string $rt = '';
    public string $rw = '';
    public string $kelurahan = '';
    public string $kecamatan = '';
    public string $kabupaten = '';
    public string $no_hp = '';

    public function mount(?Pasien $pasien = null): void
    {
        if (! $pasien?->exists) {
            return;
        }

        $this->pasien = $pasien;

        // Kolom opsional bernilai NULL di database, sedangkan properti komponen
        // bertipe string — tanpa pemaksaan ini, membuka pasien lama akan gagal.
        foreach ([
            'nik', 'nama', 'tempat_lahir', 'alamat',
            'rt', 'rw', 'kelurahan', 'kecamatan', 'kabupaten', 'no_hp',
        ] as $kolom) {
            $this->{$kolom} = (string) $pasien->{$kolom};
        }

        $this->jenis_kelamin = $pasien->jenis_kelamin->value;
        $this->tanggal_lahir = $pasien->tanggal_lahir->toDateString();
    }

    public function simpan(PendaftaranPasien $layanan)
    {
        $data = $this->only([
            'nik', 'nama', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin',
            'alamat', 'rt', 'rw', 'kelurahan', 'kecamatan', 'kabupaten', 'no_hp',
        ]);

        try {
            $pasien = $this->pasien
                ? $layanan->perbarui($this->pasien, $data)
                : $layanan->daftarkan($data);
        } catch (ValidationException $e) {
            // Aturan validasi hidup di Service; komponen hanya memindahkan pesannya ke layar.
            foreach ($e->errors() as $kolom => $pesan) {
                $this->addError($kolom, $pesan[0]);
            }

            return null;
        }

        session()->flash('sukses', "Pasien tersimpan dengan nomor RM {$pasien->no_rm}.");

        return $this->redirectRoute('pendaftaran.kunjungan', ['pasien' => $pasien->id]);
    }

    public function render()
    {
        return view('livewire.pendaftaran.form-pasien');
    }
}
