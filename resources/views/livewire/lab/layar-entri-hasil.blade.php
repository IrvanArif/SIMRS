<div class="max-w-3xl">
    <h1 class="text-xl font-semibold mb-1">Entri Hasil — {{ $order->no_order }}</h1>
    <p class="text-sm text-slate-600 mb-4">
        {{ $order->kunjungan->pasien->nama }} — No. RM {{ $order->kunjungan->pasien->no_rm }} —
        {{ $jenisKelamin->label() }}
    </p>

    @if (session('sukses'))
        <div class="mb-4 bg-green-50 text-green-700 px-4 py-2 rounded">{{ session('sukses') }}</div>
    @endif

    @error('entri')
        <div class="mb-4 bg-red-50 text-red-700 px-4 py-3 rounded">{{ $message }}</div>
    @enderror

    <div class="bg-white rounded shadow divide-y">
        @foreach ($order->detail as $detail)
            <div class="p-5">
                <h2 class="font-medium mb-3">{{ $detail->pemeriksaan->nama }}</h2>

                <table class="w-full text-sm">
                    <thead class="text-left text-slate-500">
                        <tr>
                            <th class="py-1 font-normal">Parameter</th>
                            <th class="py-1 font-normal w-32">Nilai</th>
                            <th class="py-1 font-normal w-20">Satuan</th>
                            <th class="py-1 font-normal">Rujukan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($detail->pemeriksaan->parameter as $parameter)
                            @php
                                $rujukan = $parameter->rujukan
                                    ->sortBy(fn ($r) => $r->jenis_kelamin === $jenisKelamin->value ? 0 : 1)
                                    ->first(fn ($r) => in_array($r->jenis_kelamin, [$jenisKelamin->value, 'semua'], true));
                            @endphp
                            <tr class="border-t">
                                <td class="py-2">{{ $parameter->nama }}</td>
                                <td class="py-2">
                                    <input wire:model="nilai.{{ $parameter->id }}"
                                           class="w-28 border rounded px-2 py-1">
                                    @error('nilai.'.$parameter->id)
                                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                    @enderror
                                </td>
                                <td class="py-2 text-slate-600">{{ $parameter->satuan }}</td>
                                {{-- Rujukan tampil di sebelah kolom isian supaya analis
                                     melihat kewajaran nilainya saat mengetik, bukan sesudahnya. --}}
                                <td class="py-2 text-slate-600">
                                    {{ $rujukan ? $rujukan->rentang() : 'belum ada rujukan' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    </div>

    <div class="mt-5 flex gap-3">
        <button wire:click="simpan" class="bg-blue-600 text-white px-4 py-2 rounded">Simpan Hasil</button>
        <a href="{{ route('lab.validasi', $order) }}" class="bg-slate-200 px-4 py-2 rounded">Lanjut ke Validasi</a>
        <a href="{{ route('lab.antrean') }}" class="px-4 py-2 rounded bg-slate-200">Kembali</a>
    </div>
</div>
