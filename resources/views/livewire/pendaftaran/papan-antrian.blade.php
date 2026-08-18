<div>
    <h1 class="text-xl font-semibold mb-4">Antrian Hari Ini</h1>

    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="px-4 py-2">Nomor</th>
                    <th class="px-4 py-2">Poli</th>
                    <th class="px-4 py-2">Pasien</th>
                    <th class="px-4 py-2">Dokter</th>
                    <th class="px-4 py-2">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($daftarAntrian as $antrian)
                    <tr class="border-t">
                        <td class="px-4 py-2 font-semibold">{{ $antrian->kode() }}</td>
                        <td class="px-4 py-2">{{ $antrian->poli->nama }}</td>
                        <td class="px-4 py-2">{{ $antrian->kunjungan->pasien->nama }}</td>
                        <td class="px-4 py-2">{{ $antrian->kunjungan->dokter->nama }}</td>
                        <td class="px-4 py-2">{{ $antrian->status->value }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">Belum ada antrian hari ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
