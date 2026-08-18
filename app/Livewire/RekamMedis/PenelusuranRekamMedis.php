<?php

namespace App\Livewire\RekamMedis;

use App\Models\Pasien;
use Livewire\Component;
use Livewire\WithPagination;

class PenelusuranRekamMedis extends Component
{
    use WithPagination;

    public string $kata = '';

    public function updatingKata(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $pasien = trim($this->kata) === ''
            ? Pasien::query()->latest('id')->paginate(10)
            : Pasien::cari(trim($this->kata))->paginate(10);

        $pasien->load(['kunjungan' => fn ($q) => $q->latest('tanggal')->limit(5), 'kunjungan.diagnosa.icd10']);

        return view('livewire.rekam-medis.penelusuran-rekam-medis', ['daftarPasien' => $pasien]);
    }
}
