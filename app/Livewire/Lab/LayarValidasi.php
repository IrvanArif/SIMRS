<?php

namespace App\Livewire\Lab;

use App\Models\OrderLab;
use App\Services\PemeriksaanLaboratorium;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use RuntimeException;

class LayarValidasi extends Component
{
    use AuthorizesRequests;

    public OrderLab $order;

    /** @var array<int, string> parameter_lab_id => nilai koreksi */
    public array $nilai = [];

    public string $alasanKoreksi = '';

    public function mount(OrderLab $order): void
    {
        $this->authorize('validasi', $order);

        $this->order = $order;

        foreach ($order->detail as $detail) {
            foreach ($detail->hasil as $hasil) {
                $this->nilai[$hasil->parameter_lab_id] = (string) $hasil->nilai;
            }
        }
    }

    public function validasi(PemeriksaanLaboratorium $layanan): void
    {
        $this->jalankan(fn () => $layanan->validasi($this->order, auth()->user()));
    }

    public function koreksi(PemeriksaanLaboratorium $layanan): void
    {
        $terisi = array_filter($this->nilai, fn ($v) => trim((string) $v) !== '');

        $this->jalankan(fn () => $layanan->koreksi(
            $this->order, $terisi, auth()->user(), $this->alasanKoreksi
        ));
    }

    private function jalankan(callable $aksi): void
    {
        try {
            $aksi();
            $this->order->refresh();
            $this->reset('alasanKoreksi');
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kolom => $pesan) {
                $this->addError($kolom, $pesan[0]);
            }
        } catch (RuntimeException $e) {
            $this->addError('validasi', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.lab.layar-validasi');
    }
}
