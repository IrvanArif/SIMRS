<?php

namespace App\Livewire\Pendaftaran;

use App\Models\Antrian;
use Livewire\Component;

class PapanAntrian extends Component
{
    public function render()
    {
        return view('livewire.pendaftaran.papan-antrian', [
            'daftarAntrian' => Antrian::with('kunjungan.pasien', 'kunjungan.dokter', 'poli')
                ->whereDate('tanggal', today())
                ->orderBy('poli_id')
                ->orderBy('nomor')
                ->get(),
        ]);
    }
}
