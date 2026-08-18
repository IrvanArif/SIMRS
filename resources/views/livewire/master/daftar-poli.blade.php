<div class="grid grid-cols-3 gap-6">
    <div class="bg-white p-6 rounded shadow h-fit space-y-3">
        <h2 class="font-semibold">{{ $poliId ? 'Ubah Poli' : 'Poli Baru' }}</h2>
        <div>
            <label class="block text-sm mb-1">Kode</label>
            <input wire:model="kode" maxlength="10" class="w-full border rounded px-3 py-2">
            @error('kode') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm mb-1">Nama</label>
            <input wire:model="nama" class="w-full border rounded px-3 py-2">
            @error('nama') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm mb-1">Lokasi</label>
            <input wire:model="lokasi" class="w-full border rounded px-3 py-2">
        </div>
        <button wire:click="simpan" class="bg-blue-600 text-white px-4 py-2 rounded">Simpan</button>
    </div>

    <div class="col-span-2">
        <h1 class="text-xl font-semibold mb-4">Master Poli</h1>
        @if (session('sukses'))
            <div class="mb-4 bg-green-50 text-green-700 px-4 py-2 rounded">{{ session('sukses') }}</div>
        @endif
        <div class="bg-white rounded shadow overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr><th class="px-4 py-2">Kode</th><th class="px-4 py-2">Nama</th><th class="px-4 py-2">Lokasi</th><th class="px-4 py-2"></th></tr>
                </thead>
                <tbody>
                    @foreach ($daftarPoli as $poli)
                        <tr class="border-t">
                            <td class="px-4 py-2">{{ $poli->kode }}</td>
                            <td class="px-4 py-2">{{ $poli->nama }}</td>
                            <td class="px-4 py-2">{{ $poli->lokasi }}</td>
                            <td class="px-4 py-2 text-right">
                                <button wire:click="sunting({{ $poli->id }})" class="text-blue-600">Ubah</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $daftarPoli->links() }}</div>
    </div>
</div>
