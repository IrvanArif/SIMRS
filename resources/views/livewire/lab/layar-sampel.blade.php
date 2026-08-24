<div class="max-w-xl">
    <h1 class="text-xl font-semibold mb-1">Pengambilan Sampel — {{ $order->no_order }}</h1>
    <p class="text-sm text-slate-600 mb-4">
        {{ $order->kunjungan->pasien->nama }} — No. RM {{ $order->kunjungan->pasien->no_rm }} —
        {{ $order->kunjungan->pasien->jenis_kelamin->label() }}
    </p>

    @error('sampel')
        <div class="mb-4 bg-red-50 text-red-700 px-4 py-3 rounded">{{ $message }}</div>
    @enderror

    <div class="bg-white p-6 rounded shadow space-y-4">
        <div>
            <p class="text-xs uppercase tracking-wide text-slate-500">Pemeriksaan diminta</p>
            <ul class="mt-1 text-sm list-disc pl-5">
                @foreach ($order->detail as $detail)
                    <li>{{ $detail->pemeriksaan->nama }}</li>
                @endforeach
            </ul>
        </div>

        @if ($order->catatan_klinis)
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-500">Catatan klinis dokter</p>
                <p class="text-sm">{{ $order->catatan_klinis }}</p>
            </div>
        @endif

        <p class="text-sm">Status: <strong>{{ $order->status->label() }}</strong></p>

        <div class="flex gap-3">
            <button wire:click="ambil" class="bg-green-600 text-white px-4 py-2 rounded">
                Sampel Sudah Diambil
            </button>
            <a href="{{ route('lab.antrean') }}" class="px-4 py-2 rounded bg-slate-200">Kembali</a>
        </div>
    </div>
</div>
