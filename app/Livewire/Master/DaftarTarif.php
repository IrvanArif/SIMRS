<?php

namespace App\Livewire\Master;

use App\Models\Penjamin;
use App\Models\TarifTindakan;
use App\Models\Tindakan;
use Livewire\Component;
use Livewire\WithPagination;

class DaftarTarif extends Component
{
    use WithPagination;

    public ?int $tarifId = null;
    public ?int $tindakan_id = null;
    public ?int $penjamin_id = null;
    public int $tarif = 0;
    public string $berlaku_mulai = '';

    public function mount(): void
    {
        $this->berlaku_mulai = now()->toDateString();
    }

    public function simpan(): void
    {
        $data = $this->validate([
            'tindakan_id' => ['required', 'exists:tindakan,id'],
            'penjamin_id' => ['required', 'exists:penjamin,id'],
            'tarif' => ['required', 'integer', 'min:0'],
            'berlaku_mulai' => ['required', 'date'],
        ], [
            'tindakan_id.required' => 'Tindakan wajib dipilih.',
            'penjamin_id.required' => 'Penjamin wajib dipilih.',
            'tarif.min' => 'Tarif tidak boleh negatif.',
        ]);

        $sudahAda = TarifTindakan::where('tindakan_id', $data['tindakan_id'])
            ->where('penjamin_id', $data['penjamin_id'])
            ->whereDate('berlaku_mulai', $data['berlaku_mulai'])
            ->when($this->tarifId, fn ($q) => $q->where('id', '!=', $this->tarifId))
            ->exists();

        if ($sudahAda) {
            $this->addError('tarif', 'Tarif untuk tindakan, penjamin, dan tanggal berlaku ini sudah ada.');

            return;
        }

        TarifTindakan::updateOrCreate(['id' => $this->tarifId], $data);

        $this->reset(['tarifId', 'tindakan_id', 'penjamin_id', 'tarif']);
        session()->flash('sukses', 'Tarif tersimpan.');
    }

    public function render()
    {
        return view('livewire.master.daftar-tarif', [
            'daftarTarif' => TarifTindakan::with('tindakan', 'penjamin')
                ->orderByDesc('berlaku_mulai')->paginate(15),
            'daftarTindakan' => Tindakan::orderBy('nama')->get(),
            'daftarPenjamin' => Penjamin::orderBy('nama')->get(),
        ]);
    }
}
