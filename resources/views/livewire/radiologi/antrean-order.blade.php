<div>
    <div class="flex items-end justify-between mb-4">
        <h1 class="text-xl font-semibold">Antrean Radiologi</h1>
        <select wire:model.live="status" class="border rounded px-3 py-2 text-sm">
            @foreach ($pilihanStatus as $pilihan)
                <option value="{{ $pilihan->value }}">{{ $pilihan->label() }}</option>
            @endforeach
        </select>
    </div>

    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="px-4 py-2">No. Order</th>
                    <th class="px-4 py-2">Pasien</th>
                    <th class="px-4 py-2">Poli</th>
                    <th class="px-4 py-2">Pemeriksaan</th>
                    <th class="px-4 py-2">No. Film</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($daftarOrder as $order)
                    <tr class="border-t">
                        <td class="px-4 py-2 font-medium">{{ $order->no_order }}</td>
                        <td class="px-4 py-2">{{ $order->kunjungan->pasien->nama }}</td>
                        <td class="px-4 py-2">{{ $order->kunjungan->poli->nama }}</td>
                        <td class="px-4 py-2">
                            {{ $order->detail->map(fn ($d) => $d->pemeriksaan->nama)->implode(', ') }}
                        </td>
                        <td class="px-4 py-2 text-slate-500">{{ $order->no_film ?? '—' }}</td>
                        <td class="px-4 py-2 text-right">
                            @if ($order->status === \App\Enums\StatusOrderRadiologi::Dipesan)
                                <a href="{{ route('radiologi.kerjakan', $order) }}" class="text-blue-600">Kerjakan</a>
                            @elseif ($order->status === \App\Enums\StatusOrderRadiologi::Dikerjakan)
                                <span class="text-slate-500">Menunggu dokter radiologi</span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-slate-500">Tidak ada order pada status ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $daftarOrder->links() }}</div>
</div>
