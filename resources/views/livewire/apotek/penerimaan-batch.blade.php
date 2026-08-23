<div class="grid grid-cols-3 gap-6">
    <div class="bg-white p-6 rounded shadow h-fit space-y-3">
        <h2 class="font-semibold">Terima Batch Obat</h2>

        <div>
            <label class="block text-sm mb-1">Obat</label>
            <select wire:model="obat_id" class="w-full border rounded px-3 py-2">
                <option value="">— pilih obat —</option>
                @foreach ($daftarObat as $obat)
                    <option value="{{ $obat->id }}">{{ $obat->nama }}</option>
                @endforeach
            </select>
            @error('obat_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm mb-1">Nomor Batch</label>
            <input wire:model="no_batch" class="w-full border rounded px-3 py-2">
            @error('no_batch') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm mb-1">Tanggal Kedaluwarsa</label>
            <input wire:model="tanggal_kedaluwarsa" type="date" class="w-full border rounded px-3 py-2">
            @error('tanggal_kedaluwarsa') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-sm mb-1">Jumlah</label>
                <input wire:model="jumlah" type="number" min="1" class="w-full border rounded px-3 py-2">
                @error('jumlah') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm mb-1">Harga Beli (Rp)</label>
                <input wire:model="harga_beli" type="number" min="0" class="w-full border rounded px-3 py-2">
                @error('harga_beli') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <button wire:click="simpan" class="bg-blue-600 text-white px-4 py-2 rounded">Terima</button>
    </div>

    <div class="col-span-2">
        <h1 class="text-xl font-semibold mb-4">Penerimaan Terakhir</h1>

        @if (session('sukses'))
            <div class="mb-4 bg-green-50 text-green-700 px-4 py-2 rounded">{{ session('sukses') }}</div>
        @endif

        <div class="bg-white rounded shadow overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-4 py-2">Obat</th>
                        <th class="px-4 py-2">Batch</th>
                        <th class="px-4 py-2">Kedaluwarsa</th>
                        <th class="px-4 py-2 text-right">Sisa</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($batchTerbaru as $batch)
                        <tr class="border-t">
                            <td class="px-4 py-2">{{ $batch->obat->nama }}</td>
                            <td class="px-4 py-2">{{ $batch->no_batch }}</td>
                            <td class="px-4 py-2">{{ $batch->tanggal_kedaluwarsa->format('d/m/Y') }}</td>
                            <td class="px-4 py-2 text-right">{{ $batch->jumlah_tersisa }}</td>
                            <td class="px-4 py-2 text-right">
                                <a href="{{ route('apotek.kartu-stok', $batch->obat) }}" class="text-blue-600">Kartu Stok</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">Belum ada penerimaan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
