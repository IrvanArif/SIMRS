<div class="space-y-6">
    <h1 class="text-xl font-semibold">Master Ruang dan Bed</h1>

    @forelse ($daftarRuang as $ruang)
        <div class="bg-white rounded shadow p-6">
            <h2 class="font-semibold">
                {{ $ruang->kode }} — {{ $ruang->nama }}
                <span class="text-sm font-normal text-slate-500">{{ $ruang->lantai }}</span>
                @unless ($ruang->aktif) <span class="text-sm text-red-600">(nonaktif)</span> @endunless
            </h2>

            <table class="w-full text-sm mt-3">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-3 py-2">Nomor</th>
                        <th class="px-3 py-2">Kelas</th>
                        <th class="px-3 py-2">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ruang->bed as $bed)
                        <tr class="border-t">
                            <td class="px-3 py-2 font-medium">{{ $bed->nomor }}</td>
                            <td class="px-3 py-2">{{ $bed->kelas->nama }}</td>
                            <td class="px-3 py-2">
                                @if (! $bed->aktif) Nonaktif
                                @elseif ($bed->terisi()) Terisi
                                @else Kosong
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <p class="text-sm text-slate-500">Belum ada ruang rawat inap.</p>
    @endforelse
</div>
