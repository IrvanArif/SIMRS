<div class="grid grid-cols-3 gap-6">
    <div class="bg-white p-6 rounded shadow h-fit space-y-3">
        <h2 class="font-semibold">Pengguna Baru</h2>
        <div>
            <label class="block text-sm mb-1">Nama</label>
            <input wire:model="name" class="w-full border rounded px-3 py-2">
            @error('name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm mb-1">Email</label>
            <input wire:model="email" type="email" class="w-full border rounded px-3 py-2">
            @error('email') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm mb-1">Kata Sandi</label>
            <input wire:model="password" type="password" class="w-full border rounded px-3 py-2">
            @error('password') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm mb-1">Peran</label>
            <select wire:model="peran" class="w-full border rounded px-3 py-2">
                <option value="">— pilih peran —</option>
                @foreach ($daftarPeran as $peran)
                    <option value="{{ $peran->value }}">{{ $peran->value }}</option>
                @endforeach
            </select>
            @error('peran') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm mb-1">Kaitkan ke Dokter (opsional)</label>
            <select wire:model="dokter_id" class="w-full border rounded px-3 py-2">
                <option value="">— tidak —</option>
                @foreach ($daftarDokter as $dokter)
                    <option value="{{ $dokter->id }}">{{ $dokter->nama }}</option>
                @endforeach
            </select>
        </div>
        <button wire:click="simpan" class="bg-blue-600 text-white px-4 py-2 rounded">Simpan</button>
    </div>

    <div class="col-span-2">
        <h1 class="text-xl font-semibold mb-4">Kelola Pengguna</h1>
        @if (session('sukses'))
            <div class="mb-4 bg-green-50 text-green-700 px-4 py-2 rounded">{{ session('sukses') }}</div>
        @endif
        <div class="bg-white rounded shadow overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr><th class="px-4 py-2">Nama</th><th class="px-4 py-2">Email</th><th class="px-4 py-2">Peran</th><th class="px-4 py-2">Status</th><th class="px-4 py-2"></th></tr>
                </thead>
                <tbody>
                    @foreach ($daftarUser as $user)
                        <tr class="border-t">
                            <td class="px-4 py-2">{{ $user->name }}</td>
                            <td class="px-4 py-2">{{ $user->email }}</td>
                            <td class="px-4 py-2">{{ $user->getRoleNames()->implode(', ') }}</td>
                            <td class="px-4 py-2">{{ $user->aktif ? 'Aktif' : 'Nonaktif' }}</td>
                            <td class="px-4 py-2 text-right">
                                @if ($user->aktif)
                                    <button wire:click="nonaktifkan({{ $user->id }})" class="text-red-600">Nonaktifkan</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $daftarUser->links() }}</div>
    </div>
</div>
