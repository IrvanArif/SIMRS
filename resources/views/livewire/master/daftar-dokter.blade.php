<div class="grid grid-cols-3 gap-6">
    <div class="bg-white p-6 rounded shadow h-fit space-y-3">
        <h2 class="font-semibold">{{ $dokterId ? 'Ubah Dokter' : 'Dokter Baru' }}</h2>
        <div>
            <label class="block text-sm mb-1">NIP</label>
            <input wire:model="nip" class="w-full border rounded px-3 py-2">
            @error('nip') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm mb-1">Nama</label>
            <input wire:model="nama" class="w-full border rounded px-3 py-2">
            @error('nama') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm mb-1">Spesialisasi</label>
            <input wire:model="spesialisasi" class="w-full border rounded px-3 py-2">
        </div>
        <div>
            <label class="block text-sm mb-1">No. SIP</label>
            <input wire:model="no_sip" class="w-full border rounded px-3 py-2">
        </div>
        <div>
            <label class="block text-sm mb-1">Poli</label>
            <select wire:model="poli_id" class="w-full border rounded px-3 py-2">
                <option value="">— pilih poli —</option>
                @foreach ($daftarPoli as $poli)
                    <option value="{{ $poli->id }}">{{ $poli->nama }}</option>
                @endforeach
            </select>
            @error('poli_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <button wire:click="simpan" class="bg-blue-600 text-white px-4 py-2 rounded">Simpan</button>
    </div>

    <div class="col-span-2">
        <h1 class="text-xl font-semibold mb-4">Master Dokter</h1>
        @if (session('sukses'))
            <div class="mb-4 bg-green-50 text-green-700 px-4 py-2 rounded">{{ session('sukses') }}</div>
        @endif
        <div class="bg-white rounded shadow overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr><th class="px-4 py-2">NIP</th><th class="px-4 py-2">Nama</th><th class="px-4 py-2">Poli</th><th class="px-4 py-2"></th></tr>
                </thead>
                <tbody>
                    @foreach ($daftarDokter as $dokter)
                        <tr class="border-t">
                            <td class="px-4 py-2">{{ $dokter->nip }}</td>
                            <td class="px-4 py-2">{{ $dokter->nama }}</td>
                            <td class="px-4 py-2">{{ $dokter->poli->nama }}</td>
                            <td class="px-4 py-2 text-right">
                                <button wire:click="sunting({{ $dokter->id }})" class="text-blue-600">Ubah</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $daftarDokter->links() }}</div>
    </div>
</div>
