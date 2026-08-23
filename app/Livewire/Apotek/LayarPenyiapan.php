<?php

namespace App\Livewire\Apotek;

use App\Models\BatchObat;
use App\Models\Resep;
use App\Services\PenyiapanResep;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use RuntimeException;

class LayarPenyiapan extends Component
{
    use AuthorizesRequests;

    public Resep $resep;

    public string $alasanBatal = '';

    public function mount(Resep $resep): void
    {
        $this->authorize('serahkan', $resep);

        $this->resep = $resep;
    }

    public function siapkan(PenyiapanResep $layanan): void
    {
        $this->jalankan(fn () => $layanan->siapkan($this->resep, auth()->user()));
    }

    public function batalkan(PenyiapanResep $layanan): void
    {
        $this->jalankan(fn () => $layanan->batalkan($this->resep, auth()->user(), $this->alasanBatal));
    }

    private function jalankan(callable $aksi): void
    {
        try {
            $aksi();
            $this->resep->refresh();
            $this->reset('alasanBatal');
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kolom => $pesan) {
                $this->addError($kolom, $pesan[0]);
            }
        } catch (RuntimeException $e) {
            // Termasuk StokTidakCukup dan SeluruhBatchKedaluwarsa — keduanya
            // turunan RuntimeException dan pesannya sudah layak tampil apa adanya.
            $this->addError('penyiapan', $e->getMessage());
        }
    }

    public function render()
    {
        // Batch calon sumber ditampilkan dalam urutan FEFO supaya apoteker
        // melihat apa yang akan diambil sebelum menekan tombol, bukan sesudahnya.
        $rencana = [];

        foreach ($this->resep->detail as $baris) {
            $rencana[$baris->id] = BatchObat::where('obat_id', $baris->obat_id)
                ->layakPakai()
                ->orderBy('tanggal_kedaluwarsa')
                ->orderBy('id')
                ->get();
        }

        return view('livewire.apotek.layar-penyiapan', ['rencanaBatch' => $rencana]);
    }
}
