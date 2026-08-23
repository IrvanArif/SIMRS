<div class="max-w-4xl">
    <h1 class="text-xl font-semibold mb-1">Penyiapan Resep {{ $resep->no_resep }}</h1>
    <p class="text-sm text-slate-600 mb-4">
        {{ $resep->kunjungan->pasien->nama }} — No. RM {{ $resep->kunjungan->pasien->no_rm }} —
        {{ $resep->kunjungan->penjamin->nama }} — status: {{ $resep->status->label() }}
    </p>

    @error('penyiapan')
        <div class="mb-4 bg-red-50 text-red-700 px-4 py-3 rounded">{{ $message }}</div>
    @enderror

    @error('alasan')
        <div class="mb-4 bg-red-50 text-red-700 px-4 py-3 rounded">{{ $message }}</div>
    @enderror

    <div class="bg-white rounded shadow divide-y">
        @foreach ($resep->detail as $baris)
            <div class="p-5">
                <div class="flex items-baseline justify-between">
                    <h2 class="font-medium">{{ $baris->obat->nama }}</h2>
                    <span class="text-sm text-slate-600">
                        {{ $baris->jumlah }} {{ $baris->obat->satuan }} — {{ $baris->aturan_pakai }}
                    </span>
                </div>

                @if ($resep->status->bisaDisiapkan())
                    <p class="mt-3 text-xs uppercase tracking-wide text-slate-500">
                        Rencana pengambilan (kedaluwarsa terdekat lebih dulu)
                    </p>
                    <table class="w-full text-sm mt-1">
                        <tbody>
                            @forelse ($rencanaBatch[$baris->id] as $batch)
                                <tr class="border-t">
                                    <td class="py-1">{{ $batch->no_batch }}</td>
                                    <td class="py-1">kedaluwarsa {{ $batch->tanggal_kedaluwarsa->format('d/m/Y') }}</td>
                                    <td class="py-1 text-right">sisa {{ $batch->jumlah_tersisa }}</td>
                                </tr>
                            @empty
                                <tr><td class="py-1 text-red-600">Tidak ada batch layak pakai.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                @else
                    <p class="mt-2 text-sm text-slate-600">
                        Diserahkan {{ $baris->jumlah_diserahkan }} {{ $baris->obat->satuan }} —
                        Rp {{ number_format($baris->harga_satuan, 0, ',', '.') }}/{{ $baris->obat->satuan }}
                        (Rp {{ number_format($baris->subtotal(), 0, ',', '.') }})
                    </p>
                    <ul class="mt-1 text-xs text-slate-500 list-disc pl-5">
                        @foreach ($baris->pengambilan as $ambil)
                            <li>{{ $ambil->batch->no_batch }} — {{ $ambil->jumlah }} unit</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach
    </div>

    <div class="mt-5 flex flex-wrap items-center gap-3">
        @if ($resep->status->bisaDisiapkan())
            <button wire:click="siapkan" class="bg-green-600 text-white px-4 py-2 rounded">
                Siapkan &amp; Bebankan ke Tagihan
            </button>
        @else
            <input wire:model="alasanBatal" placeholder="Alasan pembatalan penyiapan"
                   class="flex-1 min-w-64 border rounded px-3 py-2">
            <button wire:click="batalkan" class="bg-red-600 text-white px-4 py-2 rounded">
                Batalkan Penyiapan
            </button>
            <a href="{{ route('apotek.serahkan', $resep) }}" class="bg-blue-600 text-white px-4 py-2 rounded">
                Lanjut ke Penyerahan
            </a>
        @endif
        <a href="{{ route('apotek.antrean') }}" class="px-4 py-2 rounded bg-slate-200">Kembali</a>
    </div>
</div>
