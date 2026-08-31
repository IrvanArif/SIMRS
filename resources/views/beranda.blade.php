@php
    $menu = \App\Support\MenuNavigasi::untuk(auth()->user());
@endphp

<x-layouts.app judul="Beranda — SIMRS">
    <h1 class="text-xl font-semibold">Selamat datang, {{ auth()->user()->name }}</h1>
    <p class="text-sm text-slate-600 mb-6">
        Peran: {{ auth()->user()->getRoleNames()->implode(', ') ?: 'belum diberi peran' }}
    </p>

    @forelse ($menu as $kelompok)
        <div class="mb-6">
            <h2 class="text-sm uppercase tracking-wide text-slate-500 mb-2">{{ $kelompok['judul'] }}</h2>

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach ($kelompok['tautan'] as $tautan)
                    <a href="{{ route($tautan['rute']) }}"
                       class="block bg-white rounded shadow px-4 py-3 hover:shadow-md transition">
                        {{ $tautan['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    @empty
        <div class="bg-white rounded shadow p-6 text-sm text-slate-600">
            Akun Anda belum diberi peran, sehingga belum ada layar yang bisa dibuka.
            Hubungi administrator sistem.
        </div>
    @endforelse
</x-layouts.app>
