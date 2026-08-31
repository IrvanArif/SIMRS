<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $judul ?? 'SIMRS' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-800">
    @auth
        @php $menu = \App\Support\MenuNavigasi::untuk(auth()->user()); @endphp

        <nav class="bg-white shadow print:hidden">
            <div class="px-6 py-3 flex items-center justify-between">
                <a href="{{ route('beranda') }}" class="font-semibold">SIMRS</a>
                <div class="flex items-center gap-4 text-sm">
                    <span>{{ auth()->user()->name }}</span>
                    <span class="text-slate-400">{{ auth()->user()->getRoleNames()->implode(', ') }}</span>
                    <form method="POST" action="{{ route('keluar') }}">
                        @csrf
                        <button class="text-red-600">Keluar</button>
                    </form>
                </div>
            </div>

            @if ($menu !== [])
                <div class="px-6 pb-3 flex flex-wrap gap-x-5 gap-y-2 text-sm border-t pt-3">
                    @foreach ($menu as $kelompok)
                        @foreach ($kelompok['tautan'] as $tautan)
                            @php $aktif = request()->routeIs($tautan['rute']); @endphp
                            <a href="{{ route($tautan['rute']) }}"
                               class="{{ $aktif ? 'text-slate-900 font-medium border-b-2 border-slate-800' : 'text-slate-600 hover:text-slate-900' }}">
                                {{ $tautan['label'] }}
                            </a>
                        @endforeach
                    @endforeach
                </div>
            @endif
        </nav>
    @endauth

    <main class="p-6">{{ $slot }}</main>
</body>
</html>
