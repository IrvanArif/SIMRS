<div class="grid lg:grid-cols-2 gap-6">
    <div>
        <h2 class="text-lg font-semibold mb-3">Stok Menipis</h2>
        <div class="bg-white rounded shadow overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-4 py-2">Obat</th>
                        <th class="px-4 py-2 text-right">Tersedia</th>
                        <th class="px-4 py-2 text-right">Minimum</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($obatMenipis as $obat)
                        <tr class="border-t">
                            <td class="px-4 py-2">{{ $obat->nama }}</td>
                            <td class="px-4 py-2 text-right text-amber-600 font-medium">{{ $obat->stokTersedia() }}</td>
                            <td class="px-4 py-2 text-right">{{ $obat->stok_minimum }}</td>
                            <td class="px-4 py-2 text-right">
                                <a href="{{ route('apotek.kartu-stok', $obat) }}" class="text-blue-600">Kartu Stok</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">Semua stok aman.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>
        <h2 class="text-lg font-semibold mb-3">Mendekati Kedaluwarsa (3 bulan)</h2>
        <div class="bg-white rounded shadow overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-4 py-2">Obat</th>
                        <th class="px-4 py-2">Batch</th>
                        <th class="px-4 py-2">Kedaluwarsa</th>
                        <th class="px-4 py-2 text-right">Sisa</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($mendekatiKedaluwarsa as $batch)
                        <tr class="border-t">
                            <td class="px-4 py-2">{{ $batch->obat->nama }}</td>
                            <td class="px-4 py-2">{{ $batch->no_batch }}</td>
                            <td class="px-4 py-2 {{ $batch->kedaluwarsa() ? 'text-red-600 font-medium' : 'text-amber-600' }}">
                                {{ $batch->tanggal_kedaluwarsa->format('d/m/Y') }}
                                {{ $batch->kedaluwarsa() ? '(kedaluwarsa)' : '' }}
                            </td>
                            <td class="px-4 py-2 text-right">{{ $batch->jumlah_tersisa }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">Tidak ada yang mendekati kedaluwarsa.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
