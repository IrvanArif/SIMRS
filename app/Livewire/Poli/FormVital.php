<?php

namespace App\Livewire\Poli;

use App\Models\Kunjungan;
use App\Services\PemeriksaanKlinis;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use RuntimeException;

class FormVital extends Component
{
    public Kunjungan $kunjungan;

    public ?int $sistolik = null;
    public ?int $diastolik = null;
    public ?int $nadi = null;
    public ?float $suhu = null;
    public ?int $respirasi = null;
    public ?float $berat_badan = null;
    public ?int $tinggi_badan = null;
    public string $keluhan_awal = '';
    public string $alergi = '';

    public function mount(Kunjungan $kunjungan): void
    {
        $this->kunjungan = $kunjungan;

        if ($pemeriksaan = $kunjungan->pemeriksaan) {
            $this->fill($pemeriksaan->only([
                'sistolik', 'diastolik', 'nadi', 'suhu', 'respirasi',
                'berat_badan', 'tinggi_badan',
            ]));

            // Dua kolom teks ini bisa NULL, sedangkan propertinya bertipe string.
            $this->keluhan_awal = (string) $pemeriksaan->keluhan_awal;
            $this->alergi = (string) $pemeriksaan->alergi;
        }
    }

    public function simpan(PemeriksaanKlinis $layanan): void
    {
        $data = $this->only([
            'sistolik', 'diastolik', 'nadi', 'suhu', 'respirasi',
            'berat_badan', 'tinggi_badan', 'keluhan_awal', 'alergi',
        ]);

        try {
            $layanan->catatVital($this->kunjungan, $data, auth()->user());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kolom => $pesan) {
                $this->addError($kolom, $pesan[0]);
            }

            return;
        } catch (RuntimeException $e) {
            $this->addError('kunjungan', $e->getMessage());

            return;
        }

        $this->kunjungan->refresh();
        session()->flash('sukses', 'Tanda vital tersimpan.');
    }

    public function render()
    {
        return view('livewire.poli.form-vital');
    }
}
