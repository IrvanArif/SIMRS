<div class="space-y-6">
    <h1 class="text-xl font-semibold">Surat Eligibilitas Peserta (SEP)</h1>

    @if (session('sukses'))
        <div class="bg-green-50 text-green-700 px-4 py-3 rounded">{{ session('sukses') }}</div>
    @endif
    @error('sep') <div class="bg-red-50 text-red-700 px-4 py-3 rounded">{{ $message }}</div> @enderror

    <div class="bg-white rounded shadow p-6 space-y-3">
        <h2 class="font-semibold">Terbitkan SEP</h2>

        <div>
            <label class="block text-sm mb-1">Kunjungan berpenjamin yang belum ber-SEP</label>
            <select wire:model="kunjungan_id" class="w-full border rounded px-3 py-2">
                <option value="">— pilih kunjungan —</option>
                @foreach ($menungguSep as $kunjungan)
                    <option value="{{ $kunjungan->id }}">
                        {{ $kunjungan->no_kunjungan }} — {{ $kunjungan->pasien->nama }} ({{ $kunjungan->poli->nama }})
                    </option>
                @endforeach
            </select>
            @error('kunjungan_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            @error('no_kartu') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm mb-1">Diagnosa awal</label>
            <input type="text" wire:model="diagnosa_awal" class="w-full border rounded px-3 py-2">
            @error('diagnosa_awal') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm mb-1">Nomor rujukan <span class="text-slate-400">(opsional)</span></label>
            <input type="text" wire:model="no_rujukan" class="w-full border rounded px-3 py-2">
        </div>

        <button wire:click="terbitkan" class="bg-slate-800 text-white px-4 py-2 rounded text-sm">
            Terbitkan SEP
        </button>
    </div>

    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="px-4 py-2">No. SEP</th>
                    <th class="px-4 py-2">Pasien</th>
                    <th class="px-4 py-2">Jenis</th>
                    <th class="px-4 py-2">Kelas</th>
                    <th class="px-4 py-2">Tanggal</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2">Penerbit</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($daftarSep as $sep)
                    <tr class="border-t">
                        <td class="px-4 py-2 font-medium">{{ $sep->no_sep }}</td>
                        <td class="px-4 py-2">{{ $sep->kunjungan->pasien->nama }}</td>
                        <td class="px-4 py-2">{{ $sep->jenis_pelayanan->label() }}</td>
                        <td class="px-4 py-2">{{ $sep->kelas_rawat ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $sep->tanggal->format('d/m/Y') }}</td>
                        <td class="px-4 py-2">{{ $sep->berlaku() ? 'Berlaku' : 'Batal' }}</td>
                        <td class="px-4 py-2 text-slate-500">{{ $sep->diterbitkan_dengan }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-slate-500">Belum ada SEP.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="bg-white rounded shadow p-6 space-y-3">
        <h2 class="font-semibold">Batalkan SEP</h2>

        <select wire:model="sep_id" class="w-full border rounded px-3 py-2">
            <option value="">— pilih SEP —</option>
            @foreach ($daftarSep->where('status', \App\Models\Sep::BERLAKU) as $sep)
                <option value="{{ $sep->id }}">{{ $sep->no_sep }} — {{ $sep->kunjungan->pasien->nama }}</option>
            @endforeach
        </select>
        @error('sep_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

        <div>
            <label class="block text-sm mb-1">Alasan pembatalan</label>
            <input type="text" wire:model="alasanBatal" class="w-full border rounded px-3 py-2">
            @error('alasan') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <button wire:click="batalkan" class="bg-red-600 text-white px-4 py-2 rounded text-sm">
            Batalkan SEP
        </button>
    </div>
</div>
