<?php

namespace App\Livewire\Master;

use App\Models\Ruang;
use Livewire\Component;

class DaftarRuangBed extends Component
{
    public function render()
    {
        return view('livewire.master.daftar-ruang-bed', [
            'daftarRuang' => Ruang::with(['bed' => fn ($q) => $q->with('kelas')])
                ->orderBy('nama')
                ->get(),
        ]);
    }
}
