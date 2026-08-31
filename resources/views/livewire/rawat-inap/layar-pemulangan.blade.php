<div class="max-w-2xl space-y-6">
    <div>
        <h1 class="text-xl font-semibold">Pemulangan — {{ $rawatInap->no_rawat_inap }}</h1>
        <p class="text-sm text-slate-600">
            {{ $rawatInap->kunjungan->pasien->nama }} —
            No. RM {{ $rawatInap->kunjungan->pasien->no_rm }}
        </p>
    </div>

    @error('pemulangan')
        <div class="bg-red-50 text-red-700 px-4 py-3 rounded">{{ $message }}</div>
    @enderror

    <div class="bg-white p-6 rounded shadow space-y-4">
        <div>
            <label class="block text-sm mb-1">Diagnosa akhir</label>
            <select wire:model="diagnosa_akhir_id" class="w-full border rounded px-3 py-2">
                <option value="">— pilih diagnosa —</option>
                @foreach ($daftarIcd as $icd)
                    <option value="{{ $icd->id }}">{{ $icd->kode }} — {{ $icd->nama }}</option>
                @endforeach
            </select>
            @error('diagnosa_akhir_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm mb-1">Cara pulang</label>
            <select wire:model="cara_pulang" class="w-full border rounded px-3 py-2">
                @foreach ($pilihanCara as $cara)
                    <option value="{{ $cara->value }}">{{ $cara->label() }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm mb-1">Ringkasan pulang <span class="text-slate-400">(opsional)</span></label>
            <textarea wire:model="ringkasan" rows="3" class="w-full border rounded px-3 py-2"></textarea>
        </div>

        <div class="flex gap-3">
            <button wire:click="pulangkan" class="bg-green-600 text-white px-4 py-2 rounded">
                Pulangkan Pasien
            </button>
            <a href="{{ route('rawat-inap.papan') }}" class="px-4 py-2 rounded bg-slate-200">Kembali</a>
        </div>
    </div>

    <div class="bg-white p-6 rounded shadow">
        <h2 class="font-semibold mb-3">Rincian biaya sementara</h2>

        <table class="w-full text-sm">
            <tbody>
                @foreach ($rincian['baris'] as $baris)
                    <tr class="border-t">
                        <td class="py-1">{{ $baris['deskripsi'] }}</td>
                        <td class="py-1 text-right">{{ number_format($baris['subtotal'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr class="border-t font-semibold">
                    <td class="py-2">Total</td>
                    <td class="py-2 text-right">{{ number_format($rincian['total'], 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
