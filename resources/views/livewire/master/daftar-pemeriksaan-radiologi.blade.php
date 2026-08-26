<div>
    <div class="flex items-end justify-between mb-4">
        <h1 class="text-xl font-semibold">Master Pemeriksaan Radiologi</h1>
        <input type="text" wire:model.live="cari" placeholder="Cari kode atau nama"
               class="border rounded px-3 py-2 text-sm">
    </div>

    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="px-4 py-2">Kode</th>
                    <th class="px-4 py-2">Nama</th>
                    <th class="px-4 py-2">Modalitas</th>
                    <th class="px-4 py-2">Persiapan</th>
                    <th class="px-4 py-2">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($daftar as $baris)
                    <tr class="border-t">
                        <td class="px-4 py-2 font-medium">{{ $baris->kode }}</td>
                        <td class="px-4 py-2">{{ $baris->nama }}</td>
                        <td class="px-4 py-2">{{ str_replace('_', ' ', $baris->modalitas) }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $baris->persiapan ?? '—' }}</td>
                        <td class="px-4 py-2">
                            {{ $baris->aktif ? 'Aktif' : 'Nonaktif' }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">Belum ada pemeriksaan radiologi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $daftar->links() }}</div>
</div>
