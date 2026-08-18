<x-layouts.app judul="Kuitansi Pembayaran">
    @php($tagihan = $pembayaran->tagihan)

    <div class="max-w-md mx-auto bg-white p-6 border">
        <div class="text-center mb-4">
            <p class="font-semibold">{{ config('app.name') }}</p>
            <p class="text-sm">Kuitansi Pembayaran</p>
        </div>

        <table class="w-full text-sm mb-4">
            <tbody>
                <tr><td class="py-1">No. Kuitansi</td><td class="py-1 text-right">{{ $pembayaran->no_kuitansi }}</td></tr>
                <tr><td class="py-1">No. Tagihan</td><td class="py-1 text-right">{{ $tagihan->no_tagihan }}</td></tr>
                <tr><td class="py-1">Pasien</td><td class="py-1 text-right">{{ $tagihan->kunjungan->pasien->nama }}</td></tr>
                <tr><td class="py-1">No. RM</td><td class="py-1 text-right">{{ $tagihan->kunjungan->pasien->no_rm }}</td></tr>
                <tr><td class="py-1">Tanggal</td><td class="py-1 text-right">{{ $pembayaran->waktu_bayar->format('d/m/Y H:i') }}</td></tr>
            </tbody>
        </table>

        <table class="w-full text-sm border-t border-b py-2">
            <tbody>
                @foreach ($tagihan->detail as $baris)
                    <tr>
                        <td class="py-1">{{ $baris->deskripsi }} × {{ $baris->jumlah }}</td>
                        <td class="py-1 text-right">Rp {{ number_format($baris->subtotal, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="w-full text-sm mt-2">
            <tbody>
                <tr class="font-semibold"><td class="py-1">Total</td><td class="py-1 text-right">Rp {{ number_format($tagihan->total, 0, ',', '.') }}</td></tr>
                <tr><td class="py-1">Dibayar ({{ $pembayaran->metode->value }})</td><td class="py-1 text-right">Rp {{ number_format($pembayaran->nominal, 0, ',', '.') }}</td></tr>
                <tr><td class="py-1">Kembalian</td><td class="py-1 text-right">Rp {{ number_format($pembayaran->kembalian, 0, ',', '.') }}</td></tr>
            </tbody>
        </table>

        <p class="text-xs mt-6 text-right">Kasir: {{ $pembayaran->kasir?->name ?? '—' }}</p>
    </div>

    <button onclick="window.print()" class="mx-auto mt-4 block bg-blue-600 text-white px-4 py-2 rounded print:hidden">
        Cetak
    </button>
</x-layouts.app>
