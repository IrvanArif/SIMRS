<div class="grid grid-cols-3 gap-6">
    <div class="bg-white p-6 rounded shadow h-fit space-y-3">
        <h2 class="font-semibold">Harga Obat</h2>

        <div>
            <label class="block text-sm mb-1">Obat</label>
            <select wire:model="obat_id" class="w-full border rounded px-3 py-2">
                <option value="">— pilih —</option>
                @foreach ($daftarObat as $obat)
                    <option value="{{ $obat->id }}">{{ $obat->nama }}</option>
                @endforeach
            </select>
            @error('obat_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm mb-1">Penjamin</label>
            <select wire:model="penjamin_id" class="w-full border rounded px-3 py-2">
                <option value="">— pilih —</option>
                @foreach ($daftarPenjamin as $penjamin)
                    <option value="{{ $penjamin->id }}">{{ $penjamin->nama }}</option>
                @endforeach
            </select>
            @error('penjamin_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm mb-1">Harga Jual (Rp)</label>
            <input wire:model="harga" type="number" class="w-full border rounded px-3 py-2">
            @error('harga') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm mb-1">Berlaku Mulai</label>
            <input wire:model="berlaku_mulai" type="date" class="w-full border rounded px-3 py-2">
        </div>

        <button wire:click="simpan" class="bg-blue-600 text-white px-4 py-2 rounded">Simpan</button>
    </div>

    <div class="col-span-2">
        <h1 class="text-xl font-semibold mb-4">Master Harga Obat</h1>

        @if (session('sukses'))
            <div class="mb-4 bg-green-50 text-green-700 px-4 py-2 rounded">{{ session('sukses') }}</div>
        @endif

        <div class="bg-white rounded shadow overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-4 py-2">Obat</th>
                        <th class="px-4 py-2">Penjamin</th>
                        <th class="px-4 py-2 text-right">Harga</th>
                        <th class="px-4 py-2">Berlaku</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($daftarHarga as $baris)
                        <tr class="border-t">
                            <td class="px-4 py-2">{{ $baris->obat->nama }}</td>
                            <td class="px-4 py-2">{{ $baris->penjamin->kode }}</td>
                            <td class="px-4 py-2 text-right">Rp {{ number_format($baris->harga, 0, ',', '.') }}</td>
                            <td class="px-4 py-2">{{ $baris->berlaku_mulai->format('d/m/Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $daftarHarga->links() }}</div>
    </div>
</div>
