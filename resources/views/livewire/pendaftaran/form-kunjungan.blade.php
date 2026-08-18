<div class="max-w-xl">
    <h1 class="text-xl font-semibold mb-1">Buat Kunjungan</h1>
    <p class="text-sm text-slate-600 mb-4">
        {{ $pasien->nama }} — No. RM {{ $pasien->no_rm }}
    </p>

    <div class="bg-white p-6 rounded shadow space-y-4">
        <div>
            <label class="block text-sm mb-1">Poli</label>
            <select wire:model.live="poli_id" class="w-full border rounded px-3 py-2">
                <option value="">— pilih poli —</option>
                @foreach ($daftarPoli as $poli)
                    <option value="{{ $poli->id }}">{{ $poli->nama }}</option>
                @endforeach
            </select>
            @error('poli_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm mb-1">Dokter</label>
            <select wire:model="dokter_id" class="w-full border rounded px-3 py-2">
                <option value="">— pilih dokter —</option>
                @foreach ($daftarDokter as $dokter)
                    <option value="{{ $dokter->id }}">{{ $dokter->nama }}</option>
                @endforeach
            </select>
            @error('dokter_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm mb-1">Penjamin</label>
            <select wire:model.live="penjamin_id" class="w-full border rounded px-3 py-2">
                <option value="">— pilih penjamin —</option>
                @foreach ($daftarPenjamin as $penjamin)
                    <option value="{{ $penjamin->id }}">{{ $penjamin->nama }} ({{ $penjamin->kode }})</option>
                @endforeach
            </select>
            @error('penjamin_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm mb-1">Nomor Kartu Penjamin</label>
            <input wire:model="no_kartu_penjamin" class="w-full border rounded px-3 py-2"
                   placeholder="Wajib untuk pasien BPJS">
            @error('no_kartu_penjamin') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        @error('pasien_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

        <button wire:click="simpan" class="bg-blue-600 text-white px-4 py-2 rounded">
            Daftarkan & Cetak Karcis
        </button>
    </div>
</div>
