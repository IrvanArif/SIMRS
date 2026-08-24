<div class="max-w-3xl">
    <h1 class="text-xl font-semibold mb-1">Validasi Hasil — {{ $order->no_order }}</h1>
    <p class="text-sm text-slate-600 mb-4">
        {{ $order->kunjungan->pasien->nama }} — status: {{ $order->status->label() }}
    </p>

    @error('validasi')
        <div class="mb-4 bg-red-50 text-red-700 px-4 py-3 rounded">{{ $message }}</div>
    @enderror

    @error('alasan')
        <div class="mb-4 bg-red-50 text-red-700 px-4 py-3 rounded">{{ $message }}</div>
    @enderror

    <div class="bg-white rounded shadow divide-y">
        @foreach ($order->detail as $detail)
            <div class="p-5">
                <h2 class="font-medium mb-3">{{ $detail->pemeriksaan->nama }}</h2>

                <table class="w-full text-sm">
                    <tbody>
                        @foreach ($detail->hasil as $hasil)
                            <tr class="border-t">
                                <td class="py-2">{{ $hasil->parameter->nama }}</td>
                                <td class="py-2">
                                    @if ($order->terbacaDokter())
                                        <input wire:model="nilai.{{ $hasil->parameter_lab_id }}"
                                               class="w-28 border rounded px-2 py-1">
                                    @else
                                        <span class="font-medium">{{ $hasil->nilai }}</span>
                                    @endif
                                </td>
                                <td class="py-2 text-slate-600">{{ $hasil->parameter->satuan }}</td>
                                <td class="py-2">
                                    @if ($hasil->penanda === null)
                                        <span class="text-slate-400">tanpa rujukan</span>
                                    @elseif ($hasil->abnormal())
                                        <span class="font-medium {{ $hasil->penanda === \App\Enums\PenandaHasil::Tinggi ? 'text-red-600' : 'text-amber-600' }}">
                                            {{ $hasil->penanda->label() }}
                                        </span>
                                    @else
                                        <span class="text-green-700">Normal</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    </div>

    <div class="mt-5 space-y-3">
        @if ($order->terbacaDokter())
            <p class="text-sm text-slate-600">
                Hasil sudah divalidasi dan terbaca dokter. Koreksi wajib menyertakan alasan
                dan akan tercatat di audit log.
            </p>
            <div class="flex gap-3">
                <input wire:model="alasanKoreksi" placeholder="Alasan koreksi"
                       class="flex-1 border rounded px-3 py-2">
                <button wire:click="koreksi" class="bg-amber-600 text-white px-4 py-2 rounded">
                    Simpan Koreksi
                </button>
            </div>
        @else
            <button wire:click="validasi" class="bg-green-600 text-white px-4 py-2 rounded">
                Validasi &amp; Lepas ke Dokter
            </button>
        @endif

        <a href="{{ route('lab.antrean') }}" class="inline-block px-4 py-2 rounded bg-slate-200">Kembali</a>
    </div>
</div>
