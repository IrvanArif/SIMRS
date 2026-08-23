<div>
    <div class="flex items-end justify-between mb-4">
        <h1 class="text-xl font-semibold">Antrean Resep</h1>
        <select wire:model.live="status" class="border rounded px-3 py-2 text-sm">
            @foreach ($pilihanStatus as $pilihan)
                <option value="{{ $pilihan->value }}">{{ $pilihan->label() }}</option>
            @endforeach
        </select>
    </div>

    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="px-4 py-2">No. Resep</th>
                    <th class="px-4 py-2">Pasien</th>
                    <th class="px-4 py-2">Poli</th>
                    <th class="px-4 py-2">Penjamin</th>
                    <th class="px-4 py-2">Item</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($daftarResep as $resep)
                    <tr class="border-t">
                        <td class="px-4 py-2 font-medium">{{ $resep->no_resep }}</td>
                        <td class="px-4 py-2">{{ $resep->kunjungan->pasien->nama }}</td>
                        <td class="px-4 py-2">{{ $resep->kunjungan->poli->nama }}</td>
                        <td class="px-4 py-2">{{ $resep->kunjungan->penjamin->kode }}</td>
                        <td class="px-4 py-2">{{ $resep->detail->count() }} obat</td>
                        <td class="px-4 py-2 text-right space-x-3">
                            <a href="{{ route('apotek.siapkan', $resep) }}" class="text-blue-600">Siapkan</a>
                            <a href="{{ route('apotek.serahkan', $resep) }}" class="text-blue-600">Serahkan</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-slate-500">Tidak ada resep pada status ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $daftarResep->links() }}</div>
</div>
