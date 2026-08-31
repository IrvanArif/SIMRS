<?php

namespace App\Livewire\RawatInap;

use App\Models\RawatInap;
use App\Models\Ruang;
use Livewire\Component;

class PapanBed extends Component
{
    public function render()
    {
        return view('livewire.rawat-inap.papan-bed', [
            'daftarRuang' => Ruang::where('aktif', true)
                ->with(['bed' => fn ($q) => $q->with('kelas', 'penghuni.kunjungan.pasien')])
                ->orderBy('nama')
                ->get(),
            // Pasien yang perintah rawatnya sudah terbit tapi belum menempati
            // bed. Tanpa daftar ini mereka tidak muncul di mana pun.
            'menungguPenempatan' => RawatInap::aktif()
                ->whereDoesntHave('okupansi', fn ($q) => $q->whereNull('selesai'))
                ->with('kunjungan.pasien', 'kelasDiminta')
                ->orderBy('id')
                ->get(),
        ]);
    }
}
