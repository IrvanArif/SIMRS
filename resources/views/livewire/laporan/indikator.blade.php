<div class="space-y-6">
    <h1 class="text-xl font-semibold">Indikator Rawat Inap</h1>

    <div class="bg-white rounded shadow p-6 flex flex-wrap gap-4 items-end">
        <div>
            <label class="block text-sm mb-1">Tanggal awal</label>
            <input type="date" wire:model.live="awal" class="border rounded px-3 py-2">
        </div>
        <div>
            <label class="block text-sm mb-1">Tanggal akhir</label>
            <input type="date" wire:model.live="akhir" class="border rounded px-3 py-2">
        </div>
    </div>

    @if ($galat)
        <div class="bg-red-50 text-red-700 px-4 py-3 rounded">{{ $galat }}</div>
    @else
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach ([
                'BOR' => [$hasil['bor'].'%', 'Seberapa penuh bangsalnya'],
                'LOS' => [$hasil['los'].' hari', 'Rata-rata lama dirawat'],
                'TOI' => [$hasil['toi'].' hari', 'Rata-rata bed menganggur'],
                'BTO' => [$hasil['bto'].' kali', 'Perputaran satu bed'],
            ] as $nama => [$nilai, $arti])
                <div class="bg-white rounded shadow p-6">
                    <p class="text-xs uppercase tracking-wide text-slate-500">{{ $nama }}</p>
                    <p class="text-2xl font-semibold">{{ $nilai }}</p>
                    <p class="text-xs text-slate-500 mt-1">{{ $arti }}</p>
                </div>
            @endforeach
        </div>

        <div class="bg-white rounded shadow p-6 text-sm space-y-1">
            <p>Bed aktif tersedia: <strong>{{ $hasil['bed_tersedia'] }}</strong></p>
            <p>Hari rawat dalam periode: <strong>{{ $hasil['hari_rawat'] }}</strong></p>
            <p>Pasien keluar: <strong>{{ $hasil['pasien_keluar'] }}</strong></p>
            <p class="text-xs text-slate-500 pt-2">
                Bed nonaktif tidak dihitung sebagai kapasitas — bed rusak bukan kapasitas.
            </p>
        </div>
    @endif
</div>
