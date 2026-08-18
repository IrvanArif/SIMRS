<x-layouts.app judul="Karcis Antrian">
    <div class="max-w-xs mx-auto bg-white p-4 text-center border">
        <p class="text-sm">{{ config('app.name') }}</p>
        <p class="text-sm">{{ $antrian->poli->nama }}</p>
        <p class="text-5xl font-bold my-3">{{ $antrian->kode() }}</p>
        <p class="text-sm">{{ $antrian->kunjungan->pasien->nama }}</p>
        <p class="text-xs">No. RM {{ $antrian->kunjungan->pasien->no_rm }}</p>
        <p class="text-xs">
            {{ $antrian->tanggal->format('d/m/Y') }} — {{ $antrian->kunjungan->dokter->nama }}
        </p>
    </div>

    <button onclick="window.print()" class="mx-auto mt-4 block bg-blue-600 text-white px-4 py-2 rounded print:hidden">
        Cetak
    </button>
</x-layouts.app>
