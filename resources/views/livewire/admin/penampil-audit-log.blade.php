<div>
    <h1 class="text-xl font-semibold mb-4">Audit Log</h1>

    <div class="flex gap-3 mb-4">
        <select wire:model.live="filterModel" class="border rounded px-3 py-2">
            <option value="">Semua model</option>
            @foreach ($daftarModel as $model)
                <option value="{{ $model }}">{{ class_basename($model) }}</option>
            @endforeach
        </select>
        <select wire:model.live="filterAksi" class="border rounded px-3 py-2">
            <option value="">Semua aksi</option>
            <option value="create">create</option>
            <option value="update">update</option>
            <option value="delete">delete</option>
            <option value="restore">restore</option>
        </select>
    </div>

    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="px-4 py-2">Waktu</th>
                    <th class="px-4 py-2">Pelaku</th>
                    <th class="px-4 py-2">Aksi</th>
                    <th class="px-4 py-2">Model</th>
                    <th class="px-4 py-2">ID</th>
                    <th class="px-4 py-2">Alasan</th>
                    <th class="px-4 py-2">Perubahan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($catatan as $baris)
                    <tr class="border-t align-top">
                        <td class="px-4 py-2 whitespace-nowrap">{{ $baris->created_at?->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-2">{{ $baris->user?->name ?? 'Sistem' }}</td>
                        <td class="px-4 py-2">{{ $baris->aksi }}</td>
                        <td class="px-4 py-2">{{ class_basename($baris->model_tipe) }}</td>
                        <td class="px-4 py-2">{{ $baris->model_id }}</td>
                        <td class="px-4 py-2">{{ $baris->alasan ?? '—' }}</td>
                        <td class="px-4 py-2 text-xs text-slate-600">
                            @foreach (($baris->perubahan['sesudah'] ?? []) as $kolom => $nilai)
                                @continue(in_array($kolom, ['created_at', 'updated_at']))
                                <div>{{ $kolom }}: {{ \Illuminate\Support\Str::limit((string) ($baris->perubahan['sebelum'][$kolom] ?? '—'), 20) }}
                                    → {{ \Illuminate\Support\Str::limit((string) $nilai, 20) }}</div>
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-slate-500">Belum ada catatan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $catatan->links() }}</div>
</div>
