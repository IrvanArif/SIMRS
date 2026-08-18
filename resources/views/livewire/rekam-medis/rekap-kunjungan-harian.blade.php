<div class="max-w-2xl">
    <h1 class="text-xl font-semibold mb-4">Rekap Kunjungan Harian</h1>

    <input wire:model.live="tanggal" type="date" class="border rounded px-3 py-2 mb-4">

    <div class="bg-white p-6 rounded shadow">
        <p class="text-3xl font-bold">{{ $jumlahKunjungan }}</p>
        <p class="text-sm text-slate-600 mb-4">total kunjungan pada tanggal tersebut</p>

        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left">
                <tr><th class="px-3 py-2">Poli</th><th class="px-3 py-2 text-right">Jumlah</th></tr>
            </thead>
            <tbody>
                @forelse ($perPoli as $namaPoli => $jumlah)
                    <tr class="border-t">
                        <td class="px-3 py-2">{{ $namaPoli }}</td>
                        <td class="px-3 py-2 text-right">{{ $jumlah }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="px-3 py-4 text-center text-slate-500">Tidak ada kunjungan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
