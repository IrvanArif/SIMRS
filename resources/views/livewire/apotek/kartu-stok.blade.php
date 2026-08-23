<div>
    <h1 class="text-xl font-semibold mb-1">Kartu Stok — {{ $obat->nama }}</h1>
    <p class="text-sm text-slate-600 mb-4">
        Stok layak pakai: <strong>{{ $obat->stokTersedia() }} {{ $obat->satuan }}</strong>
        &middot; stok minimum {{ $obat->stok_minimum }}
    </p>

    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="px-4 py-2">Waktu</th>
                    <th class="px-4 py-2">Jenis</th>
                    <th class="px-4 py-2">Batch</th>
                    <th class="px-4 py-2 text-right">Jumlah</th>
                    <th class="px-4 py-2 text-right">Sisa</th>
                    <th class="px-4 py-2">Petugas</th>
                    <th class="px-4 py-2">Catatan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($mutasi as $baris)
                    <tr class="border-t">
                        <td class="px-4 py-2 whitespace-nowrap">{{ $baris->created_at?->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-2">{{ $baris->jenis->value }}</td>
                        <td class="px-4 py-2">{{ $baris->batch?->no_batch ?? '—' }}</td>
                        <td class="px-4 py-2 text-right {{ $baris->jumlah < 0 ? 'text-red-600' : 'text-green-700' }}">
                            {{ $baris->jumlah > 0 ? '+' : '' }}{{ $baris->jumlah }}
                        </td>
                        <td class="px-4 py-2 text-right">{{ $baris->stok_sesudah }}</td>
                        <td class="px-4 py-2">{{ $baris->petugas?->name ?? 'Sistem' }}</td>
                        <td class="px-4 py-2 text-slate-500">{{ $baris->catatan }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-slate-500">Belum ada mutasi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $mutasi->links() }}</div>
</div>
