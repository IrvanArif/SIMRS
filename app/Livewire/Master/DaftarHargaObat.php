<?php

namespace App\Livewire\Master;

use App\Models\HargaObat;
use App\Models\Obat;
use App\Models\Penjamin;
use Livewire\Component;
use Livewire\WithPagination;

class DaftarHargaObat extends Component
{
    use WithPagination;

    public ?int $hargaId = null;
    public ?int $obat_id = null;
    public ?int $penjamin_id = null;
    public int $harga = 0;
    public string $berlaku_mulai = '';

    public function mount(): void
    {
        $this->berlaku_mulai = now()->toDateString();
    }

    public function simpan(): void
    {
        $data = $this->validate([
            'obat_id' => ['required', 'exists:obat,id'],
            'penjamin_id' => ['required', 'exists:penjamin,id'],
            'harga' => ['required', 'integer', 'min:0'],
            'berlaku_mulai' => ['required', 'date'],
        ], [
            'obat_id.required' => 'Obat wajib dipilih.',
            'penjamin_id.required' => 'Penjamin wajib dipilih.',
            'harga.min' => 'Harga tidak boleh negatif.',
        ]);

        $sudahAda = HargaObat::where('obat_id', $data['obat_id'])
            ->where('penjamin_id', $data['penjamin_id'])
            ->whereDate('berlaku_mulai', $data['berlaku_mulai'])
            ->when($this->hargaId, fn ($q) => $q->where('id', '!=', $this->hargaId))
            ->exists();

        if ($sudahAda) {
            $this->addError('harga', 'Harga untuk obat, penjamin, dan tanggal berlaku ini sudah ada.');

            return;
        }

        HargaObat::updateOrCreate(['id' => $this->hargaId], $data);

        $this->reset(['hargaId', 'obat_id', 'penjamin_id', 'harga']);
        session()->flash('sukses', 'Harga obat tersimpan.');
    }

    public function render()
    {
        return view('livewire.master.daftar-harga-obat', [
            'daftarHarga' => HargaObat::with('obat', 'penjamin')
                ->orderByDesc('berlaku_mulai')->paginate(15),
            'daftarObat' => Obat::orderBy('nama')->get(),
            'daftarPenjamin' => Penjamin::orderBy('nama')->get(),
        ]);
    }
}
