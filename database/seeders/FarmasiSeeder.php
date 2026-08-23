<?php

namespace Database\Seeders;

use App\Enums\JenisMutasiStok;
use App\Models\BatchObat;
use App\Models\MutasiStok;
use App\Enums\JenisLayanan;
use App\Models\Tarif;
use App\Models\Obat;
use App\Models\Penjamin;
use Illuminate\Database\Seeder;

class FarmasiSeeder extends Seeder
{
    public function run(): void
    {
        $penjamin = Penjamin::pluck('id', 'kode');
        $obat = Obat::orderBy('id')->get();

        $this->harga($obat, $penjamin);
        $this->stok($obat);
    }

    private function harga($daftarObat, $penjamin): void
    {
        // Harga umum diperkirakan dari bentuk sediaannya; harga BPJS sekitar 70%
        // dan dibulatkan ke ratusan.
        $rentang = [
            'Tablet' => [500, 5000],
            'Kapsul' => [800, 6000],
            'Sirup' => [8000, 35000],
            'Salep' => [15000, 60000],
            'Krim' => [15000, 60000],
            'Larutan' => [10000, 40000],
            'Serbuk' => [1000, 5000],
        ];

        foreach ($daftarObat as $obat) {
            [$min, $maks] = $rentang[$obat->bentuk_sediaan] ?? [1000, 10000];
            $hargaUmum = (int) (round(rand($min, $maks) / 100) * 100);

            Tarif::updateOrCreate([
                'jenis_layanan' => JenisLayanan::Obat,
                'layanan_id' => $obat->id,
                'penjamin_id' => $penjamin['UMUM'],
                'berlaku_mulai' => '2026-01-01',
            ], ['harga' => $hargaUmum]);

            Tarif::updateOrCreate([
                'jenis_layanan' => JenisLayanan::Obat,
                'layanan_id' => $obat->id,
                'penjamin_id' => $penjamin['BPJS'],
                'berlaku_mulai' => '2026-01-01',
            ], ['harga' => (int) (round($hargaUmum * 0.7 / 100) * 100)]);
        }
    }

    /**
     * Batch dibuat langsung, bukan lewat PenerimaanObat, karena sebagian sengaja
     * dibuat sudah kedaluwarsa. Mutasi masuknya tetap dicatat manual supaya
     * kartu stok memperlihatkan riwayat yang utuh, bukan hanya pengeluaran.
     */
    private function catatMasuk(BatchObat $batch): void
    {
        MutasiStok::firstOrCreate([
            'batch_obat_id' => $batch->id,
            'jenis' => JenisMutasiStok::Masuk,
        ], [
            'obat_id' => $batch->obat_id,
            'jumlah' => $batch->jumlah_awal,
            'stok_sesudah' => $batch->jumlah_awal,
            'catatan' => 'Stok awal batch '.$batch->no_batch,
            'created_at' => $batch->diterima_pada ?? now(),
        ]);
    }

    private function stok($daftarObat): void
    {
        foreach ($daftarObat as $indeks => $obat) {
            // Lima obat sengaja bersaldo tipis supaya layar peringatan tidak kosong.
            $tipis = $indeks < 5;

            // Dua batch berbeda kedaluwarsa supaya FEFO terlihat bekerja saat didemokan.
            $this->catatMasuk(BatchObat::updateOrCreate(
                ['obat_id' => $obat->id, 'no_batch' => sprintf('B26A%03d', $obat->id)],
                [
                    'tanggal_kedaluwarsa' => now()->addMonths(8)->toDateString(),
                    'jumlah_awal' => $tipis ? 5 : 120,
                    'jumlah_tersisa' => $tipis ? 5 : 120,
                    'harga_beli' => rand(300, 20000),
                    'diterima_pada' => now()->subMonths(2),
                ]
            ));

            if (! $tipis) {
                $this->catatMasuk(BatchObat::updateOrCreate(
                    ['obat_id' => $obat->id, 'no_batch' => sprintf('B26B%03d', $obat->id)],
                    [
                        'tanggal_kedaluwarsa' => now()->addYears(2)->toDateString(),
                        'jumlah_awal' => 200,
                        'jumlah_tersisa' => 200,
                        'harga_beli' => rand(300, 20000),
                        'diterima_pada' => now()->subWeeks(2),
                    ]
                ));
            }

            // Tiga obat pertama diberi satu batch yang sudah kedaluwarsa sebagai
            // bahan uji aturan 22. Dibuat langsung, bukan lewat PenerimaanObat,
            // karena servicenya memang menolak tanggal kedaluwarsa di masa lalu.
            if ($indeks < 3) {
                $this->catatMasuk(BatchObat::updateOrCreate(
                    ['obat_id' => $obat->id, 'no_batch' => sprintf('B25X%03d', $obat->id)],
                    [
                        'tanggal_kedaluwarsa' => now()->subMonths(2)->toDateString(),
                        'jumlah_awal' => 40,
                        'jumlah_tersisa' => 40,
                        'harga_beli' => rand(300, 20000),
                        'diterima_pada' => now()->subYear(),
                    ]
                ));
            }
        }
    }
}
