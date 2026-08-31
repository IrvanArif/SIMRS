<div class="max-w-xl">
    <h1 class="text-xl font-semibold mb-1">Penempatan Bed — {{ $rawatInap->no_rawat_inap }}</h1>
    <p class="text-sm text-slate-600 mb-4">
        {{ $rawatInap->kunjungan->pasien->nama }} —
        No. RM {{ $rawatInap->kunjungan->pasien->no_rm }} —
        diminta {{ $rawatInap->kelasDiminta->nama }}
    </p>

    @error('penempatan')
        <div class="mb-4 bg-red-50 text-red-700 px-4 py-3 rounded">{{ $message }}</div>
    @enderror

    <div class="bg-white p-6 rounded shadow space-y-4">
        <div>
            <p class="text-xs uppercase tracking-wide text-slate-500">Indikasi rawat</p>
            <p class="text-sm">{{ $rawatInap->indikasi }}</p>
        </div>

        <div>
            <label class="block text-sm mb-1">Bed tersedia</label>
            <select wire:model="bed_id" class="w-full border rounded px-3 py-2">
                <option value="">— pilih bed —</option>
                @foreach ($bedTersedia as $bed)
                    <option value="{{ $bed->id }}">
                        {{ $bed->ruang->nama }} {{ $bed->nomor }} — {{ $bed->kelas->nama }}
                        @if ($bed->kelas_kamar_id === $rawatInap->kelas_diminta_id) (sesuai permintaan) @endif
                    </option>
                @endforeach
            </select>
            @error('bed_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror

            @if ($bedTersedia->isEmpty())
                <p class="text-sm text-amber-700 mt-2">Tidak ada bed kosong saat ini.</p>
            @endif
        </div>

        <div class="flex gap-3">
            <button wire:click="tempatkan" class="bg-green-600 text-white px-4 py-2 rounded">
                Tempatkan Pasien
            </button>
            <a href="{{ route('rawat-inap.papan') }}" class="px-4 py-2 rounded bg-slate-200">Kembali</a>
        </div>
    </div>
</div>
