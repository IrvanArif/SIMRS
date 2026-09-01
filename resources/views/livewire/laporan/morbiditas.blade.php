<div class="space-y-6">
    <h1 class="text-xl font-semibold">Sepuluh Besar Penyakit</h1>

    <div class="bg-white rounded shadow p-6 flex flex-wrap gap-4 items-end">
        <div>
            <label class="block text-sm mb-1">Tanggal awal</label>
            <input type="date" wire:model.live="awal" class="border rounded px-3 py-2">
        </div>
        <div>
            <label class="block text-sm mb-1">Tanggal akhir</label>
            <input type="date" wire:model.live="akhir" class="border rounded px-3 py-2">
        </div>
        <div>
            <label class="block text-sm mb-1">Jenis pelayanan</label>
            <select wire:model.live="jenis" class="border rounded px-3 py-2">
                <option value="">Semua</option>
                <option value="jalan">Rawat Jalan</option>
                <option value="inap">Rawat Inap</option>
            </select>
        </div>
    </div>

    @if ($galat)
        <div class="bg-red-50 text-red-700 px-4 py-3 rounded">{{ $galat }}</div>
    @else
        <div class="bg-white rounded shadow overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-4 py-2">#</th>
                        <th class="px-4 py-2">Kode ICD-10</th>
                        <th class="px-4 py-2">Diagnosa</th>
                        <th class="px-4 py-2 text-right">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($baris as $urutan => $satu)
                        <tr class="border-t">
                            <td class="px-4 py-2">{{ $urutan + 1 }}</td>
                            <td class="px-4 py-2 font-medium">{{ $satu['kode'] }}</td>
                            <td class="px-4 py-2">{{ $satu['nama'] }}</td>
                            <td class="px-4 py-2 text-right">{{ $satu['jumlah'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">Tidak ada diagnosa pada periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="text-xs text-slate-500">
            Hanya diagnosa primer yang dihitung; diagnosa sekunder adalah penyerta.
        </p>
    @endif
</div>
