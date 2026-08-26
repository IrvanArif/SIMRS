<?php

namespace App\Livewire\Radiologi;

use App\Enums\StatusOrderRadiologi;
use App\Models\OrderRadiologi;
use Livewire\Component;
use Livewire\WithPagination;

class AntreanOrder extends Component
{
    use WithPagination;

    public string $status = 'dipesan';

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.radiologi.antrean-order', [
            'daftarOrder' => OrderRadiologi::with('kunjungan.pasien', 'kunjungan.poli', 'detail.pemeriksaan')
                ->where('status', $this->status)
                ->latest('id')
                ->paginate(15),
            'pilihanStatus' => StatusOrderRadiologi::cases(),
        ]);
    }
}
