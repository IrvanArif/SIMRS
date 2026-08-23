<?php

namespace App\Livewire\Apotek;

use App\Models\BatchObat;
use App\Models\Obat;
use App\Services\PenerimaanObat;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class PenerimaanBatch extends Component
{
    public ?int $obat_id = null;
    public string $no_batch = '';
    public string $tanggal_kedaluwarsa = '';
    public int $jumlah = 1;
    public int $harga_beli = 0;

    public function simpan(PenerimaanObat $layanan): void
    {
        try {
            $layanan->terima($this->only([
                'obat_id', 'no_batch', 'tanggal_kedaluwarsa', 'jumlah', 'harga_beli',
            ]), auth()->user());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kolom => $pesan) {
                $this->addError($kolom, $pesan[0]);
            }

            return;
        }

        $this->reset(['obat_id', 'no_batch', 'tanggal_kedaluwarsa', 'jumlah', 'harga_beli']);
        session()->flash('sukses', 'Batch obat diterima dan stok bertambah.');
    }

    public function render()
    {
        return view('livewire.apotek.penerimaan-batch', [
            'daftarObat' => Obat::where('aktif', true)->orderBy('nama')->get(),
            'batchTerbaru' => BatchObat::with('obat')->latest('id')->limit(10)->get(),
        ]);
    }
}
