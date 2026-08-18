<div>
    <h1 class="text-xl font-semibold mb-4">Antrian Poli Hari Ini</h1>

    @unless (auth()->user()->dokter)
        <select wire:model.live="poli_id" class="border rounded px-3 py-2 mb-4">
            <option value="">Semua poli</option>
            @foreach ($daftarPoli as $poli)
                <option value="{{ $poli->id }}">{{ $poli->nama }}</option>
            @endforeach
        </select>
    @endunless

    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="px-4 py-2">Nomor</th>
                    <th class="px-4 py-2">Pasien</th>
                    <th class="px-4 py-2">Dokter</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($daftarAntrian as $antrian)
                    <tr class="border-t">
                        <td class="px-4 py-2 font-semibold">{{ $antrian->kode() }}</td>
                        <td class="px-4 py-2">{{ $antrian->kunjungan->pasien->nama }}</td>
                        <td class="px-4 py-2">{{ $antrian->kunjungan->dokter->nama }}</td>
                        <td class="px-4 py-2">{{ $antrian->status->value }}</td>
                        <td class="px-4 py-2 text-right space-x-3">
                            <button wire:click="panggil({{ $antrian->id }})" class="text-blue-600">Panggil</button>
                            @role('perawat')
                                <a href="{{ route('poli.vital', $antrian->kunjungan) }}" class="text-blue-600">Vital</a>
                            @endrole
                            @role('dokter')
                                <a href="{{ route('poli.soap', $antrian->kunjungan) }}" class="text-blue-600">Periksa</a>
                            @endrole
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">Belum ada antrian.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
