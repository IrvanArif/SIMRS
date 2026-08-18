<?php

namespace App\Livewire\Kasir;

use App\Enums\StatusTagihan;
use App\Models\Tagihan;
use Livewire\Component;
use Livewire\WithPagination;

class DaftarTagihan extends Component
{
    use WithPagination;

    public function render()
    {
        return view('livewire.kasir.daftar-tagihan', [
            'daftarTagihan' => Tagihan::with('kunjungan.pasien', 'penjamin')
                ->where('status', StatusTagihan::BelumBayar)
                ->latest('id')
                ->paginate(15),
        ]);
    }
}
