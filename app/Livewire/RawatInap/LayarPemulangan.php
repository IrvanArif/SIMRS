<?php

namespace App\Livewire\RawatInap;

use App\Enums\CaraPulang;
use App\Models\Icd10;
use App\Models\RawatInap;
use App\Services\PemulanganPasien;
use App\Services\PenghitungBiayaKamar;
use App\Services\PenyusunTagihan;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use RuntimeException;

class LayarPemulangan extends Component
{
    use AuthorizesRequests;

    public RawatInap $rawatInap;

    public ?int $diagnosa_akhir_id = null;

    public string $cara_pulang = 'sembuh';

    public string $ringkasan = '';

    public function mount(RawatInap $rawatInap): void
    {
        $this->authorize('pulangkan', $rawatInap);

        $this->rawatInap = $rawatInap;
    }

    public function pulangkan(PemulanganPasien $layanan)
    {
        try {
            $layanan->pulangkan(
                $this->rawatInap,
                auth()->user(),
                (int) $this->diagnosa_akhir_id,
                CaraPulang::from($this->cara_pulang),
                trim($this->ringkasan) === '' ? null : trim($this->ringkasan)
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kolom => $pesan) {
                $this->addError($kolom, $pesan[0]);
            }

            return null;
        } catch (RuntimeException $e) {
            $this->addError('pemulangan', $e->getMessage());

            return null;
        }

        return $this->redirectRoute('rawat-inap.papan');
    }

    public function render()
    {
        return view('livewire.rawat-inap.layar-pemulangan', [
            'daftarIcd' => Icd10::orderBy('kode')->limit(50)->get(),
            'pilihanCara' => CaraPulang::cases(),
            'penggalKamar' => app(PenghitungBiayaKamar::class)->penggal($this->rawatInap),
            'rincian' => app(PenyusunTagihan::class)->rincianSementara($this->rawatInap->kunjungan),
        ]);
    }
}
