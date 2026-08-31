@php $bed = $rawatInap->bedSekarang(); @endphp

<div class="max-w-4xl space-y-6">
    <div>
        <h1 class="text-xl font-semibold">Perawatan — {{ $rawatInap->no_rawat_inap }}</h1>
        <p class="text-sm text-slate-600">
            {{ $rawatInap->kunjungan->pasien->nama }} —
            No. RM {{ $rawatInap->kunjungan->pasien->no_rm }} —
            @if ($bed) {{ $bed->ruang->nama }} {{ $bed->nomor }} ({{ $bed->kelas->nama }}) @else belum menempati bed @endif
        </p>
    </div>

    @if (session('sukses'))
        <div class="bg-green-50 text-green-700 px-4 py-3 rounded">{{ session('sukses') }}</div>
    @endif

    @error('catatan') <div class="bg-red-50 text-red-700 px-4 py-3 rounded">{{ $message }}</div> @enderror
    @error('pindah') <div class="bg-red-50 text-red-700 px-4 py-3 rounded">{{ $message }}</div> @enderror

    <div class="bg-white p-6 rounded shadow space-y-3">
        <h2 class="font-semibold">Catatan Perkembangan Baru</h2>

        @foreach (['subjective' => 'Subjective', 'objective' => 'Objective', 'assessment' => 'Assessment', 'plan' => 'Plan'] as $kolom => $label)
            <div>
                <label class="block text-sm mb-1">{{ $label }}</label>
                <textarea wire:model="{{ $kolom }}" rows="2" class="w-full border rounded px-3 py-2"></textarea>
                @error($kolom) <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        @endforeach

        <button wire:click="simpanCatatan" class="bg-slate-800 text-white px-4 py-2 rounded text-sm">
            Simpan Catatan
        </button>
    </div>

    <div class="bg-white p-6 rounded shadow">
        <h2 class="font-semibold mb-3">Riwayat Catatan</h2>

        @forelse ($catatan as $baris)
            <div class="border-t pt-3 mt-3 text-sm">
                <p class="text-xs text-slate-500">
                    {{ $baris->waktu->format('d/m/Y H:i') }} —
                    {{ $baris->penulis?->name ?? 'Pengguna dihapus' }}
                    <span class="uppercase">({{ $baris->peran_penulis }})</span>
                </p>
                <p><strong>S:</strong> {{ $baris->subjective }}</p>
                <p><strong>O:</strong> {{ $baris->objective }}</p>
                <p><strong>A:</strong> {{ $baris->assessment }}</p>
                <p><strong>P:</strong> {{ $baris->plan }}</p>
            </div>
        @empty
            <p class="text-sm text-slate-500">Belum ada catatan perkembangan.</p>
        @endforelse
    </div>

    <div class="bg-white p-6 rounded shadow space-y-3">
        <h2 class="font-semibold">Pindah Bed</h2>

        <select wire:model="bed_tujuan_id" class="w-full border rounded px-3 py-2">
            <option value="">— pilih bed tujuan —</option>
            @foreach ($bedTersedia as $pilihan)
                <option value="{{ $pilihan->id }}">
                    {{ $pilihan->ruang->nama }} {{ $pilihan->nomor }} — {{ $pilihan->kelas->nama }}
                </option>
            @endforeach
        </select>
        @error('bed_tujuan_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

        <div>
            <label class="block text-sm mb-1">Alasan pindah</label>
            <input type="text" wire:model="alasanPindah" class="w-full border rounded px-3 py-2">
            @error('alasan') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <button wire:click="pindahBed" class="bg-slate-800 text-white px-4 py-2 rounded text-sm">
            Pindahkan
        </button>
    </div>

    <div class="bg-white p-6 rounded shadow">
        <h2 class="font-semibold mb-3">Biaya sementara</h2>

        <table class="w-full text-sm">
            <tbody>
                @foreach ($rincian['baris'] as $baris)
                    <tr class="border-t">
                        <td class="py-1">{{ $baris['deskripsi'] }}</td>
                        <td class="py-1 text-right">{{ number_format($baris['subtotal'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr class="border-t font-semibold">
                    <td class="py-2">Total sementara</td>
                    <td class="py-2 text-right">{{ number_format($rincian['total'], 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>

        <p class="text-xs text-slate-500 mt-2">
            Angka sementara, dihitung sampai hari ini. Tagihan resmi terbit saat pasien dipulangkan.
        </p>
    </div>
</div>
