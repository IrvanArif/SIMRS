<div class="space-y-6">
    <h1 class="text-xl font-semibold">Pendapatan per Penjamin</h1>

    <div class="bg-white rounded shadow p-6 flex flex-wrap gap-4 items-end">
        <div>
            <label class="block text-sm mb-1">Tanggal awal</label>
            <input type="date" wire:model.live="awal" class="border rounded px-3 py-2">
        </div>
        <div>
            <label class="block text-sm mb-1">Tanggal akhir</label>
            <input type="date" wire:model.live="akhir" class="border rounded px-3 py-2">
        </div>
    </div>

    @if ($galat)
        <div class="bg-red-50 text-red-700 px-4 py-3 rounded">{{ $galat }}</div>
    @else
        <div class="bg-white rounded shadow overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-4 py-2">Penjamin</th>
                        <th class="px-4 py-2 text-right">Lunas</th>
                        <th class="px-4 py-2 text-right">Menunggu Kasir</th>
                        <th class="px-4 py-2 text-right">Ditanggung Penjamin</th>
                        <th class="px-4 py-2 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($baris as $satu)
                        <tr class="border-t">
                            <td class="px-4 py-2 font-medium">{{ $satu['penjamin'] }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($satu['lunas'], 0, ',', '.') }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($satu['menunggu'], 0, ',', '.') }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($satu['ditanggung_penjamin'], 0, ',', '.') }}</td>
                            <td class="px-4 py-2 text-right font-medium">{{ number_format($satu['total'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">Tidak ada tagihan pada periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="text-xs text-slate-500">
            Yang ditanggung penjamin bukan uang yang sudah diterima — ia piutang sampai klaimnya dibayar.
        </p>
    @endif
</div>
