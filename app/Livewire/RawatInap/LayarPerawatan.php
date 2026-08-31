<?php

namespace App\Livewire\RawatInap;

use App\Models\Bed;
use App\Models\RawatInap;
use App\Services\CatatanHarian;
use App\Services\PenempatanBed;
use App\Services\PenghitungBiayaKamar;
use App\Services\PenyusunTagihan;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use RuntimeException;

class LayarPerawatan extends Component
{
    use AuthorizesRequests;

    public RawatInap $rawatInap;

    public string $subjective = '';

    public string $objective = '';

    public string $assessment = '';

    public string $plan = '';

    public ?int $bed_tujuan_id = null;

    public string $alasanPindah = '';

    public function mount(RawatInap $rawatInap): void
    {
        $this->authorize('rawat', $rawatInap);

        $this->rawatInap = $rawatInap;
    }

    public function simpanCatatan(CatatanHarian $layanan): void
    {
        $berhasil = $this->jalankan(fn () => $layanan->tulis(
            $this->rawatInap,
            $this->only(['subjective', 'objective', 'assessment', 'plan']),
            auth()->user()
        ), 'catatan');

        if ($berhasil) {
            $this->reset('subjective', 'objective', 'assessment', 'plan');
            session()->flash('sukses', 'Catatan perkembangan tersimpan.');
        }
    }

    public function pindahBed(PenempatanBed $layanan): void
    {
        $tujuan = Bed::find($this->bed_tujuan_id);

        if ($tujuan === null) {
            $this->addError('bed_tujuan_id', 'Pilih bed tujuan lebih dulu.');

            return;
        }

        $berhasil = $this->jalankan(
            fn () => $layanan->pindahkan($this->rawatInap, $tujuan, auth()->user(), $this->alasanPindah),
            'pindah'
        );

        if ($berhasil) {
            $this->reset('bed_tujuan_id', 'alasanPindah');
            session()->flash('sukses', 'Pasien dipindahkan.');
        }
    }

    private function jalankan(callable $aksi, string $kunciGalat): bool
    {
        try {
            $aksi();
            $this->rawatInap->refresh();
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kolom => $pesan) {
                $this->addError($kolom, $pesan[0]);
            }

            return false;
        } catch (RuntimeException $e) {
            $this->addError($kunciGalat, $e->getMessage());

            return false;
        }

        return true;
    }

    public function render()
    {
        return view('livewire.rawat-inap.layar-perawatan', [
            'catatan' => $this->rawatInap->catatan()->with('penulis')->get(),
            'penggalKamar' => app(PenghitungBiayaKamar::class)->penggal($this->rawatInap),
            'rincian' => app(PenyusunTagihan::class)->rincianSementara($this->rawatInap->kunjungan),
            'bedTersedia' => Bed::kosong()->with('ruang', 'kelas')->get()
                ->sortBy(fn ($bed) => $bed->ruang->nama.' '.$bed->nomor)->values(),
        ]);
    }
}
