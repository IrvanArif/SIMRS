<?php

namespace App\Livewire\Master;

use App\Models\Dokter;
use App\Models\Poli;
use Livewire\Component;
use Livewire\WithPagination;

class DaftarDokter extends Component
{
    use WithPagination;

    public ?int $dokterId = null;
    public string $nip = '';
    public string $nama = '';
    public string $spesialisasi = '';
    public string $no_sip = '';
    public ?int $poli_id = null;
    public bool $aktif = true;

    public function simpan(): void
    {
        $data = $this->validate([
            'nip' => ['required', 'string', 'max:30', 'unique:dokter,nip,'.($this->dokterId ?? 'NULL').',id'],
            'nama' => ['required', 'string', 'max:100'],
            'spesialisasi' => ['nullable', 'string', 'max:100'],
            'no_sip' => ['nullable', 'string', 'max:50'],
            'poli_id' => ['required', 'exists:poli,id'],
            'aktif' => ['boolean'],
        ], [
            'nip.required' => 'NIP wajib diisi.',
            'nip.unique' => 'NIP ini sudah dipakai dokter lain.',
            'nama.required' => 'Nama dokter wajib diisi.',
            'poli_id.required' => 'Poli tempat bertugas wajib dipilih.',
        ]);

        Dokter::updateOrCreate(['id' => $this->dokterId], $data);

        $this->reset(['dokterId', 'nip', 'nama', 'spesialisasi', 'no_sip', 'poli_id']);
        session()->flash('sukses', 'Data dokter tersimpan.');
    }

    public function sunting(int $id): void
    {
        $dokter = Dokter::findOrFail($id);
        $this->dokterId = $dokter->id;
        $this->nip = $dokter->nip;
        $this->nama = $dokter->nama;
        $this->spesialisasi = (string) $dokter->spesialisasi;
        $this->no_sip = (string) $dokter->no_sip;
        $this->poli_id = $dokter->poli_id;
        $this->aktif = $dokter->aktif;
    }

    public function render()
    {
        return view('livewire.master.daftar-dokter', [
            'daftarDokter' => Dokter::with('poli')->orderBy('nama')->paginate(10),
            'daftarPoli' => Poli::orderBy('nama')->get(),
        ]);
    }
}
