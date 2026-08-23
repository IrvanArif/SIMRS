<?php

namespace App\Livewire\Apotek;

use App\Models\BatchObat;
use App\Models\Obat;
use Livewire\Component;

class PeringatanStok extends Component
{
    public function render()
    {
        return view('livewire.apotek.peringatan-stok', [
            'obatMenipis' => Obat::menipis()->orderBy('nama')->get(),
            'mendekatiKedaluwarsa' => BatchObat::with('obat')
                ->where('jumlah_tersisa', '>', 0)
                ->whereDate('tanggal_kedaluwarsa', '<=', now()->addMonths(3))
                ->orderBy('tanggal_kedaluwarsa')
                ->get(),
        ]);
    }
}
