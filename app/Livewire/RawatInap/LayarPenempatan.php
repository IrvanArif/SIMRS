<?php

namespace App\Livewire\RawatInap;

use App\Models\Bed;
use App\Models\RawatInap;
use App\Services\PenempatanBed;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use RuntimeException;

class LayarPenempatan extends Component
{
    use AuthorizesRequests;

    public RawatInap $rawatInap;

    public ?int $bed_id = null;

    public function mount(RawatInap $rawatInap): void
    {
        $this->authorize('tempatkan', $rawatInap);

        $this->rawatInap = $rawatInap;
    }

    public function tempatkan(PenempatanBed $layanan)
    {
        $bed = Bed::find($this->bed_id);

        if ($bed === null) {
            $this->addError('bed_id', 'Pilih bed lebih dulu.');

            return null;
        }

        try {
            $layanan->tempatkan($this->rawatInap, $bed, auth()->user());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kolom => $pesan) {
                $this->addError($kolom, $pesan[0]);
            }

            return null;
        } catch (RuntimeException $e) {
            $this->addError('penempatan', $e->getMessage());

            return null;
        }

        return $this->redirectRoute('rawat-inap.papan');
    }

    public function render()
    {
        return view('livewire.rawat-inap.layar-penempatan', [
            // Bed sekelas dengan yang diminta ditawarkan lebih dulu, tapi kelas
            // lain tetap terlihat: kamar yang diminta kerap penuh.
            'bedTersedia' => Bed::kosong()
                ->with('ruang', 'kelas')
                ->get()
                ->sortBy([
                    fn ($bed) => $bed->kelas_kamar_id === $this->rawatInap->kelas_diminta_id ? 0 : 1,
                    fn ($bed) => $bed->ruang->nama.' '.$bed->nomor,
                ])
                ->values(),
        ]);
    }
}
