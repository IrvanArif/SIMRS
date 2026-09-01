<?php

namespace App\Livewire\Klaim;

use App\Models\Kunjungan;
use App\Models\Sep;
use App\Services\PenerbitanSep;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use RuntimeException;

class DaftarSep extends Component
{
    use AuthorizesRequests;

    public ?int $kunjungan_id = null;

    public string $diagnosa_awal = '';

    public string $no_rujukan = '';

    public ?int $sep_id = null;

    public string $alasanBatal = '';

    public function terbitkan(PenerbitanSep $layanan): void
    {
        $this->authorize('terbitkan', Sep::class);

        $kunjungan = Kunjungan::find($this->kunjungan_id);

        if ($kunjungan === null) {
            $this->addError('kunjungan_id', 'Pilih kunjungan lebih dulu.');

            return;
        }

        if ($this->jalankan(fn () => $layanan->terbitkan(
            $kunjungan,
            auth()->user(),
            $this->diagnosa_awal,
            trim($this->no_rujukan) === '' ? null : trim($this->no_rujukan)
        ))) {
            $this->reset('kunjungan_id', 'diagnosa_awal', 'no_rujukan');
            session()->flash('sukses', 'SEP diterbitkan.');
        }
    }

    public function batalkan(PenerbitanSep $layanan): void
    {
        $sep = Sep::find($this->sep_id);

        if ($sep === null) {
            $this->addError('sep_id', 'Pilih SEP yang akan dibatalkan.');

            return;
        }

        $this->authorize('batalkan', $sep);

        if ($this->jalankan(fn () => $layanan->batalkan($sep, auth()->user(), $this->alasanBatal))) {
            $this->reset('sep_id', 'alasanBatal');
            session()->flash('sukses', 'SEP dibatalkan.');
        }
    }

    private function jalankan(callable $aksi): bool
    {
        try {
            $aksi();
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kolom => $pesan) {
                $this->addError($kolom, $pesan[0]);
            }

            return false;
        } catch (RuntimeException $e) {
            $this->addError('sep', $e->getMessage());

            return false;
        }

        return true;
    }

    public function render()
    {
        return view('livewire.klaim.daftar-sep', [
            // Kunjungan berpenjamin yang belum ber-SEP. Pasien tunai tidak
            // pernah muncul: tidak ada yang menjaminnya (aturan 78).
            'menungguSep' => Kunjungan::whereHas('penjamin', fn ($q) => $q->where('jenis', 'penjamin'))
                ->whereDoesntHave('sep', fn ($q) => $q->berlaku())
                ->with('pasien', 'poli', 'penjamin')
                ->latest('id')
                ->limit(25)
                ->get(),
            'daftarSep' => Sep::with('kunjungan.pasien')->latest('id')->limit(25)->get(),
        ]);
    }
}
