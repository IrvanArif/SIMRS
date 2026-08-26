@php $sudahDitulis = $order->status === \App\Enums\StatusOrderRadiologi::Selesai; @endphp

<div class="max-w-3xl">
    <h1 class="text-xl font-semibold mb-1">Ekspertise Radiologi — {{ $order->no_order }}</h1>
    <p class="text-sm text-slate-600 mb-4">
        {{ $order->kunjungan->pasien->nama }} — No. RM {{ $order->kunjungan->pasien->no_rm }} —
        No. Film {{ $order->no_film ?? '—' }}
    </p>

    @if (session('sukses'))
        <div class="mb-4 bg-green-50 text-green-700 px-4 py-3 rounded">{{ session('sukses') }}</div>
    @endif

    @error('ekspertise')
        <div class="mb-4 bg-red-50 text-red-700 px-4 py-3 rounded">{{ $message }}</div>
    @enderror

    <div class="bg-white p-6 rounded shadow space-y-5">
        <div>
            <p class="text-xs uppercase tracking-wide text-slate-500">Indikasi klinis dokter pengirim</p>
            <p class="text-sm">{{ $order->indikasi_klinis }}</p>
        </div>

        @foreach ($order->detail as $detail)
            <div class="border-t pt-4 space-y-3">
                <p class="font-medium">{{ $detail->pemeriksaan->nama }}</p>

                <div>
                    <label class="block text-sm mb-1">Temuan</label>
                    <textarea wire:model="bacaan.{{ $detail->id }}.temuan" rows="4"
                              class="w-full border rounded px-3 py-2"></textarea>
                    @error("bacaan.{$detail->id}.temuan")
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm mb-1">Kesan</label>
                    <textarea wire:model="bacaan.{{ $detail->id }}.kesan" rows="2"
                              class="w-full border rounded px-3 py-2"></textarea>
                    @error("bacaan.{$detail->id}.kesan")
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm mb-1">Saran <span class="text-slate-400">(opsional)</span></label>
                    <textarea wire:model="bacaan.{{ $detail->id }}.saran" rows="2"
                              class="w-full border rounded px-3 py-2"></textarea>
                </div>
            </div>
        @endforeach

        @if ($sudahDitulis)
            {{-- Bacaan yang sudah ditulis hanya boleh diubah dengan alasan (aturan 56). --}}
            <div class="border-t pt-4">
                <label class="block text-sm mb-1">Alasan koreksi</label>
                <input type="text" wire:model="alasanKoreksi" class="w-full border rounded px-3 py-2"
                       placeholder="mis. salah membaca sisi">
                @error('alasan') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        @endif

        <div class="flex gap-3 border-t pt-4">
            <button wire:click="simpan" class="bg-slate-800 text-white px-4 py-2 rounded">
                {{ $sudahDitulis ? 'Simpan Koreksi' : 'Simpan Ekspertise' }}
            </button>
            <a href="{{ route('beranda') }}" class="px-4 py-2 rounded bg-slate-200">Kembali</a>
        </div>
    </div>
</div>
