<?php

namespace App\Livewire\Poli;

use App\Models\Kunjungan;
use App\Models\Obat;
use App\Services\PenulisanResep;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use RuntimeException;

class FormResep extends Component
{
    use AuthorizesRequests;

    public Kunjungan $kunjungan;

    /** @var array<int, array{obat_id: ?int, jumlah: int, aturan_pakai: string, catatan: ?string}> */
    public array $item = [];

    public function mount(Kunjungan $kunjungan): void
    {
        $this->authorize('periksa', $kunjungan);

        $this->kunjungan = $kunjungan;

        $this->item = $kunjungan->resep
            ? $kunjungan->resep->detail
                ->map(fn ($baris) => $baris->only(['obat_id', 'jumlah', 'aturan_pakai', 'catatan']))
                ->all()
            : [$this->barisKosong()];
    }

    public function tambahBaris(): void
    {
        $this->item[] = $this->barisKosong();
    }

    public function hapusBaris(int $indeks): void
    {
        unset($this->item[$indeks]);
        $this->item = array_values($this->item);
    }

    public function simpan(PenulisanResep $layanan): void
    {
        try {
            $layanan->tulis($this->kunjungan, $this->item, auth()->user());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kolom => $pesan) {
                $this->addError($kolom, $pesan[0]);
            }

            return;
        } catch (RuntimeException $e) {
            $this->addError('item', $e->getMessage());

            return;
        }

        session()->flash('sukses', 'Resep tersimpan.');
    }

    private function barisKosong(): array
    {
        return ['obat_id' => null, 'jumlah' => 1, 'aturan_pakai' => '', 'catatan' => null];
    }

    public function render()
    {
        return view('livewire.poli.form-resep', [
            'daftarObat' => Obat::where('aktif', true)->orderBy('nama')->get(),
        ]);
    }
}
