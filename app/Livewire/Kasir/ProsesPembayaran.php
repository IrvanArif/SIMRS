<?php

namespace App\Livewire\Kasir;

use App\Enums\MetodePembayaran;
use App\Models\Tagihan;
use App\Services\ProsesPembayaran as LayananPembayaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use RuntimeException;

class ProsesPembayaran extends Component
{
    use AuthorizesRequests;

    public Tagihan $tagihan;

    public string $metode = 'tunai';
    public int $nominal = 0;

    public function mount(Tagihan $tagihan): void
    {
        $this->authorize('proses', $tagihan);

        $this->tagihan = $tagihan;
        $this->nominal = (int) $tagihan->ditagihkan_ke_pasien;
    }

    public function bayar(LayananPembayaran $layanan)
    {
        try {
            $pembayaran = $layanan->bayar(
                $this->tagihan,
                MetodePembayaran::from($this->metode),
                $this->nominal,
                auth()->user()
            );
        } catch (RuntimeException $e) {
            $this->addError('nominal', $e->getMessage());

            return null;
        }

        return $this->redirectRoute('cetak.kuitansi', ['pembayaran' => $pembayaran->id]);
    }

    public function render()
    {
        return view('livewire.kasir.proses-pembayaran');
    }
}
