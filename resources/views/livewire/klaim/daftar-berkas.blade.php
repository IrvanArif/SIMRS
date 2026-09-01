<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">Berkas Klaim</h1>
        @can('susun', \App\Models\BerkasKlaim::class)
            <a href="{{ route('klaim.ekspor') }}" class="text-sm bg-slate-800 text-white px-4 py-2 rounded">
                Unduh CSV
            </a>
        @endcan
    </div>

    @if (session('sukses'))
        <div class="bg-green-50 text-green-700 px-4 py-3 rounded">{{ session('sukses') }}</div>
    @endif
    @error('klaim') <div class="bg-red-50 text-red-700 px-4 py-3 rounded">{{ $message }}</div> @enderror
    @error('berkas') <div class="bg-red-50 text-red-700 px-4 py-3 rounded">{{ $message }}</div> @enderror

    <div class="bg-white rounded shadow overflow-x-auto">
        <h2 class="font-semibold px-4 pt-4">Menunggu Disusun</h2>
        <table class="w-full text-sm mt-2">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="px-4 py-2">No. Kunjungan</th>
                    <th class="px-4 py-2">Pasien</th>
                    <th class="px-4 py-2">Total Tagihan</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($menungguKlaim as $kunjungan)
                    <tr class="border-t">
                        <td class="px-4 py-2 font-medium">{{ $kunjungan->no_kunjungan }}</td>
                        <td class="px-4 py-2">{{ $kunjungan->pasien->nama }}</td>
                        <td class="px-4 py-2">{{ number_format((int) ($kunjungan->tagihan->total ?? 0), 0, ',', '.') }}</td>
                        <td class="px-4 py-2 text-right">
                            @can('susun', \App\Models\BerkasKlaim::class)
                                <button wire:click="susun({{ $kunjungan->id }})" class="text-blue-600">Susun Klaim</button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">Tidak ada yang menunggu.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="bg-white rounded shadow overflow-x-auto">
        <h2 class="font-semibold px-4 pt-4">Berkas Tersusun</h2>
        <table class="w-full text-sm mt-2">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="px-4 py-2">No. Berkas</th>
                    <th class="px-4 py-2">No. SEP</th>
                    <th class="px-4 py-2">Peserta</th>
                    <th class="px-4 py-2">Jenis</th>
                    <th class="px-4 py-2">Biaya</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($daftarBerkas as $berkas)
                    <tr class="border-t">
                        <td class="px-4 py-2 font-medium">{{ $berkas->no_berkas }}</td>
                        <td class="px-4 py-2">{{ $berkas->sep?->no_sep ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $berkas->nama_peserta }}</td>
                        <td class="px-4 py-2">{{ $berkas->jenis_pelayanan->label() }}</td>
                        <td class="px-4 py-2">{{ number_format((int) $berkas->total_biaya, 0, ',', '.') }}</td>
                        <td class="px-4 py-2">{{ $berkas->status->label() }}</td>
                        <td class="px-4 py-2 text-right">
                            @can('ajukan', $berkas)
                                <button wire:click="ajukan({{ $berkas->id }})" class="text-blue-600">Ajukan</button>
                            @endcan
                        </td>
                    </tr>
                    @if ($berkas->peringatan)
                        <tr class="bg-amber-50">
                            <td colspan="7" class="px-4 py-2 text-xs text-amber-800">{{ $berkas->peringatan }}</td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-slate-500">Belum ada berkas klaim.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="bg-white rounded shadow p-6 space-y-3">
        <h2 class="font-semibold">Hasil Verifikasi</h2>

        <select wire:model="berkas_id" class="w-full border rounded px-3 py-2">
            <option value="">— pilih berkas —</option>
            @foreach ($daftarBerkas as $berkas)
                <option value="{{ $berkas->id }}">{{ $berkas->no_berkas }} — {{ $berkas->nama_peserta }} ({{ $berkas->status->label() }})</option>
            @endforeach
        </select>
        @error('berkas_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

        <select wire:model="hasil" class="w-full border rounded px-3 py-2">
            @foreach ($pilihanHasil as $pilihan)
                <option value="{{ $pilihan->value }}">{{ $pilihan->label() }}</option>
            @endforeach
        </select>

        <div>
            <label class="block text-sm mb-1">Catatan verifikator</label>
            <input type="text" wire:model="catatanVerifikasi" class="w-full border rounded px-3 py-2">
            @error('catatan_verifikasi') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            <p class="text-xs text-slate-500 mt-1">Wajib diisi bila berkas ditolak.</p>
        </div>

        <div class="flex gap-3">
            <button wire:click="tandaiHasil" class="bg-slate-800 text-white px-4 py-2 rounded text-sm">
                Catat Hasil
            </button>
        </div>

        <div class="border-t pt-3">
            <label class="block text-sm mb-1">Alasan pembatalan berkas</label>
            <input type="text" wire:model="alasanBatal" class="w-full border rounded px-3 py-2">
            @error('alasan') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            <button wire:click="batalkan" class="mt-2 bg-red-600 text-white px-4 py-2 rounded text-sm">
                Batalkan Berkas
            </button>
        </div>
    </div>
</div>
