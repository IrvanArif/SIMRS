<?php

namespace App\Livewire\Master;

use App\Models\Poli;
use Livewire\Component;
use Livewire\WithPagination;

class DaftarPoli extends Component
{
    use WithPagination;

    public ?int $poliId = null;
    public string $kode = '';
    public string $nama = '';
    public string $lokasi = '';
    public bool $aktif = true;

    public function simpan(): void
    {
        $data = $this->validate([
            'kode' => ['required', 'string', 'max:10', 'unique:poli,kode,'.($this->poliId ?? 'NULL').',id'],
            'nama' => ['required', 'string', 'max:100'],
            'lokasi' => ['nullable', 'string', 'max:100'],
            'aktif' => ['boolean'],
        ], [
            'kode.required' => 'Kode poli wajib diisi.',
            'kode.unique' => 'Kode poli ini sudah dipakai.',
            'nama.required' => 'Nama poli wajib diisi.',
        ]);

        Poli::updateOrCreate(['id' => $this->poliId], $data);

        $this->reset(['poliId', 'kode', 'nama', 'lokasi']);
        session()->flash('sukses', 'Data poli tersimpan.');
    }

    public function sunting(int $id): void
    {
        $poli = Poli::findOrFail($id);
        $this->poliId = $poli->id;
        $this->fill($poli->only(['kode', 'nama', 'aktif']));
        $this->lokasi = (string) $poli->lokasi;
    }

    public function render()
    {
        return view('livewire.master.daftar-poli', [
            'daftarPoli' => Poli::orderBy('kode')->paginate(10),
        ]);
    }
}
