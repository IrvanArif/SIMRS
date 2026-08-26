<div class="max-w-xl">
    <h1 class="text-xl font-semibold mb-1">Pelaksanaan Pencitraan — {{ $order->no_order }}</h1>
    <p class="text-sm text-slate-600 mb-4">
        {{ $order->kunjungan->pasien->nama }} — No. RM {{ $order->kunjungan->pasien->no_rm }} —
        {{ $order->kunjungan->pasien->jenis_kelamin->label() }}
    </p>

    @error('pelaksanaan')
        <div class="mb-4 bg-red-50 text-red-700 px-4 py-3 rounded">{{ $message }}</div>
    @enderror

    <div class="bg-white p-6 rounded shadow space-y-4">
        <div>
            <p class="text-xs uppercase tracking-wide text-slate-500">Pemeriksaan diminta</p>
            <ul class="mt-1 text-sm list-disc pl-5">
                @foreach ($order->detail as $detail)
                    <li>
                        {{ $detail->pemeriksaan->nama }}
                        <span class="text-slate-500">({{ str_replace('_', ' ', $detail->pemeriksaan->modalitas) }})</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <div>
            <p class="text-xs uppercase tracking-wide text-slate-500">Indikasi klinis</p>
            <p class="text-sm">{{ $order->indikasi_klinis }}</p>
        </div>

        @php $persiapan = $order->detail->pluck('pemeriksaan.persiapan')->filter()->unique(); @endphp

        @if ($persiapan->isNotEmpty())
            <div class="bg-amber-50 border border-amber-200 rounded px-4 py-3">
                <p class="text-xs uppercase tracking-wide text-amber-700">Persiapan pasien</p>
                <ul class="mt-1 text-sm list-disc pl-5 text-amber-900">
                    @foreach ($persiapan as $instruksi)
                        <li>{{ $instruksi }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div>
            <label class="block text-sm mb-1">Nomor film / arsip citra</label>
            <input type="text" wire:model="no_film" class="w-full border rounded px-3 py-2"
                   placeholder="mis. FILM-2026-0007">
            @error('no_film') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            <p class="text-xs text-slate-500 mt-1">
                Tanpa nomor ini, citra yang sudah diambil tidak bisa ditemukan lagi di arsip.
            </p>
        </div>

        <p class="text-sm">Status: <strong>{{ $order->status->label() }}</strong></p>

        <div class="flex gap-3">
            <button wire:click="kerjakan" class="bg-green-600 text-white px-4 py-2 rounded">
                Pencitraan Sudah Dikerjakan
            </button>
            <a href="{{ route('radiologi.antrean') }}" class="px-4 py-2 rounded bg-slate-200">Kembali</a>
        </div>
    </div>
</div>
