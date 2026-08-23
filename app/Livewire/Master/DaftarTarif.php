<?php

namespace App\Livewire\Master;

use App\Enums\JenisLayanan;
use App\Models\Obat;
use App\Models\Penjamin;
use App\Models\Tarif;
use App\Models\Tindakan;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class DaftarTarif extends Component
{
    use WithPagination;

    public string $jenis_layanan = 'tindakan';
    public ?int $layanan_id = null;
    public ?int $penjamin_id = null;
    public int $harga = 0;
    public string $berlaku_mulai = '';

    public function mount(): void
    {
        $this->berlaku_mulai = now()->toDateString();
    }

    public function updatingJenisLayanan(): void
    {
        // Layanan yang terpilih tidak lagi relevan begitu jenisnya berganti.
        $this->layanan_id = null;
    }

    public function simpan(): void
    {
        $data = $this->validate([
            'jenis_layanan' => ['required', Rule::in(array_column(JenisLayanan::cases(), 'value'))],
            'layanan_id' => ['required', 'integer'],
            'penjamin_id' => ['required', 'exists:penjamin,id'],
            'harga' => ['required', 'integer', 'min:0'],
            'berlaku_mulai' => ['required', 'date'],
        ], [
            'layanan_id.required' => 'Layanan wajib dipilih.',
            'penjamin_id.required' => 'Penjamin wajib dipilih.',
            'harga.min' => 'Tarif tidak boleh negatif.',
        ]);

        $sudahAda = Tarif::where('jenis_layanan', $data['jenis_layanan'])
            ->where('layanan_id', $data['layanan_id'])
            ->where('penjamin_id', $data['penjamin_id'])
            ->whereDate('berlaku_mulai', $data['berlaku_mulai'])
            ->exists();

        if ($sudahAda) {
            $this->addError('harga', 'Tarif untuk layanan, penjamin, dan tanggal berlaku ini sudah ada.');

            return;
        }

        Tarif::create($data);

        $this->reset(['layanan_id', 'penjamin_id', 'harga']);
        session()->flash('sukses', 'Tarif tersimpan.');
    }

    public function render()
    {
        $pilihanLayanan = match (JenisLayanan::from($this->jenis_layanan)) {
            JenisLayanan::Tindakan => Tindakan::orderBy('nama')->get(['id', 'nama']),
            JenisLayanan::Obat => Obat::orderBy('nama')->get(['id', 'nama']),
            JenisLayanan::Lab => collect(),
        };

        return view('livewire.master.daftar-tarif', [
            'daftarTarif' => Tarif::with('penjamin')->orderByDesc('berlaku_mulai')->paginate(15),
            'pilihanLayanan' => $pilihanLayanan,
            'daftarPenjamin' => Penjamin::orderBy('nama')->get(),
            'pilihanJenis' => JenisLayanan::cases(),
        ]);
    }
}
