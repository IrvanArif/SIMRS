<?php

namespace App\Livewire\Radiologi;

use App\Models\OrderRadiologi;
use App\Services\PelaksanaanRadiologi;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use RuntimeException;

class LayarPelaksanaan extends Component
{
    use AuthorizesRequests;

    public OrderRadiologi $order;

    public string $no_film = '';

    public function mount(OrderRadiologi $order): void
    {
        $this->authorize('kerjakan', $order);

        $this->order = $order;
        $this->no_film = (string) $order->no_film;
    }

    public function kerjakan(PelaksanaanRadiologi $layanan)
    {
        try {
            $layanan->kerjakan($this->order, $this->no_film, auth()->user());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kolom => $pesan) {
                $this->addError($kolom, $pesan[0]);
            }

            return null;
        } catch (RuntimeException $e) {
            $this->addError('pelaksanaan', $e->getMessage());

            return null;
        }

        return $this->redirectRoute('radiologi.antrean');
    }

    public function render()
    {
        return view('livewire.radiologi.layar-pelaksanaan');
    }
}
