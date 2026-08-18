<div class="max-w-2xl">
    <h1 class="text-xl font-semibold mb-1">Tanda Vital</h1>
    <p class="text-sm text-slate-600 mb-4">
        {{ $kunjungan->pasien->nama }} — No. RM {{ $kunjungan->pasien->no_rm }} —
        {{ $kunjungan->poli->nama }}
    </p>

    @if (session('sukses'))
        <div class="mb-4 bg-green-50 text-green-700 px-4 py-2 rounded">{{ session('sukses') }}</div>
    @endif

    @error('kunjungan') <div class="mb-4 bg-red-50 text-red-700 px-4 py-2 rounded">{{ $message }}</div> @enderror

    <div class="bg-white p-6 rounded shadow grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm mb-1">Sistolik (mmHg)</label>
            <input wire:model="sistolik" type="number" class="w-full border rounded px-3 py-2">
            @error('sistolik') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm mb-1">Diastolik (mmHg)</label>
            <input wire:model="diastolik" type="number" class="w-full border rounded px-3 py-2">
            @error('diastolik') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm mb-1">Nadi (x/menit)</label>
            <input wire:model="nadi" type="number" class="w-full border rounded px-3 py-2">
            @error('nadi') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm mb-1">Suhu (°C)</label>
            <input wire:model="suhu" type="number" step="0.1" class="w-full border rounded px-3 py-2">
            @error('suhu') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm mb-1">Respirasi (x/menit)</label>
            <input wire:model="respirasi" type="number" class="w-full border rounded px-3 py-2">
            @error('respirasi') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm mb-1">Berat Badan (kg)</label>
            <input wire:model="berat_badan" type="number" step="0.1" class="w-full border rounded px-3 py-2">
            @error('berat_badan') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm mb-1">Tinggi Badan (cm)</label>
            <input wire:model="tinggi_badan" type="number" class="w-full border rounded px-3 py-2">
            @error('tinggi_badan') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm mb-1">Alergi</label>
            <input wire:model="alergi" class="w-full border rounded px-3 py-2" placeholder="Kosongkan bila tidak ada">
        </div>
        <div class="col-span-2">
            <label class="block text-sm mb-1">Keluhan Awal</label>
            <textarea wire:model="keluhan_awal" rows="3" class="w-full border rounded px-3 py-2"></textarea>
            @error('keluhan_awal') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="col-span-2">
            <button wire:click="simpan" class="bg-blue-600 text-white px-4 py-2 rounded">Simpan</button>
        </div>
    </div>
</div>
