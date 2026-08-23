<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIMRS — Sistem Informasi Manajemen Rumah Sakit</title>
    <meta name="description" content="Sistem informasi manajemen rumah sakit berbasis Laravel: pendaftaran, antrian, rekam medis, farmasi, dan kasir dalam satu alur.">
    @vite(['resources/css/app.css'])
    <style>
        :root {
            --dasar: #080c11;
            --panel: #0f171f;
            --garis: #1d2a36;
            --vital: #34e39b;
            --vital-redup: rgba(52, 227, 155, .14);
            --siaga: #ffb020;
            --teks: #e8f0f6;
            --teks-redup: #7c8fa0;
            --mono: ui-monospace, "SF Mono", "Cascadia Mono", "Roboto Mono", Menlo, monospace;
        }

        body {
            background: var(--dasar);
            color: var(--teks);
            font-feature-settings: "ss01", "cv01";
            overflow-x: hidden;
        }

        /* Kisi halus meniru kertas EKG. */
        .kertas-ekg {
            position: fixed;
            inset: 0;
            pointer-events: none;
            background-image:
                linear-gradient(var(--vital-redup) 1px, transparent 1px),
                linear-gradient(90deg, var(--vital-redup) 1px, transparent 1px);
            background-size: 56px 56px;
            mask-image: radial-gradient(ellipse 90% 60% at 50% 0%, #000 20%, transparent 75%);
            opacity: .5;
        }

        /* Butiran halus supaya bidang gelapnya tidak terasa datar. */
        .butiran {
            position: fixed;
            inset: 0;
            pointer-events: none;
            opacity: .035;
            mix-blend-mode: overlay;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.8' numOctaves='4'/%3E%3C/filter%3E%3Crect width='200' height='200' filter='url(%23n)'/%3E%3C/svg%3E");
        }

        .jejak-ekg {
            stroke-dasharray: 1400;
            stroke-dashoffset: 1400;
            animation: sapuan 4.5s cubic-bezier(.4, 0, .2, 1) infinite;
        }

        @keyframes sapuan {
            0%   { stroke-dashoffset: 1400; }
            55%  { stroke-dashoffset: 0; }
            100% { stroke-dashoffset: -1400; }
        }

        .denyut::before {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: inherit;
            background: currentColor;
            animation: denyut 2s ease-out infinite;
        }

        @keyframes denyut {
            0%   { transform: scale(1); opacity: .55; }
            70%  { transform: scale(2.6); opacity: 0; }
            100% { opacity: 0; }
        }

        .muncul {
            opacity: 0;
            transform: translateY(14px);
            animation: muncul .7s cubic-bezier(.2, .7, .2, 1) forwards;
        }

        @keyframes muncul {
            to { opacity: 1; transform: none; }
        }

        .kartu-modul {
            transition: border-color .25s ease, transform .25s ease, background-color .25s ease;
        }

        .kartu-modul:hover {
            border-color: rgba(52, 227, 155, .45);
            background-color: #131d27;
            transform: translateY(-2px);
        }

        .angka { font-family: var(--mono); font-variant-numeric: tabular-nums; }

        .label-alat {
            font-family: var(--mono);
            font-size: .68rem;
            letter-spacing: .22em;
            text-transform: uppercase;
            color: var(--teks-redup);
        }

        @media (prefers-reduced-motion: reduce) {
            .jejak-ekg, .denyut::before, .muncul { animation: none; }
            .muncul { opacity: 1; transform: none; }
        }
    </style>
</head>
<body class="antialiased">
    <div class="kertas-ekg"></div>
    <div class="butiran"></div>

    <div class="relative max-w-6xl mx-auto px-6">

        {{-- Kepala --}}
        <header class="flex items-center justify-between py-7 muncul">
            <div class="flex items-center gap-3">
                <span class="relative inline-flex h-2.5 w-2.5 rounded-full denyut" style="color: var(--vital); background: var(--vital)"></span>
                <span class="label-alat" style="color: var(--teks)">SIMRS</span>
            </div>
            <nav class="flex items-center gap-6 text-sm">
                <a href="{{ route('display.antrian') }}" class="text-slate-400 hover:text-white transition">Display Antrian</a>
                <a href="{{ route('masuk') }}"
                   class="px-4 py-2 rounded-md text-sm font-medium transition"
                   style="background: var(--vital); color: #04120c">Masuk</a>
            </nav>
        </header>

        {{-- Hero --}}
        <section class="relative grid lg:grid-cols-[1.15fr_1fr] gap-12 items-center pt-14 pb-24">
            <div>
                <p class="label-alat muncul" style="animation-delay:.05s">Sistem Informasi Manajemen Rumah Sakit</p>

                <h1 class="mt-5 text-6xl sm:text-7xl font-semibold leading-[0.95] tracking-tight muncul" style="animation-delay:.12s">
                    Satu alur,<br>
                    dari <span style="color: var(--vital)">pendaftaran</span><br>
                    sampai kuitansi.
                </h1>

                <p class="mt-7 text-lg leading-relaxed max-w-xl muncul" style="color: var(--teks-redup); animation-delay:.2s">
                    Pasien mendaftar, mendapat nomor antrian, diperiksa perawat lalu dokter,
                    menerima resep, menebus obat di apotek, dan menyelesaikan tagihannya di kasir —
                    tercatat utuh beserta jejak siapa mengubah apa.
                </p>

                <div class="mt-9 flex flex-wrap items-center gap-4 muncul" style="animation-delay:.28s">
                    <a href="{{ route('masuk') }}"
                       class="px-6 py-3 rounded-md font-medium transition hover:brightness-110"
                       style="background: var(--vital); color: #04120c">
                        Masuk ke Sistem
                    </a>
                    <a href="{{ route('display.antrian') }}"
                       class="px-6 py-3 rounded-md font-medium border transition hover:border-slate-500"
                       style="border-color: var(--garis); color: var(--teks)">
                        Lihat Display Antrian
                    </a>
                </div>
            </div>

            {{-- Panel monitor --}}
            <div class="relative rounded-xl border p-6 muncul"
                 style="border-color: var(--garis); background: var(--panel); animation-delay:.36s">

                <div class="flex items-center justify-between">
                    <span class="label-alat">Denyut Sistem</span>
                    <span class="flex items-center gap-2 label-alat" style="color: var(--vital)">
                        <span class="relative inline-flex h-1.5 w-1.5 rounded-full denyut" style="color: var(--vital); background: var(--vital)"></span>
                        Aktif
                    </span>
                </div>

                <svg viewBox="0 0 700 120" class="w-full h-24 mt-4" fill="none" preserveAspectRatio="none" aria-hidden="true">
                    <path class="jejak-ekg"
                          d="M0,70 L60,70 L72,62 L84,70 L120,70 L132,26 L142,104 L152,52 L164,70 L230,70 L248,58 L266,70 L350,70 L362,62 L374,70 L410,70 L422,26 L432,104 L442,52 L454,70 L520,70 L538,58 L556,70 L700,70"
                          stroke="var(--vital)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>

                <dl class="mt-4 divide-y" style="border-color: var(--garis)">
                    @foreach ([
                        ['Pasien terdaftar', $angka['pasien'], null],
                        ['Kunjungan hari ini', $angka['kunjungan_hari_ini'], null],
                        ['Menunggu di poli', $angka['menunggu_poli'], 'vital'],
                        ['Menunggu di apotek', $angka['menunggu_apotek'], 'vital'],
                        ['Obat menipis', $angka['obat_menipis'], 'siaga'],
                    ] as [$label, $nilai, $warna])
                        <div class="flex items-baseline justify-between py-3" style="border-color: var(--garis)">
                            <dt class="text-sm" style="color: var(--teks-redup)">{{ $label }}</dt>
                            <dd class="angka text-2xl font-semibold"
                                style="color: {{ $nilai === null ? 'var(--teks-redup)' : ($warna ? "var(--{$warna})" : 'var(--teks)') }}">
                                {{ $nilai === null ? '—' : number_format($nilai, 0, ',', '.') }}
                            </dd>
                        </div>
                    @endforeach
                </dl>

                @if ($angka['pasien'] === null)
                    <p class="mt-3 text-xs" style="color: var(--siaga)">
                        Angka tidak terbaca — periksa koneksi database.
                    </p>
                @endif
            </div>
        </section>

        {{-- Modul --}}
        <section class="pb-24">
            <div class="flex items-end justify-between border-b pb-4" style="border-color: var(--garis)">
                <h2 class="text-2xl font-semibold tracking-tight">Modul</h2>
                <span class="label-alat">Fase 1 selesai &middot; Fase 2 berjalan</span>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-8">
                @foreach ([
                    ['Pendaftaran & Antrian', 'Pencarian pasien, kunjungan, nomor antrian per poli per hari, cetak karcis.', true],
                    ['Rekam Medis', 'Tanda vital, SOAP, diagnosa ICD-10, tindakan, dan resep — terkunci setelah kunjungan selesai.', true],
                    ['Kasir', 'Tagihan tersusun otomatis dengan tarif berbeda per penjamin, pembayaran, dan kuitansi.', true],
                    ['Farmasi', 'Stok per batch, alokasi FEFO, penyerahan obat, dan biayanya masuk ke tagihan yang sama.', false],
                    ['Rekam Medis (Berkas)', 'Penelusuran riwayat, koreksi data yang berjejak, dan rekap kunjungan harian.', true],
                    ['Audit & Hak Akses', 'Tujuh peran dijaga Policy, dan setiap perubahan data klinis meninggalkan jejak.', true],
                ] as [$judul, $isi, $selesai])
                    <article class="kartu-modul rounded-lg border p-5" style="border-color: var(--garis); background: var(--panel)">
                        <div class="flex items-center gap-2">
                            <span class="h-1.5 w-1.5 rounded-full"
                                  style="background: {{ $selesai ? 'var(--vital)' : 'var(--siaga)' }}"></span>
                            <span class="label-alat">{{ $selesai ? 'Siap pakai' : 'Sedang dibangun' }}</span>
                        </div>
                        <h3 class="mt-3 text-lg font-medium">{{ $judul }}</h3>
                        <p class="mt-2 text-sm leading-relaxed" style="color: var(--teks-redup)">{{ $isi }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        {{-- Peran --}}
        <section class="pb-24">
            <div class="flex items-end justify-between border-b pb-4" style="border-color: var(--garis)">
                <h2 class="text-2xl font-semibold tracking-tight">Peran &amp; Kewenangan</h2>
                <span class="label-alat">Tujuh peran, dijaga Policy</span>
            </div>

            <div class="mt-8 overflow-x-auto rounded-lg border" style="border-color: var(--garis); background: var(--panel)">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left" style="color: var(--teks-redup)">
                            <th class="px-5 py-3 font-medium">Peran</th>
                            <th class="px-5 py-3 font-medium">Kewenangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ([
                            ['Admisi', 'Daftar pasien, buat kunjungan, cetak karcis antrian'],
                            ['Perawat', 'Input tanda vital dan keluhan awal'],
                            ['Dokter', 'SOAP, diagnosa ICD-10, tindakan, resep, selesaikan kunjungan'],
                            ['Apoteker', 'Siapkan dan serahkan obat, terima batch, kelola stok'],
                            ['Kasir', 'Proses pembayaran dan cetak kuitansi'],
                            ['Rekam Medis', 'Telusur riwayat, koreksi data berjejak, rekap harian'],
                            ['Admin', 'Master data, kelola pengguna, penampil audit log'],
                        ] as [$peran, $wewenang])
                            <tr class="border-t" style="border-color: var(--garis)">
                                <td class="px-5 py-3 font-medium">{{ $peran }}</td>
                                <td class="px-5 py-3" style="color: var(--teks-redup)">{{ $wewenang }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if (app()->environment('local'))
                {{-- Kredensial contoh hanya muncul di lingkungan lokal. Halaman ini
                     terbuka tanpa login, jadi menampilkannya di luar lokal sama saja
                     menyerahkan akun setiap peran kepada siapa pun yang membukanya. --}}
                <div class="mt-6 rounded-lg border p-5" style="border-color: rgba(255,176,32,.35); background: rgba(255,176,32,.06)">
                    <p class="label-alat" style="color: var(--siaga)">Lingkungan lokal</p>
                    <p class="mt-2 text-sm" style="color: var(--teks-redup)">
                        Akun contoh tersedia dengan pola <span class="angka" style="color: var(--teks)">{peran}@rs.test</span>
                        dan kata sandi seragam yang disetel <span class="angka" style="color: var(--teks)">PenggunaSeeder</span>.
                        Blok ini tidak akan pernah tampil di luar lingkungan lokal.
                    </p>
                </div>
            @endif

            <p class="mt-4 text-xs" style="color: var(--teks-redup)">
                Seluruh data pada sistem ini adalah data contoh dan tidak menunjuk rumah sakit
                maupun pasien mana pun.
            </p>
        </section>

        <footer class="border-t py-8 flex flex-wrap items-center justify-between gap-4 text-sm"
                style="border-color: var(--garis); color: var(--teks-redup)">
            <span>Laravel {{ app()->version() }} &middot; Livewire &middot; MySQL &middot; dikembangkan dengan TDD</span>
            <a href="https://github.com/IrvanArif/SIMRS" class="hover:text-white transition">github.com/IrvanArif/SIMRS</a>
        </footer>
    </div>
</body>
</html>
