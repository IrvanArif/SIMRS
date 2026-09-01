<?php

namespace App\Livewire\Laporan;

use App\Services\LaporanKunjungan;
use App\Services\RentangTanggal;
use InvalidArgumentException;
use Livewire\Component;

class RekapKunjungan extends Component
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
            $baris = app(LaporanKunjungan::class)->perPoli($rentang);
        } catch (InvalidArgumentException $e) {
            $galat = $e->getMessage();
        }

        return view('livewire.laporan.rekap-kunjungan', compact('baris', 'galat'));
    }
}
