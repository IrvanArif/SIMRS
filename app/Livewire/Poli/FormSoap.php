<?php

namespace App\Livewire\Poli;

use App\Enums\JenisDiagnosa;
use App\Models\Icd10;
use App\Models\Kunjungan;
use App\Models\PemeriksaanLab;
use App\Models\Tindakan;
use App\Services\PemesananLab;
use App\Services\PemeriksaanKlinis;
use App\Services\TindakanPelayanan;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use RuntimeException;

class FormSoap extends Component
{
    use AuthorizesRequests;

    public Kunjungan $kunjungan;

    public string $subjective = '';
    public string $objective = '';
    public string $assessment = '';
    public string $plan = '';
    public ?int $icd10_id = null;
    public ?int $tindakan_id = null;
    public int $jumlah_tindakan = 1;

    /** @var list<int> */
    public array $pemeriksaanLabDipilih = [];

    public function mount(Kunjungan $kunjungan): void
    {
        $this->authorize('periksa', $kunjungan);

        $this->kunjungan = $kunjungan;

        if ($pemeriksaan = $kunjungan->pemeriksaan) {
            $this->fill(array_map(
                fn ($nilai) => (string) $nilai,
                $pemeriksaan->only(['subjective', 'objective', 'assessment', 'plan'])
            ));
        }
    }

    public function simpanSoap(PemeriksaanKlinis $layanan): void
    {
        $this->jalankan(fn () => $layanan->catatSoap(
            $this->kunjungan,
            $this->only(['subjective', 'objective', 'assessment', 'plan']),
            auth()->user()
        ));
    }

    public function tambahDiagnosaPrimer(PemeriksaanKlinis $layanan): void
    {
        $this->jalankan(fn () => $layanan->tambahDiagnosa(
            $this->kunjungan, (int) $this->icd10_id, JenisDiagnosa::Primer
        ));
    }

    public function tambahDiagnosaSekunder(PemeriksaanKlinis $layanan): void
    {
        $this->jalankan(fn () => $layanan->tambahDiagnosa(
            $this->kunjungan, (int) $this->icd10_id, JenisDiagnosa::Sekunder
        ));
    }

    public function tambahTindakan(TindakanPelayanan $layanan): void
    {
        $this->jalankan(fn () => $layanan->tambah(
            $this->kunjungan, (int) $this->tindakan_id, $this->jumlah_tindakan, auth()->user()
        ));
    }

    public function pesanLab(PemesananLab $layanan): void
    {
        $this->jalankan(fn () => $layanan->pesan(
            $this->kunjungan, $this->pemeriksaanLabDipilih, auth()->user()
        ));

        $this->reset('pemeriksaanLabDipilih');
    }

    public function selesaikan(PemeriksaanKlinis $layanan): void
    {
        $this->jalankan(fn () => $layanan->selesaikan($this->kunjungan, auth()->user()));
    }

    /**
     * RuntimeException dipetakan ke kunci "penyelesaian" supaya pesan seperti
     * "diagnosa primer belum ditetapkan" tampil di dekat tombol, bukan jadi error 500.
     */
    private function jalankan(callable $aksi): void
    {
        try {
            $aksi();
            $this->kunjungan->refresh();
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kolom => $pesan) {
                $this->addError($kolom, $pesan[0]);
            }
        } catch (RuntimeException $e) {
            $this->addError('penyelesaian', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.poli.form-soap', [
            'daftarIcd' => Icd10::orderBy('kode')->limit(50)->get(),
            'daftarTindakan' => Tindakan::where('aktif', true)->orderBy('nama')->get(),
            'daftarPemeriksaanLab' => PemeriksaanLab::where('aktif', true)->orderBy('nama')->get(),
            'orderLab' => $this->kunjungan->orderLab()
                ->with('detail.pemeriksaan', 'detail.hasil.parameter')->get(),
            'riwayat' => $this->kunjungan->pasien->kunjungan()
                ->where('id', '!=', $this->kunjungan->id)
                ->with('pemeriksaan', 'diagnosa.icd10')
                ->latest('tanggal')->limit(5)->get(),
        ]);
    }
}
