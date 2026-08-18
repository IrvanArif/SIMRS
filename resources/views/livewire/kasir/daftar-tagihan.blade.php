<div>
    <h1 class="text-xl font-semibold mb-4">Tagihan Belum Lunas</h1>

    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="px-4 py-2">No. Tagihan</th>
                    <th class="px-4 py-2">Pasien</th>
                    <th class="px-4 py-2">Penjamin</th>
                    <th class="px-4 py-2 text-right">Ditagihkan</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($daftarTagihan as $tagihan)
                    <tr class="border-t">
                        <td class="px-4 py-2">{{ $tagihan->no_tagihan }}</td>
                        <td class="px-4 py-2">{{ $tagihan->kunjungan->pasien->nama }}</td>
                        <td class="px-4 py-2">{{ $tagihan->penjamin->nama }}</td>
                        <td class="px-4 py-2 text-right">
                            Rp {{ number_format($tagihan->ditagihkan_ke_pasien, 0, ',', '.') }}
                        </td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('kasir.bayar', $tagihan) }}" class="text-blue-600">Proses</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">Tidak ada tagihan menunggu.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $daftarTagihan->links() }}</div>
</div>
