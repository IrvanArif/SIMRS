<?php

namespace App\Livewire\Laporan;

use App\Services\IndikatorRawatInap;
use App\Services\RentangTanggal;
use InvalidArgumentException;
use Livewire\Component;

class Indikator extends Component
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
        $hasil = null;

        try {
            $rentang = RentangTanggal::dari($this->awal, $this->akhir);
            $hasil = app(IndikatorRawatInap::class)->hitung($rentang);
        } catch (InvalidArgumentException $e) {
            // Rentang terbalik dilaporkan apa adanya. Laporan yang mengembalikan
            // nol karena tanggalnya tertukar terlihat seperti periode yang sepi.
            $galat = $e->getMessage();
        }

        return view('livewire.laporan.indikator', compact('hasil', 'galat'));
    }
}
