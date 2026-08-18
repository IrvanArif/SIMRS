<?php

namespace App\Livewire\Pendaftaran;

use App\Models\Dokter;
use App\Models\Pasien;
use App\Models\Penjamin;
use App\Models\Poli;
use App\Services\PendaftaranKunjungan;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class FormKunjungan extends Component
{
    public Pasien $pasien;

    public ?int $poli_id = null;
    public ?int $dokter_id = null;
    public ?int $penjamin_id = null;
    public string $no_kartu_penjamin = '';

    public function mount(Pasien $pasien): void
    {
        $this->pasien = $pasien;
    }

    public function simpan(PendaftaranKunjungan $layanan)
    {
        try {
            $kunjungan = $layanan->daftarkan([
                'pasien_id' => $this->pasien->id,
                'poli_id' => $this->poli_id,
                'dokter_id' => $this->dokter_id,
                'penjamin_id' => $this->penjamin_id,
                'no_kartu_penjamin' => $this->no_kartu_penjamin ?: null,
                'tanggal' => now()->toDateString(),
            ], auth()->user());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kolom => $pesan) {
                $this->addError($kolom, $pesan[0]);
            }

            return null;
        }

        return $this->redirectRoute('cetak.karcis', ['antrian' => $kunjungan->antrian->id]);
    }

    public function render()
    {
        return view('livewire.pendaftaran.form-kunjungan', [
            'daftarPoli' => Poli::where('aktif', true)->orderBy('nama')->get(),
            'daftarDokter' => $this->poli_id
                ? Dokter::where('poli_id', $this->poli_id)->where('aktif', true)->get()
                : collect(),
            'daftarPenjamin' => Penjamin::where('aktif', true)->get(),
        ]);
    }
}
