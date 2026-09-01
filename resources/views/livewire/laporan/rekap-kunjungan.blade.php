<div class="space-y-6">
    <h1 class="text-xl font-semibold">Rekapitulasi Kunjungan per Poli</h1>

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
                        <th class="px-4 py-2">Poli</th>
                        <th class="px-4 py-2 text-right">Rawat Jalan</th>
                        <th class="px-4 py-2 text-right">Rawat Inap</th>
                        <th class="px-4 py-2 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($baris as $satu)
                        <tr class="border-t">
                            <td class="px-4 py-2 font-medium">{{ $satu['poli'] }}</td>
                            <td class="px-4 py-2 text-right">{{ $satu['rawat_jalan'] }}</td>
                            <td class="px-4 py-2 text-right">{{ $satu['rawat_inap'] }}</td>
                            <td class="px-4 py-2 text-right font-medium">{{ $satu['total'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">Tidak ada kunjungan pada periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
