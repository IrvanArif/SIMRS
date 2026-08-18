<?php

namespace App\Livewire\Pendaftaran;

use App\Models\Pasien;
use Livewire\Component;
use Livewire\WithPagination;

class CariPasien extends Component
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

        return view('livewire.pendaftaran.cari-pasien', ['daftarPasien' => $pasien]);
    }
}
