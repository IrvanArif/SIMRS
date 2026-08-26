<?php

namespace App\Livewire\Master;

use App\Models\PemeriksaanRadiologi;
use Livewire\Component;
use Livewire\WithPagination;

class DaftarPemeriksaanRadiologi extends Component
{
    use WithPagination;

    public string $cari = '';

    public function updatingCari(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.master.daftar-pemeriksaan-radiologi', [
            'daftar' => PemeriksaanRadiologi::when($this->cari !== '', fn ($q) => $q
                ->where('nama', 'like', "%{$this->cari}%")
                ->orWhere('kode', 'like', "%{$this->cari}%"))
                ->orderBy('kode')
                ->paginate(20),
        ]);
    }
}
