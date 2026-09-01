<?php

namespace App\Livewire\Klaim;

use App\Enums\StatusBerkasKlaim;
use App\Enums\StatusKunjungan;
use App\Models\BerkasKlaim;
use App\Models\Kunjungan;
use App\Services\PenyusunBerkasKlaim;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use RuntimeException;

class DaftarBerkas extends Component
{
    use AuthorizesRequests;

    public ?int $berkas_id = null;

    public string $hasil = 'disetujui';

    public string $catatanVerifikasi = '';

    public string $alasanBatal = '';

    public function susun(int $kunjunganId, PenyusunBerkasKlaim $layanan): void
    {
        $this->authorize('susun', BerkasKlaim::class);

        $kunjungan = Kunjungan::findOrFail($kunjunganId);

        if ($this->jalankan(fn () => $layanan->susun($kunjungan, auth()->user()))) {
            session()->flash('sukses', 'Berkas klaim tersusun sebagai draf.');
        }
    }

    public function ajukan(int $berkasId, PenyusunBerkasKlaim $layanan): void
    {
        $berkas = BerkasKlaim::findOrFail($berkasId);

        $this->authorize('ajukan', $berkas);

        if ($this->jalankan(fn () => $layanan->ajukan($berkas, auth()->user()))) {
            session()->flash('sukses', 'Berkas klaim diajukan.');
        }
    }

    public function batalkan(PenyusunBerkasKlaim $layanan): void
    {
        $berkas = BerkasKlaim::find($this->berkas_id);

        if ($berkas === null) {
            $this->addError('berkas_id', 'Pilih berkas yang akan dibatalkan.');

            return;
        }

        $this->authorize('batalkan', $berkas);

        if ($this->jalankan(fn () => $layanan->batalkan($berkas, auth()->user(), $this->alasanBatal))) {
            $this->reset('berkas_id', 'alasanBatal');
            session()->flash('sukses', 'Berkas klaim dibatalkan.');
        }
    }

    public function tandaiHasil(PenyusunBerkasKlaim $layanan): void
    {
        $berkas = BerkasKlaim::find($this->berkas_id);

        if ($berkas === null) {
            $this->addError('berkas_id', 'Pilih berkas yang akan ditandai.');

            return;
        }

        $this->authorize('verifikasi', $berkas);

        if ($this->jalankan(fn () => $layanan->tandaiHasil(
            $berkas,
            StatusBerkasKlaim::from($this->hasil),
            auth()->user(),
            $this->catatanVerifikasi
        ))) {
            $this->reset('berkas_id', 'catatanVerifikasi');
            session()->flash('sukses', 'Hasil verifikasi tercatat.');
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
            $this->addError('klaim', $e->getMessage());

            return false;
        }

        return true;
    }

    public function render()
    {
        return view('livewire.klaim.daftar-berkas', [
            // Kunjungan berpenjamin yang sudah selesai tetapi klaimnya belum
            // disusun. Inilah antrean kerja pengkode rekam medis.
            'menungguKlaim' => Kunjungan::whereHas('penjamin', fn ($q) => $q->where('jenis', 'penjamin'))
                ->where('status', StatusKunjungan::Selesai->value)
                ->whereDoesntHave('berkasKlaim', fn ($q) => $q->berlaku())
                ->with('pasien', 'tagihan')
                ->latest('id')
                ->limit(25)
                ->get(),
            'daftarBerkas' => BerkasKlaim::with('kunjungan.pasien', 'sep')
                ->latest('id')->limit(25)->get(),
            'pilihanHasil' => [StatusBerkasKlaim::Disetujui, StatusBerkasKlaim::Ditolak],
        ]);
    }
}
