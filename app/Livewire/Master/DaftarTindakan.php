<?php

namespace App\Livewire\Master;

use App\Models\Tindakan;
use Livewire\Component;
use Livewire\WithPagination;

class DaftarTindakan extends Component
{
    use WithPagination;

    public ?int $tindakanId = null;
    public string $kode = '';
    public string $nama = '';
    public string $kategori = 'tindakan_medis';
    public bool $aktif = true;

    public function simpan(): void
    {
        $data = $this->validate([
            'kode' => ['required', 'string', 'max:20', 'unique:tindakan,kode,'.($this->tindakanId ?? 'NULL').',id'],
            'nama' => ['required', 'string', 'max:150'],
            'kategori' => ['required', 'in:administrasi,konsultasi,tindakan_medis'],
            'aktif' => ['boolean'],
        ], [
            'kode.required' => 'Kode tindakan wajib diisi.',
            'kode.unique' => 'Kode tindakan ini sudah dipakai.',
            'nama.required' => 'Nama tindakan wajib diisi.',
        ]);

        Tindakan::updateOrCreate(['id' => $this->tindakanId], $data);

        $this->reset(['tindakanId', 'kode', 'nama']);
        session()->flash('sukses', 'Data tindakan tersimpan.');
    }

    public function sunting(int $id): void
    {
        $tindakan = Tindakan::findOrFail($id);
        $this->tindakanId = $tindakan->id;
        $this->fill($tindakan->only(['kode', 'nama', 'kategori', 'aktif']));
    }

    public function render()
    {
        return view('livewire.master.daftar-tindakan', [
            'daftarTindakan' => Tindakan::orderBy('nama')->paginate(10),
        ]);
    }
}
