<?php

namespace App\Livewire\Apotek;

use App\Models\MutasiStok;
use App\Models\Obat;
use Livewire\Component;
use Livewire\WithPagination;

class KartuStok extends Component
{
    use WithPagination;

    public Obat $obat;

    public function mount(Obat $obat): void
    {
        $this->obat = $obat;
    }

    public function render()
    {
        return view('livewire.apotek.kartu-stok', [
            'mutasi' => MutasiStok::with('batch', 'petugas')
                ->where('obat_id', $this->obat->id)
                ->latest('id')
                ->paginate(30),
        ]);
    }
}
