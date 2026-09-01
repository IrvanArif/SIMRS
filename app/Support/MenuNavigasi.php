<?php

namespace App\Support;

use App\Enums\Peran;
use App\Models\User;

/**
 * Menu disusun dari daftar tunggal ini, bukan ditulis ulang di tiap view.
 *
 * Perannya di sini hanya menentukan apa yang *terlihat*. Yang menegakkan
 * kewenangan tetap middleware dan Policy — menyembunyikan tautan bukan
 * pengamanan. Namun keduanya harus sejalan: menu yang menampilkan tautan
 * berujung 403 lebih buruk daripada tidak ada menu, karena pengguna diundang
 * ke pintu yang terkunci.
 */
class MenuNavigasi
{
    /**
     * kelompok => [judul, [rute, label, peran yang boleh melihat, berpoli?]]
     *
     * `berpoli` menandai tautan yang hanya masuk akal bagi dokter pemegang poli.
     *
     * @var list<array{judul: string, tautan: list<array{rute: string, label: string, peran: list<string>, berpoli?: bool}>}>
     */
    private const SUSUNAN = [
        [
            'judul' => 'Pendaftaran',
            'tautan' => [
                ['rute' => 'pendaftaran.pasien', 'label' => 'Cari Pasien', 'peran' => ['admisi']],
                ['rute' => 'pendaftaran.pasien.baru', 'label' => 'Pasien Baru', 'peran' => ['admisi']],
                ['rute' => 'pendaftaran.antrian', 'label' => 'Papan Antrian', 'peran' => ['admisi']],
            ],
        ],
        [
            'judul' => 'Poli',
            'tautan' => [
                ['rute' => 'poli.antrian', 'label' => 'Antrian Poli', 'peran' => ['perawat', 'dokter'], 'berpoli' => true],
            ],
        ],
        [
            'judul' => 'Rawat Inap',
            'tautan' => [
                ['rute' => 'rawat-inap.papan', 'label' => 'Papan Bed', 'peran' => ['admisi', 'perawat', 'dokter', 'kasir', 'rekam_medis'], 'berpoli' => true],
            ],
        ],
        [
            'judul' => 'Penunjang',
            'tautan' => [
                ['rute' => 'lab.antrean', 'label' => 'Antrean Laboratorium', 'peran' => ['analis']],
                ['rute' => 'radiologi.antrean', 'label' => 'Antrean Radiologi', 'peran' => ['radiografer', 'dokter']],
            ],
        ],
        [
            'judul' => 'Apotek',
            'tautan' => [
                ['rute' => 'apotek.antrean', 'label' => 'Antrean Resep', 'peran' => ['apoteker']],
                ['rute' => 'apotek.penerimaan', 'label' => 'Penerimaan Batch', 'peran' => ['apoteker']],
                ['rute' => 'apotek.peringatan', 'label' => 'Peringatan Stok', 'peran' => ['apoteker']],
            ],
        ],
        [
            'judul' => 'Kasir',
            'tautan' => [
                ['rute' => 'kasir.tagihan', 'label' => 'Daftar Tagihan', 'peran' => ['kasir']],
            ],
        ],
        [
            'judul' => 'Rekam Medis',
            'tautan' => [
                ['rute' => 'rekam-medis.telusur', 'label' => 'Penelusuran', 'peran' => ['rekam_medis']],
                ['rute' => 'rekam-medis.rekap', 'label' => 'Rekap Harian', 'peran' => ['rekam_medis']],
            ],
        ],
        [
            'judul' => 'Klaim',
            'tautan' => [
                ['rute' => 'klaim.sep', 'label' => 'SEP', 'peran' => ['admisi', 'rekam_medis', 'kasir', 'admin']],
                ['rute' => 'klaim.berkas', 'label' => 'Berkas Klaim', 'peran' => ['rekam_medis', 'kasir', 'admin']],
            ],
        ],
        [
            'judul' => 'Laporan',
            'tautan' => [
                ['rute' => 'laporan.indikator', 'label' => 'Indikator Rawat Inap', 'peran' => ['rekam_medis', 'admin']],
                ['rute' => 'laporan.morbiditas', 'label' => 'Sepuluh Besar Penyakit', 'peran' => ['rekam_medis', 'admin']],
                ['rute' => 'laporan.kunjungan', 'label' => 'Rekap Kunjungan', 'peran' => ['rekam_medis', 'admin']],
                ['rute' => 'laporan.pendapatan', 'label' => 'Pendapatan', 'peran' => ['kasir', 'rekam_medis', 'admin']],
            ],
        ],
        [
            'judul' => 'Master Data',
            'tautan' => [
                ['rute' => 'master.poli', 'label' => 'Poli', 'peran' => ['admin']],
                ['rute' => 'master.dokter', 'label' => 'Dokter', 'peran' => ['admin']],
                ['rute' => 'master.tindakan', 'label' => 'Tindakan', 'peran' => ['admin']],
                ['rute' => 'master.tarif', 'label' => 'Tarif', 'peran' => ['admin']],
                ['rute' => 'master.pemeriksaan-radiologi', 'label' => 'Pemeriksaan Radiologi', 'peran' => ['admin']],
                ['rute' => 'master.ruang-bed', 'label' => 'Ruang dan Bed', 'peran' => ['admin']],
            ],
        ],
        [
            'judul' => 'Administrasi',
            'tautan' => [
                ['rute' => 'admin.user', 'label' => 'Kelola Pengguna', 'peran' => ['admin']],
                ['rute' => 'admin.audit', 'label' => 'Audit Log', 'peran' => ['admin']],
            ],
        ],
    ];

    /**
     * Kelompok yang seluruh tautannya tidak terlihat ikut dibuang, sehingga
     * tidak ada judul kelompok yang menggantung tanpa isi.
     *
     * @return list<array{judul: string, tautan: list<array{rute: string, label: string}>}>
     */
    public static function untuk(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $peran = $user->getRoleNames()->all();
        $hasil = [];

        foreach (self::SUSUNAN as $kelompok) {
            $tautan = [];

            foreach ($kelompok['tautan'] as $satu) {
                if (array_intersect($satu['peran'], $peran) === []) {
                    continue;
                }

                // Sebagian layar hanya masuk akal bagi dokter yang memegang poli.
                // Dokter radiologi berperan dokter tetapi tidak memegang pasien
                // poli; menampilkan antrian poli kepadanya berarti mengundangnya
                // ke daftar yang satu pun tidak bisa ia buka.
                if (($satu['berpoli'] ?? false)
                    && in_array(Peran::Dokter->value, $peran, true)
                    && ! in_array(Peran::Perawat->value, $peran, true)
                    && $user->dokter_id === null) {
                    continue;
                }

                $tautan[] = ['rute' => $satu['rute'], 'label' => $satu['label']];
            }

            if ($tautan !== []) {
                $hasil[] = ['judul' => $kelompok['judul'], 'tautan' => $tautan];
            }
        }

        return $hasil;
    }

    /**
     * Daftar rata tanpa pengelompokan, untuk bilah navigasi atas.
     *
     * @return list<array{rute: string, label: string}>
     */
    public static function rataUntuk(?User $user): array
    {
        return array_merge(...array_column(self::untuk($user), 'tautan')) ?: [];
    }

    /** @return list<string> */
    public static function peranYangDilayani(): array
    {
        return Peran::semua();
    }
}
