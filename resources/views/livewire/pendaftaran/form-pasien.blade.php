<div class="max-w-2xl">
    <h1 class="text-xl font-semibold mb-4">
        {{ $pasien ? 'Ubah Data Pasien' : 'Pendaftaran Pasien Baru' }}
    </h1>

    <div class="bg-white p-6 rounded shadow space-y-4">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm mb-1">NIK</label>
                <input wire:model="nik" maxlength="16" class="w-full border rounded px-3 py-2">
                @error('nik') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm mb-1">Nama Lengkap</label>
                <input wire:model="nama" class="w-full border rounded px-3 py-2">
                @error('nama') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm mb-1">Tempat Lahir</label>
                <input wire:model="tempat_lahir" class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label class="block text-sm mb-1">Tanggal Lahir</label>
                <input wire:model="tanggal_lahir" type="date" class="w-full border rounded px-3 py-2">
                @error('tanggal_lahir') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm mb-1">Jenis Kelamin</label>
                <select wire:model="jenis_kelamin" class="w-full border rounded px-3 py-2">
                    <option value="">— pilih —</option>
                    <option value="L">Laki-laki</option>
                    <option value="P">Perempuan</option>
                </select>
                @error('jenis_kelamin') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm mb-1">No. HP</label>
                <input wire:model="no_hp" class="w-full border rounded px-3 py-2">
            </div>
        </div>

        <div>
            <label class="block text-sm mb-1">Alamat</label>
            <input wire:model="alamat" class="w-full border rounded px-3 py-2">
            @error('alamat') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-4 gap-4">
            <div>
                <label class="block text-sm mb-1">RT</label>
                <input wire:model="rt" maxlength="3" class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label class="block text-sm mb-1">RW</label>
                <input wire:model="rw" maxlength="3" class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label class="block text-sm mb-1">Kelurahan</label>
                <input wire:model="kelurahan" class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label class="block text-sm mb-1">Kecamatan</label>
                <input wire:model="kecamatan" class="w-full border rounded px-3 py-2">
            </div>
        </div>

        <button wire:click="simpan" class="bg-blue-600 text-white px-4 py-2 rounded">Simpan</button>
    </div>
</div>
