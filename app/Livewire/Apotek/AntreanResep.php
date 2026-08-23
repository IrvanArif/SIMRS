<?php

namespace App\Livewire\Apotek;

use App\Enums\StatusResep;
use App\Models\Resep;
use Livewire\Component;
use Livewire\WithPagination;

class AntreanResep extends Component
{
    use WithPagination;

    public string $status = 'dibuat';

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.apotek.antrean-resep', [
            'daftarResep' => Resep::with('kunjungan.pasien', 'kunjungan.poli', 'kunjungan.penjamin', 'detail.obat')
                ->where('status', $this->status)
                ->latest('id')
                ->paginate(15),
            'pilihanStatus' => StatusResep::cases(),
        ]);
    }
}
