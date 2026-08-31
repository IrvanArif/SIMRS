<?php

namespace App\Services;

use App\Enums\StatusOrderLab;
use App\Enums\StatusOrderRadiologi;
use App\Enums\StatusTagihan;
use App\Models\Kunjungan;
use App\Models\Resep;
use App\Models\Tagihan;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PenyusunTagihan
{
    public function __construct(
        private readonly NomorDokumen $nomorDokumen,
        private readonly PenghitungBiayaKamar $biayaKamar,
    ) {}

    /**
     * Idempoten: pemanggilan kedua mengembalikan tagihan yang sudah ada (aturan 12).
     */
    public function susun(Kunjungan $kunjungan): Tagihan
    {
        if ($kunjungan->tagihan !== null) {
            return $kunjungan->tagihan;
        }

        return DB::transaction(function () use ($kunjungan) {
            $ditanggung = $kunjungan->penjamin->ditanggung();

            $tagihan = Tagihan::create([
                'no_tagihan' => $this->nomorDokumen->berikutnya('tagihan', $kunjungan->tanggal),
                'kunjungan_id' => $kunjungan->id,
                'penjamin_id' => $kunjungan->penjamin_id,
                'total' => 0,
                'ditanggung_penjamin' => 0,
                'ditagihkan_ke_pasien' => 0,
                // Nilai penuh tetap dicatat meski pasien tidak membayar — itu bahan
                // klaim di fase berikutnya (aturan 14).
                'status' => $ditanggung ? StatusTagihan::DitanggungPenjamin : StatusTagihan::BelumBayar,
                'disusun_pada' => now(),
            ]);

            // Seluruh baris dibangun lewat satu himpunan pembuat yang sama
            // dengan rincianSementara(), supaya angka yang disebutkan kepada
            // keluarga pasien hari ini tidak berselisih dengan tagihan nanti.
            $baris = array_merge(
                $this->barisTindakan($kunjungan),
                $this->barisLab($kunjungan),
                $this->barisRadiologi($kunjungan),
                $this->barisKamar($kunjungan),
                // Obat yang sudah diserahkan selama pasien dirawat inap. Untuk
                // pasien rawat jalan bagian ini selalu kosong: tagihannya dibuat
                // sebelum apotek menyiapkan, dan obatnya menyusul lewat tambahObat().
                $this->barisObatTerserahkan($kunjungan),
            );

            foreach ($baris as $satu) {
                $tagihan->detail()->create($satu);
            }

            // Totalnya dihitung dari rinciannya, bukan dijumlahkan terpisah,
            // supaya angkanya tidak pernah dihitung di dua tempat.
            return $this->hitungUlang($tagihan);
        });
    }

    /**
     * Menambahkan baris obat ke tagihan kunjungan yang sudah ada (aturan 28).
     * Tagihan tidak disusun ulang — hanya ditambahi, dan hanya selama belum lunas.
     */
    public function tambahObat(Resep $resep): ?Tagihan
    {
        $tagihan = $resep->kunjungan->tagihan;

        if ($tagihan === null) {
            if ($resep->kunjungan->sedangDirawatInap()) {
                // Tagihan rawat inap baru disusun saat pasien pulang. Biayanya
                // tidak hilang: susun() memungutnya dari resep yang sudah
                // diserahkan pada saat itu.
                return null;
            }

            throw new RuntimeException(
                'Kunjungan ini belum punya tagihan. Dokter harus menyelesaikan kunjungan lebih dulu.'
            );
        }

        if ($tagihan->status === StatusTagihan::Lunas) {
            throw new RuntimeException(
                "Tagihan {$tagihan->no_tagihan} sudah lunas dan tidak bisa ditambahi biaya obat."
            );
        }

        return DB::transaction(function () use ($resep, $tagihan) {
            foreach ($resep->detail as $baris) {
                if ((int) $baris->jumlah_diserahkan === 0) {
                    continue;
                }

                // Baris yang sudah dipungut susun() tidak ditambahkan dua kali.
                if ($tagihan->detail()
                    ->where('sumber_tipe', $baris::class)
                    ->where('sumber_id', $baris->id)
                    ->exists()) {
                    continue;
                }

                $tagihan->detail()->create([
                    'sumber_tipe' => $baris::class,
                    'sumber_id' => $baris->id,
                    'deskripsi' => $baris->obat->nama,
                    'jumlah' => $baris->jumlah_diserahkan,
                    'tarif_satuan' => $baris->harga_satuan,
                    'subtotal' => $baris->subtotal(),
                ]);
            }

            return $this->hitungUlang($tagihan);
        });
    }

    /**
     * Total berjalan tanpa membuat tagihan (aturan 75). Keluarga pasien lazim
     * menanyakan biaya sementara, dan bertanya bukan menutup berkas.
     *
     * Dihitung dari sumber yang sama dengan susun(), supaya angka yang
     * disebutkan hari ini tidak berselisih dengan tagihan yang keluar nanti.
     *
     * @return array{baris: list<array<string, mixed>>, total: int}
     */
    public function rincianSementara(Kunjungan $kunjungan): array
    {
        if ($kunjungan->tagihan !== null) {
            $baris = $kunjungan->tagihan->detail->map(fn ($d) => [
                'deskripsi' => $d->deskripsi,
                'jumlah' => (int) $d->jumlah,
                'tarif_satuan' => (int) $d->tarif_satuan,
                'subtotal' => (int) $d->subtotal,
            ])->all();

            return ['baris' => $baris, 'total' => (int) $kunjungan->tagihan->total];
        }

        $baris = array_merge(
            $this->barisKamar($kunjungan),
            $this->barisTindakan($kunjungan),
            $this->barisLab($kunjungan),
            $this->barisRadiologi($kunjungan),
            $this->barisObatTerserahkan($kunjungan),
        );

        return [
            'baris' => array_values(array_map(
                fn (array $b) => Arr::only($b, ['deskripsi', 'jumlah', 'tarif_satuan', 'subtotal']),
                $baris
            )),
            'total' => array_sum(array_column($baris, 'subtotal')),
        ];
    }

    /**
     * Satu baris per penggal okupansi (aturan 72): pasien yang pindah kelas di
     * tengah masa rawat ditagih dua tarif berbeda, dan rinciannya terlihat.
     *
     * @return list<array<string, mixed>>
     */
    private function barisKamar(Kunjungan $kunjungan): array
    {
        $rawatInap = $kunjungan->rawatInap;

        if ($rawatInap === null) {
            return [];
        }

        return array_map(function (array $penggal) {
            $okupansi = $penggal['okupansi'];

            return [
                'sumber_tipe' => $okupansi::class,
                'sumber_id' => $okupansi->id,
                'deskripsi' => $this->biayaKamar->deskripsi($okupansi),
                'jumlah' => $penggal['hari'],
                'tarif_satuan' => (int) $okupansi->tarif_harian,
                'subtotal' => $penggal['subtotal'],
            ];
        }, $this->biayaKamar->penggal($rawatInap));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function barisObatTerserahkan(Kunjungan $kunjungan): array
    {
        $baris = [];

        foreach ($kunjungan->resep()->with('detail.obat')->get() as $resep) {
            foreach ($resep->detail as $item) {
                if ((int) $item->jumlah_diserahkan === 0) {
                    continue;
                }

                $baris[] = [
                    'sumber_tipe' => $item::class,
                    'sumber_id' => $item->id,
                    'deskripsi' => $item->obat->nama,
                    'jumlah' => (int) $item->jumlah_diserahkan,
                    'tarif_satuan' => (int) $item->harga_satuan,
                    'subtotal' => $item->subtotal(),
                ];
            }
        }

        return $baris;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function barisTindakan(Kunjungan $kunjungan): array
    {
        return $kunjungan->tindakan()->with('tindakan')->get()->map(fn ($item) => [
            'sumber_tipe' => $item::class,
            'sumber_id' => $item->id,
            'deskripsi' => $item->tindakan->nama,
            'jumlah' => (int) $item->jumlah,
            'tarif_satuan' => (int) $item->tarif_satuan,
            'subtotal' => $item->subtotal(),
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function barisLab(Kunjungan $kunjungan): array
    {
        // Order yang dibatalkan sebelum sampel diambil tidak ditagihkan
        // (aturan 45); yang dibatalkan setelah sampel tetap ditagihkan karena
        // bahan dan waktu kerjanya sudah terpakai (aturan 46).
        $baris = [];

        $order = $kunjungan->orderLab()
            ->where(function ($q) {
                $q->where('status', '!=', StatusOrderLab::Batal->value)
                    ->orWhereNotNull('waktu_sampel');
            })
            ->with('detail.pemeriksaan')
            ->get();

        foreach ($order as $satu) {
            foreach ($satu->detail as $item) {
                $baris[] = [
                    'sumber_tipe' => $item::class,
                    'sumber_id' => $item->id,
                    'deskripsi' => $item->pemeriksaan->nama,
                    'jumlah' => 1,
                    'tarif_satuan' => (int) $item->tarif_satuan,
                    'subtotal' => (int) $item->tarif_satuan,
                ];
            }
        }

        return $baris;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function barisRadiologi(Kunjungan $kunjungan): array
    {
        // Aturan 57: yang dibatalkan sebelum dikerjakan tidak ditagihkan; yang
        // dibatalkan setelah dikerjakan tetap ditagihkan karena film dan waktu
        // alatnya sudah terpakai.
        $baris = [];

        $order = $kunjungan->orderRadiologi()
            ->where(function ($q) {
                $q->where('status', '!=', StatusOrderRadiologi::Batal->value)
                    ->orWhereNotNull('waktu_dikerjakan');
            })
            ->with('detail.pemeriksaan')
            ->get();

        foreach ($order as $satu) {
            foreach ($satu->detail as $item) {
                $baris[] = [
                    'sumber_tipe' => $item::class,
                    'sumber_id' => $item->id,
                    'deskripsi' => $item->pemeriksaan->nama,
                    'jumlah' => 1,
                    'tarif_satuan' => (int) $item->tarif_satuan,
                    'subtotal' => (int) $item->tarif_satuan,
                ];
            }
        }

        return $baris;
    }

    /**
     * Mencabut seluruh baris yang berasal dari satu jenis sumber. Dipakai saat
     * penyiapan resep dibatalkan, dan nanti saat order laboratorium dibatalkan.
     */
    public function hapusBarisDari(Tagihan $tagihan, string $sumberTipe): void
    {
        $tagihan->detail()->where('sumber_tipe', $sumberTipe)->delete();
    }

    /**
     * Menyetel ulang total dari seluruh rinciannya. Dipakai setiap kali baris
     * ditambahkan atau dibatalkan, supaya angkanya tidak pernah dihitung di dua tempat.
     */
    public function hitungUlang(Tagihan $tagihan): Tagihan
    {
        $total = (int) $tagihan->detail()->sum('subtotal');
        $ditanggung = $tagihan->penjamin->ditanggung();

        $tagihan->update([
            'total' => $total,
            'ditanggung_penjamin' => $ditanggung ? $total : 0,
            'ditagihkan_ke_pasien' => $ditanggung ? 0 : $total,
        ]);

        return $tagihan->refresh();
    }
}
