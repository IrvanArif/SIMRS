<div class="max-w-2xl">
    <h1 class="text-xl font-semibold mb-1">Penyerahan Obat {{ $resep->no_resep }}</h1>
    <p class="text-sm text-slate-600 mb-4">
        {{ $resep->kunjungan->pasien->nama }} — No. RM {{ $resep->kunjungan->pasien->no_rm }} —
        {{ $resep->kunjungan->penjamin->nama }}
    </p>

    @if (session('sukses'))
        <div class="mb-4 bg-green-50 text-green-700 px-4 py-2 rounded">{{ session('sukses') }}</div>
    @endif

    @error('penyerahan')
        <div class="mb-4 bg-red-50 text-red-700 px-4 py-3 rounded">{{ $message }}</div>
    @enderror

    <div class="bg-white rounded shadow p-5">
        <p class="text-sm mb-3">Status resep: <strong>{{ $resep->status->label() }}</strong></p>

        <table class="w-full text-sm">
            <tbody>
                @foreach ($resep->detail as $baris)
                    <tr class="border-b">
                        <td class="py-2">{{ $baris->obat->nama }}</td>
                        <td class="py-2 text-right">
                            {{ $baris->jumlah_diserahkan ?: $baris->jumlah }} {{ $baris->obat->satuan }}
                        </td>
                        <td class="py-2 text-right text-slate-500">{{ $baris->aturan_pakai }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @php($tagihan = $resep->kunjungan->tagihan)
        @if ($tagihan)
            <p class="mt-4 text-sm text-slate-600">
                Tagihan {{ $tagihan->no_tagihan }} — {{ $tagihan->status->value }} —
                ditagihkan Rp {{ number_format($tagihan->ditagihkan_ke_pasien, 0, ',', '.') }}
            </p>
        @endif

        <div class="mt-5 flex gap-3">
            <button wire:click="serahkan" class="bg-green-600 text-white px-4 py-2 rounded">
                Serahkan ke Pasien
            </button>
            <a href="{{ route('apotek.antrean') }}" class="px-4 py-2 rounded bg-slate-200">Kembali</a>
        </div>
    </div>
</div>
