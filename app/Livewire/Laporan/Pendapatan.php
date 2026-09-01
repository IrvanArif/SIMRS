<?php

namespace App\Livewire\Laporan;

use App\Services\LaporanPendapatan;
use App\Services\RentangTanggal;
use InvalidArgumentException;
use Livewire\Component;

class Pendapatan extends Component
{
    public string $awal = '';

    public string $akhir = '';

    public function mount(): void
    {
        $this->awal = now()->startOfMonth()->toDateString();
        $this->akhir = now()->endOfMonth()->toDateString();
    }

    public function render()
    {
        $galat = null;
        $baris = collect();

        try {
            $rentang = RentangTanggal::dari($this->awal, $this->akhir);
            $baris = app(LaporanPendapatan::class)->perPenjamin($rentang);
        } catch (InvalidArgumentException $e) {
            $galat = $e->getMessage();
        }

        return view('livewire.laporan.pendapatan', compact('baris', 'galat'));
    }
}
