<?php

namespace App\Livewire\RekamMedis;

use App\Models\Pasien;
use App\Services\PendaftaranPasien;
use App\Support\KonteksAudit;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class KoreksiPasien extends Component
{
    public Pasien $pasien;

    public string $nik = '';
    public string $nama = '';
    public string $tanggal_lahir = '';
    public string $jenis_kelamin = '';
    public string $alamat = '';
    public string $no_hp = '';
    public string $alasan = '';

    public function mount(Pasien $pasien): void
    {
        $this->pasien = $pasien;
        $this->nik = $pasien->nik;
        $this->nama = $pasien->nama;
        $this->jenis_kelamin = $pasien->jenis_kelamin->value;
        $this->alamat = $pasien->alamat;
        $this->no_hp = (string) $pasien->no_hp;
        $this->tanggal_lahir = $pasien->tanggal_lahir->toDateString();
    }

    public function simpan(PendaftaranPasien $layanan): void
    {
        if (trim($this->alasan) === '') {
            $this->addError('alasan', 'Alasan koreksi wajib diisi.');

            return;
        }

        $data = $this->only(['nik', 'nama', 'tanggal_lahir', 'jenis_kelamin', 'alamat', 'no_hp']);

        try {
            KonteksAudit::dengan(trim($this->alasan), fn () => $layanan->perbarui($this->pasien, $data));
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kolom => $pesan) {
                $this->addError($kolom, $pesan[0]);
            }

            return;
        }

        $this->pasien->refresh();
        $this->reset('alasan');
        session()->flash('sukses', 'Koreksi data pasien tersimpan dan tercatat di audit log.');
    }

    public function render()
    {
        return view('livewire.rekam-medis.koreksi-pasien');
    }
}
