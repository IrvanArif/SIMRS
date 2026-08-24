<?php

namespace App\Livewire\Lab;

use App\Models\OrderLab;
use App\Services\PemeriksaanLaboratorium;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use RuntimeException;

class LayarEntriHasil extends Component
{
    use AuthorizesRequests;

    public OrderLab $order;

    /** @var array<int, string> parameter_lab_id => nilai */
    public array $nilai = [];

    public function mount(OrderLab $order): void
    {
        $this->authorize('kerjakan', $order);

        $this->order = $order;

        foreach ($order->detail as $detail) {
            foreach ($detail->pemeriksaan->parameter as $parameter) {
                $tersimpan = $detail->hasil->firstWhere('parameter_lab_id', $parameter->id);
                $this->nilai[$parameter->id] = (string) ($tersimpan->nilai ?? '');
            }
        }
    }

    public function simpan(PemeriksaanLaboratorium $layanan): void
    {
        $terisi = array_filter($this->nilai, fn ($v) => trim((string) $v) !== '');

        try {
            $layanan->entriHasil($this->order, $terisi, auth()->user());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kolom => $pesan) {
                $this->addError($kolom, $pesan[0]);
            }

            return;
        } catch (RuntimeException $e) {
            $this->addError('entri', $e->getMessage());

            return;
        }

        $this->order->refresh();
        session()->flash('sukses', 'Hasil tersimpan.');
    }

    public function render()
    {
        return view('livewire.lab.layar-entri-hasil', [
            'jenisKelamin' => $this->order->kunjungan->pasien->jenis_kelamin,
        ]);
    }
}
