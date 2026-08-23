<?php

namespace App\Livewire\Apotek;

use App\Models\Resep;
use App\Services\PenyerahanObat;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use RuntimeException;

class LayarPenyerahan extends Component
{
    use AuthorizesRequests;

    public Resep $resep;

    public function mount(Resep $resep): void
    {
        $this->authorize('serahkan', $resep);

        $this->resep = $resep;
    }

    public function serahkan(PenyerahanObat $layanan): void
    {
        try {
            $layanan->serahkan($this->resep, auth()->user());
        } catch (RuntimeException $e) {
            $this->addError('penyerahan', $e->getMessage());

            return;
        }

        $this->resep->refresh();
        session()->flash('sukses', 'Obat diserahkan kepada pasien.');
    }

    public function render()
    {
        return view('livewire.apotek.layar-penyerahan');
    }
}
