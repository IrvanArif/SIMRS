<div class="space-y-6">
    <h1 class="text-xl font-semibold">Papan Bed Rawat Inap</h1>

    @if ($menungguPenempatan->isNotEmpty())
        <div class="bg-white rounded shadow p-6">
            <h2 class="font-semibold mb-3">Menunggu Penempatan</h2>
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-3 py-2">No. Rawat Inap</th>
                        <th class="px-3 py-2">Pasien</th>
                        <th class="px-3 py-2">Kelas Diminta</th>
                        <th class="px-3 py-2">Indikasi</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($menungguPenempatan as $rawatInap)
                        <tr class="border-t">
                            <td class="px-3 py-2 font-medium">{{ $rawatInap->no_rawat_inap }}</td>
                            <td class="px-3 py-2">{{ $rawatInap->kunjungan->pasien->nama }}</td>
                            <td class="px-3 py-2">{{ $rawatInap->kelasDiminta->nama }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $rawatInap->indikasi }}</td>
                            <td class="px-3 py-2 text-right">
                                @can('tempatkan', $rawatInap)
                                    <a href="{{ route('rawat-inap.tempatkan', $rawatInap->id) }}"
                                       class="text-blue-600">Tempatkan</a>
                                @else
                                    <span class="text-slate-500">Menunggu admisi</span>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @foreach ($daftarRuang as $ruang)
        <div class="bg-white rounded shadow p-6">
            <h2 class="font-semibold">{{ $ruang->nama }}
                <span class="text-sm font-normal text-slate-500">{{ $ruang->lantai }}</span>
            </h2>

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3 mt-3">
                @forelse ($ruang->bed as $bed)
                    @php $penghuni = $bed->penghuni; @endphp
                    <div class="border rounded p-3 {{ $penghuni ? 'bg-amber-50 border-amber-200' : 'bg-slate-50' }}">
                        <div class="flex items-baseline justify-between">
                            <span class="font-medium">{{ $bed->nomor }}</span>
                            <span class="text-xs text-slate-500">{{ $bed->kelas->nama }}</span>
                        </div>

                        @if ($penghuni)
                            <p class="text-sm mt-1">{{ $penghuni->kunjungan->pasien->nama }}</p>
                            <p class="text-xs text-slate-500">{{ $penghuni->no_rawat_inap }}</p>

                            {{-- Tautannya mengikuti kewenangan pembaca. Papan ini adalah
                                 satu-satunya daftar pasien rawat inap, jadi tanpa tautan
                                 di sini layar perawatan dan pemulangan tak terjangkau. --}}
                            <div class="mt-2 space-x-3 text-sm">
                                @can('rawat', $penghuni)
                                    <a href="{{ route('rawat-inap.rawat', $penghuni->id) }}"
                                       class="text-blue-600">Perawatan</a>
                                @endcan
                                @can('pulangkan', $penghuni)
                                    <a href="{{ route('rawat-inap.pulangkan', $penghuni->id) }}"
                                       class="text-blue-600">Pulangkan</a>
                                @endcan
                            </div>
                        @elseif (! $bed->aktif)
                            <p class="text-sm text-slate-400 mt-1">Nonaktif</p>
                        @else
                            <p class="text-sm text-green-700 mt-1">Kosong</p>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Belum ada bed di ruang ini.</p>
                @endforelse
            </div>
        </div>
    @endforeach
</div>
