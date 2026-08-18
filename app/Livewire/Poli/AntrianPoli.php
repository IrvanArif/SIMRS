<?php

namespace App\Livewire\Poli;

use App\Enums\StatusAntrian;
use App\Models\Antrian;
use App\Models\Poli;
use Livewire\Component;

class AntrianPoli extends Component
{
    public ?int $poli_id = null;

    public function mount(): void
    {
        // Dokter langsung terkunci ke polinya sendiri; perawat memilih poli.
        $this->poli_id = auth()->user()->dokter?->poli_id;
    }

    public function panggil(int $antrianId): void
    {
        $antrian = Antrian::findOrFail($antrianId);

        $antrian->update([
            'status' => StatusAntrian::Dipanggil,
            'waktu_panggil' => now(),
        ]);
    }

    public function render()
    {
        $daftar = Antrian::with('kunjungan.pasien', 'kunjungan.dokter', 'poli')
            ->whereDate('tanggal', today())
            ->when($this->poli_id, fn ($q) => $q->where('poli_id', $this->poli_id))
            ->orderBy('nomor')
            ->get();

        return view('livewire.poli.antrian-poli', [
            'daftarAntrian' => $daftar,
            'daftarPoli' => Poli::where('aktif', true)->orderBy('nama')->get(),
        ]);
    }
}
