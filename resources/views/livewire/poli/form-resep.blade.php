<div class="max-w-3xl">
    <h1 class="text-xl font-semibold mb-1">Resep</h1>
    <p class="text-sm text-slate-600 mb-4">
        {{ $kunjungan->pasien->nama }} — No. RM {{ $kunjungan->pasien->no_rm }}
    </p>

    @if (session('sukses'))
        <div class="mb-4 bg-green-50 text-green-700 px-4 py-2 rounded">{{ session('sukses') }}</div>
    @endif

    @error('item') <div class="mb-4 bg-red-50 text-red-700 px-4 py-2 rounded">{{ $message }}</div> @enderror

    <div class="bg-white p-6 rounded shadow space-y-3">
        @foreach ($item as $indeks => $baris)
            <div class="flex gap-2 items-start">
                <select wire:model="item.{{ $indeks }}.obat_id" class="flex-1 border rounded px-3 py-2">
                    <option value="">— pilih obat —</option>
                    @foreach ($daftarObat as $obat)
                        <option value="{{ $obat->id }}">{{ $obat->nama }} ({{ $obat->satuan }})</option>
                    @endforeach
                </select>
                <input wire:model="item.{{ $indeks }}.jumlah" type="number" min="1"
                       class="w-20 border rounded px-3 py-2">
                <input wire:model="item.{{ $indeks }}.aturan_pakai" placeholder="3x1 sesudah makan"
                       class="flex-1 border rounded px-3 py-2">
                <button wire:click="hapusBaris({{ $indeks }})" class="text-red-600 px-2 py-2">Hapus</button>
            </div>
        @endforeach

        <div class="flex gap-3">
            <button wire:click="tambahBaris" class="bg-slate-200 px-4 py-2 rounded">Tambah Obat</button>
            <button wire:click="simpan" class="bg-blue-600 text-white px-4 py-2 rounded">Simpan Resep</button>
        </div>
    </div>
</div>
