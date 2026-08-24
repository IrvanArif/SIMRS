<?php

namespace App\Livewire\Lab;

use App\Enums\StatusOrderLab;
use App\Models\OrderLab;
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
        return view('livewire.lab.antrean-order', [
            'daftarOrder' => OrderLab::with('kunjungan.pasien', 'kunjungan.poli', 'detail.pemeriksaan')
                ->where('status', $this->status)
                ->latest('id')
                ->paginate(15),
            'pilihanStatus' => StatusOrderLab::cases(),
        ]);
    }
}
