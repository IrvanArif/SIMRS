<?php

namespace App\Livewire\Lab;

use App\Models\OrderLab;
use App\Services\PemeriksaanLaboratorium;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use RuntimeException;

class LayarSampel extends Component
{
    use AuthorizesRequests;

    public OrderLab $order;

    public function mount(OrderLab $order): void
    {
        $this->authorize('kerjakan', $order);

        $this->order = $order;
    }

    public function ambil(PemeriksaanLaboratorium $layanan)
    {
        try {
            $layanan->ambilSampel($this->order, auth()->user());
        } catch (RuntimeException $e) {
            $this->addError('sampel', $e->getMessage());

            return null;
        }

        return $this->redirectRoute('lab.hasil', ['order' => $this->order->id]);
    }

    public function render()
    {
        return view('livewire.lab.layar-sampel');
    }
}
