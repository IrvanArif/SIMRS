<?php

namespace App\Livewire\RekamMedis;

use App\Models\Kunjungan;
use Livewire\Component;

class RekapKunjunganHarian extends Component
{
    public string $tanggal = '';

    public int $jumlahKunjungan = 0;

    public function mount(): void
    {
        $this->tanggal = now()->toDateString();
        $this->hitung();
    }

    public function updatedTanggal(): void
    {
        $this->hitung();
    }

    private function hitung(): void
    {
        $this->jumlahKunjungan = Kunjungan::whereDate('tanggal', $this->tanggal)->count();
    }

    public function render()
    {
        return view('livewire.rekam-medis.rekap-kunjungan-harian', [
            'perPoli' => Kunjungan::with('poli')
                ->whereDate('tanggal', $this->tanggal)
                ->get()
                ->groupBy(fn (Kunjungan $k) => $k->poli->nama)
                ->map->count(),
        ]);
    }
}
