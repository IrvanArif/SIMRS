<?php

namespace App\Livewire\Radiologi;

use App\Enums\StatusOrderRadiologi;
use App\Models\OrderRadiologi;
use App\Services\PenulisanEkspertise;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use RuntimeException;

class LayarEkspertise extends Component
{
    use AuthorizesRequests;

    public OrderRadiologi $order;

    /** @var array<int, array<string, string>> order_radiologi_detail_id => temuan/kesan/saran */
    public array $bacaan = [];

    public string $alasanKoreksi = '';

    public function mount(OrderRadiologi $order): void
    {
        $this->authorize('ekspertise', $order);

        $this->order = $order;

        foreach ($order->detail as $detail) {
            $tersimpan = $detail->ekspertise;

            // Kolom nullable dituang lewat (string) supaya properti bertipe string
            // tidak pernah menerima null — sumber galat 500 yang sudah pernah kena.
            $this->bacaan[$detail->id] = [
                'temuan' => (string) ($tersimpan->temuan ?? ''),
                'kesan' => (string) ($tersimpan->kesan ?? ''),
                'saran' => (string) ($tersimpan->saran ?? ''),
            ];
        }
    }

    /**
     * Satu tombol untuk dua jalur: bacaan pertama ditulis biasa, perubahan atas
     * bacaan yang sudah ada wajib beralasan (aturan 56).
     */
    public function simpan(PenulisanEkspertise $layanan): void
    {
        $sudahDitulis = $this->order->status === StatusOrderRadiologi::Selesai;

        try {
            $sudahDitulis
                ? $layanan->koreksi($this->order, $this->bacaan, auth()->user(), $this->alasanKoreksi)
                : $layanan->tulis($this->order, $this->bacaan, auth()->user());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kolom => $pesan) {
                $this->addError($kolom, $pesan[0]);
            }

            return;
        } catch (RuntimeException $e) {
            $this->addError('ekspertise', $e->getMessage());

            return;
        }

        $this->order->refresh();
        $this->reset('alasanKoreksi');
        session()->flash('sukses', 'Ekspertise tersimpan.');
    }

    public function render()
    {
        return view('livewire.radiologi.layar-ekspertise');
    }
}
