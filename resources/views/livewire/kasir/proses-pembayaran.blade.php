<div class="max-w-xl">
    <h1 class="text-xl font-semibold mb-1">Proses Pembayaran</h1>
    <p class="text-sm text-slate-600 mb-4">
        {{ $tagihan->no_tagihan }} — {{ $tagihan->kunjungan->pasien->nama }}
        (No. RM {{ $tagihan->kunjungan->pasien->no_rm }})
    </p>

    <div class="bg-white p-6 rounded shadow space-y-4">
        <table class="w-full text-sm">
            <tbody>
                @foreach ($tagihan->detail as $baris)
                    <tr class="border-b">
                        <td class="py-2">{{ $baris->deskripsi }} × {{ $baris->jumlah }}</td>
                        <td class="py-2 text-right">Rp {{ number_format($baris->subtotal, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr class="font-semibold">
                    <td class="py-2">Total</td>
                    <td class="py-2 text-right">Rp {{ number_format($tagihan->total, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td class="py-2">Ditanggung penjamin</td>
                    <td class="py-2 text-right">Rp {{ number_format($tagihan->ditanggung_penjamin, 0, ',', '.') }}</td>
                </tr>
                <tr class="font-semibold text-blue-700">
                    <td class="py-2">Ditagihkan ke pasien</td>
                    <td class="py-2 text-right">Rp {{ number_format($tagihan->ditagihkan_ke_pasien, 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>

        <div>
            <label class="block text-sm mb-1">Metode</label>
            <select wire:model="metode" class="w-full border rounded px-3 py-2">
                <option value="tunai">Tunai</option>
                <option value="debit">Debit</option>
                <option value="qris">QRIS</option>
            </select>
        </div>

        <div>
            <label class="block text-sm mb-1">Nominal Diterima</label>
            <input wire:model="nominal" type="number" class="w-full border rounded px-3 py-2">
            @error('nominal') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <button wire:click="bayar" class="bg-green-600 text-white px-4 py-2 rounded">
            Bayar & Cetak Kuitansi
        </button>
    </div>
</div>
