<?php

namespace App\Livewire\Laporan;

use App\Services\LaporanMorbiditas;
use App\Services\RentangTanggal;
use InvalidArgumentException;
use Livewire\Component;

class Morbiditas extends Component
{
    public string $awal = '';

    public string $akhir = '';

    /** '' semua, 'jalan' rawat jalan, 'inap' rawat inap */
    public string $jenis = '';

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
            $baris = app(LaporanMorbiditas::class)->sepuluhBesar($rentang, match ($this->jenis) {
                'jalan' => false,
                'inap' => true,
                default => null,
            });
        } catch (InvalidArgumentException $e) {
            $galat = $e->getMessage();
        }

        return view('livewire.laporan.morbiditas', compact('baris', 'galat'));
    }
}
