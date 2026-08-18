<div>
    <h1 class="text-xl font-semibold mb-4">Penelusuran Rekam Medis</h1>

    <input wire:model.live.debounce.300ms="kata" placeholder="Cari NIK, nama, atau nomor RM"
           class="w-full border rounded px-3 py-2 mb-4">

    @forelse ($daftarPasien as $pasien)
        <div class="bg-white rounded shadow p-4 mb-3">
            <div class="flex justify-between items-start">
                <div>
                    <p class="font-semibold">{{ $pasien->nama }}</p>
                    <p class="text-sm text-slate-600">
                        No. RM {{ $pasien->no_rm }} — NIK {{ $pasien->nik }} —
                        {{ $pasien->tanggal_lahir->format('d/m/Y') }} ({{ $pasien->umur() }} th)
                    </p>
                </div>
                <a href="{{ route('rekam-medis.koreksi', $pasien) }}" class="text-blue-600 text-sm">Koreksi Data</a>
            </div>

            <ul class="mt-3 text-sm border-t pt-2">
                @forelse ($pasien->kunjungan as $kunjungan)
                    <li class="py-1">
                        {{ $kunjungan->tanggal->format('d/m/Y') }} — {{ $kunjungan->status->label() }}
                        @foreach ($kunjungan->diagnosa as $diagnosa)
                            <span class="text-slate-500">[{{ $diagnosa->icd10->kode }}]</span>
                        @endforeach
                    </li>
                @empty
                    <li class="py-1 text-slate-500">Belum ada kunjungan.</li>
                @endforelse
            </ul>
        </div>
    @empty
        <p class="text-slate-500">Tidak ada pasien.</p>
    @endforelse

    <div class="mt-4">{{ $daftarPasien->links() }}</div>
</div>
