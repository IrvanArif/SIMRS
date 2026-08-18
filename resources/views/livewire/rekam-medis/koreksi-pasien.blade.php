<div class="max-w-2xl">
    <h1 class="text-xl font-semibold mb-1">Koreksi Data Pasien</h1>
    <p class="text-sm text-slate-600 mb-4">No. RM {{ $pasien->no_rm }}</p>

    @if (session('sukses'))
        <div class="mb-4 bg-green-50 text-green-700 px-4 py-2 rounded">{{ session('sukses') }}</div>
    @endif

    <div class="bg-white p-6 rounded shadow space-y-4">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm mb-1">NIK</label>
                <input wire:model="nik" maxlength="16" class="w-full border rounded px-3 py-2">
                @error('nik') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm mb-1">Nama</label>
                <input wire:model="nama" class="w-full border rounded px-3 py-2">
                @error('nama') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm mb-1">Tanggal Lahir</label>
                <input wire:model="tanggal_lahir" type="date" class="w-full border rounded px-3 py-2">
                @error('tanggal_lahir') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm mb-1">Jenis Kelamin</label>
                <select wire:model="jenis_kelamin" class="w-full border rounded px-3 py-2">
                    <option value="L">Laki-laki</option>
                    <option value="P">Perempuan</option>
                </select>
            </div>
            <div class="col-span-2">
                <label class="block text-sm mb-1">Alamat</label>
                <input wire:model="alamat" class="w-full border rounded px-3 py-2">
                @error('alamat') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm mb-1">No. HP</label>
                <input wire:model="no_hp" class="w-full border rounded px-3 py-2">
            </div>
        </div>

        <div class="border-t pt-4">
            <label class="block text-sm mb-1">Alasan Koreksi (wajib, tercatat di audit log)</label>
            <input wire:model="alasan" class="w-full border rounded px-3 py-2"
                   placeholder="Contoh: salah ketik nama saat pendaftaran">
            @error('alasan') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <button wire:click="simpan" class="bg-blue-600 text-white px-4 py-2 rounded">Simpan Koreksi</button>
    </div>
</div>
