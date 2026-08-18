<div>
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">Cari Pasien</h1>
        <a href="{{ route('pendaftaran.pasien.baru') }}" class="bg-blue-600 text-white px-4 py-2 rounded">
            Pasien Baru
        </a>
    </div>

    @if (session('sukses'))
        <div class="mb-4 bg-green-50 text-green-700 px-4 py-2 rounded">{{ session('sukses') }}</div>
    @endif

    <input wire:model.live.debounce.300ms="kata" placeholder="Cari NIK, nama, atau nomor RM"
           class="w-full border rounded px-3 py-2 mb-4">

    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="px-4 py-2">No. RM</th>
                    <th class="px-4 py-2">NIK</th>
                    <th class="px-4 py-2">Nama</th>
                    <th class="px-4 py-2">Tanggal Lahir</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($daftarPasien as $pasien)
                    <tr class="border-t">
                        <td class="px-4 py-2">{{ $pasien->no_rm }}</td>
                        <td class="px-4 py-2">{{ $pasien->nik }}</td>
                        <td class="px-4 py-2">{{ $pasien->nama }}</td>
                        <td class="px-4 py-2">{{ $pasien->tanggal_lahir->format('d/m/Y') }}</td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('pendaftaran.kunjungan', $pasien) }}" class="text-blue-600">
                                Buat Kunjungan
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">Tidak ada pasien.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $daftarPasien->links() }}</div>
</div>
