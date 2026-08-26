<div class="grid grid-cols-3 gap-6">
    <div class="col-span-2 space-y-6">
        <div>
            <h1 class="text-xl font-semibold">Pemeriksaan Dokter</h1>
            <p class="text-sm text-slate-600">
                {{ $kunjungan->pasien->nama }} — No. RM {{ $kunjungan->pasien->no_rm }} —
                {{ $kunjungan->poli->nama }} — {{ $kunjungan->penjamin->nama }}
            </p>
        </div>

        @error('penyelesaian')
            <div class="bg-red-50 text-red-700 px-4 py-2 rounded">{{ $message }}</div>
        @enderror

        <div class="bg-white p-6 rounded shadow space-y-3">
            <h2 class="font-semibold">SOAP</h2>
            @foreach (['subjective' => 'Subjective', 'objective' => 'Objective', 'assessment' => 'Assessment', 'plan' => 'Plan'] as $kolom => $label)
                <div>
                    <label class="block text-sm mb-1">{{ $label }}</label>
                    <textarea wire:model="{{ $kolom }}" rows="2" class="w-full border rounded px-3 py-2"></textarea>
                    @error($kolom) <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            @endforeach
            <button wire:click="simpanSoap" class="bg-blue-600 text-white px-4 py-2 rounded">Simpan SOAP</button>
        </div>

        <div class="bg-white p-6 rounded shadow space-y-3">
            <h2 class="font-semibold">Diagnosa (ICD-10)</h2>
            <select wire:model="icd10_id" class="w-full border rounded px-3 py-2">
                <option value="">— pilih kode —</option>
                @foreach ($daftarIcd as $icd)
                    <option value="{{ $icd->id }}">{{ $icd->kode }} — {{ $icd->nama_id }}</option>
                @endforeach
            </select>
            @error('icd10_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            @error('jenis') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <div class="flex gap-2">
                <button wire:click="tambahDiagnosaPrimer" class="bg-slate-800 text-white px-3 py-2 rounded text-sm">
                    Tambah Primer
                </button>
                <button wire:click="tambahDiagnosaSekunder" class="bg-slate-500 text-white px-3 py-2 rounded text-sm">
                    Tambah Sekunder
                </button>
            </div>

            <ul class="text-sm list-disc pl-5">
                @foreach ($kunjungan->diagnosa as $diagnosa)
                    <li>{{ $diagnosa->icd10->kode }} — {{ $diagnosa->icd10->nama_id }} ({{ $diagnosa->jenis->value }})</li>
                @endforeach
            </ul>
        </div>

        <div class="bg-white p-6 rounded shadow space-y-3">
            <h2 class="font-semibold">Tindakan</h2>
            <div class="flex gap-2">
                <select wire:model="tindakan_id" class="flex-1 border rounded px-3 py-2">
                    <option value="">— pilih tindakan —</option>
                    @foreach ($daftarTindakan as $tindakan)
                        <option value="{{ $tindakan->id }}">{{ $tindakan->nama }}</option>
                    @endforeach
                </select>
                <input wire:model="jumlah_tindakan" type="number" min="1" class="w-20 border rounded px-3 py-2">
                <button wire:click="tambahTindakan" class="bg-slate-800 text-white px-3 py-2 rounded text-sm">
                    Tambah
                </button>
            </div>
            @error('jumlah') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <ul class="text-sm list-disc pl-5">
                @foreach ($kunjungan->tindakan as $baris)
                    <li>{{ $baris->tindakan->nama }} × {{ $baris->jumlah }} — Rp {{ number_format($baris->subtotal(), 0, ',', '.') }}</li>
                @endforeach
            </ul>
        </div>

        <div class="bg-white p-6 rounded shadow space-y-3">
            <h2 class="font-semibold">Pemeriksaan Laboratorium</h2>

            <div class="grid sm:grid-cols-2 gap-2">
                @foreach ($daftarPemeriksaanLab as $pemeriksaan)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="pemeriksaanLabDipilih" value="{{ $pemeriksaan->id }}">
                        {{ $pemeriksaan->nama }}
                    </label>
                @endforeach
            </div>
            @error('pemeriksaan') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <button wire:click="pesanLab" class="bg-slate-800 text-white px-3 py-2 rounded text-sm">
                Pesan Pemeriksaan
            </button>

            @forelse ($orderLab as $order)
                <div class="border-t pt-3 text-sm">
                    <p class="font-medium">
                        {{ $order->no_order }} — {{ $order->status->label() }}
                    </p>

                    @if ($order->terbacaDokter())
                        {{-- Hasil hanya ditampilkan setelah divalidasi (aturan 42). --}}
                        @foreach ($order->detail as $detail)
                            <p class="mt-2 text-slate-600">{{ $detail->pemeriksaan->nama }}</p>
                            <table class="w-full text-sm">
                                <tbody>
                                    @foreach ($detail->hasil as $hasil)
                                        <tr>
                                            <td class="py-1">{{ $hasil->parameter->nama }}</td>
                                            <td class="py-1">{{ $hasil->nilai }} {{ $hasil->parameter->satuan }}</td>
                                            <td class="py-1 text-right">
                                                @if ($hasil->abnormal())
                                                    <span class="font-medium {{ $hasil->penanda === \App\Enums\PenandaHasil::Tinggi ? 'text-red-600' : 'text-amber-600' }}">
                                                        {{ $hasil->penanda->label() }}
                                                    </span>
                                                @elseif ($hasil->penanda === null)
                                                    <span class="text-slate-400">—</span>
                                                @else
                                                    <span class="text-green-700">Normal</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endforeach
                    @else
                        <p class="text-slate-500">Hasil belum bisa dibaca — menunggu laboratorium.</p>
                    @endif
                </div>
            @empty
                <p class="text-sm text-slate-500 border-t pt-3">Belum ada pemeriksaan laboratorium.</p>
            @endforelse
        </div>

        <div class="bg-white p-6 rounded shadow space-y-3">
            <h2 class="font-semibold">Pemeriksaan Radiologi</h2>

            <div class="grid sm:grid-cols-2 gap-2">
                @foreach ($daftarPemeriksaanRadiologi as $pemeriksaan)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="pemeriksaanRadiologiDipilih" value="{{ $pemeriksaan->id }}">
                        {{ $pemeriksaan->nama }}
                    </label>
                @endforeach
            </div>

            <div>
                <label class="block text-sm mb-1">Indikasi klinis</label>
                <input type="text" wire:model="indikasiRadiologi" class="w-full border rounded px-3 py-2"
                       placeholder="mis. nyeri perut kanan atas">
                @error('indikasi_klinis') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                <p class="text-xs text-slate-500 mt-1">
                    Wajib diisi — pencitraan tanpa indikasi berarti pasien menerima radiasi tanpa alasan yang tercatat.
                </p>
            </div>

            <button wire:click="pesanRadiologi" class="bg-slate-800 text-white px-3 py-2 rounded text-sm">
                Pesan Pencitraan
            </button>

            @forelse ($orderRadiologi as $order)
                <div class="border-t pt-3 text-sm">
                    <p class="font-medium">{{ $order->no_order }} — {{ $order->status->label() }}</p>

                    @if ($order->terbacaDokter())
                        {{-- Ekspertise hanya ditampilkan setelah ditulis dokter radiologi (aturan 55). --}}
                        @foreach ($order->detail as $detail)
                            <p class="mt-2 text-slate-600">{{ $detail->pemeriksaan->nama }}</p>
                            @if ($detail->ekspertise)
                                <dl class="mt-1 space-y-1">
                                    <dt class="text-xs uppercase tracking-wide text-slate-500">Temuan</dt>
                                    <dd>{{ $detail->ekspertise->temuan }}</dd>
                                    <dt class="text-xs uppercase tracking-wide text-slate-500">Kesan</dt>
                                    <dd class="font-medium">{{ $detail->ekspertise->kesan }}</dd>
                                    @if ($detail->ekspertise->saran)
                                        <dt class="text-xs uppercase tracking-wide text-slate-500">Saran</dt>
                                        <dd>{{ $detail->ekspertise->saran }}</dd>
                                    @endif
                                </dl>
                            @endif
                        @endforeach
                    @else
                        <p class="text-slate-500">Ekspertise belum ditulis — menunggu dokter radiologi.</p>
                    @endif
                </div>
            @empty
                <p class="text-sm text-slate-500 border-t pt-3">Belum ada pemeriksaan radiologi.</p>
            @endforelse
        </div>

        <div class="flex gap-3">
            <a href="{{ route('poli.resep', $kunjungan) }}" class="bg-slate-200 px-4 py-2 rounded">Tulis Resep</a>
            <button wire:click="selesaikan" class="bg-green-600 text-white px-4 py-2 rounded">
                Selesaikan Kunjungan
            </button>
        </div>
    </div>

    <div class="bg-white p-4 rounded shadow h-fit">
        <h2 class="font-semibold mb-2">Riwayat Kunjungan</h2>
        @forelse ($riwayat as $lalu)
            <div class="border-b py-2 text-sm">
                <p class="font-medium">{{ $lalu->tanggal->format('d/m/Y') }}</p>
                <p class="text-slate-600">{{ $lalu->pemeriksaan?->assessment ?? '—' }}</p>
                @foreach ($lalu->diagnosa as $diagnosa)
                    <p class="text-xs text-slate-500">{{ $diagnosa->icd10->kode }}</p>
                @endforeach
            </div>
        @empty
            <p class="text-sm text-slate-500">Belum ada kunjungan sebelumnya.</p>
        @endforelse
    </div>
</div>
