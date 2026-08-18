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
        <nav class="bg-white shadow px-6 py-3 flex items-center justify-between print:hidden">
            <a href="{{ route('beranda') }}" class="font-semibold">SIMRS</a>
            <div class="flex items-center gap-4 text-sm">
                <span>{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('keluar') }}">
                    @csrf
                    <button class="text-red-600">Keluar</button>
                </form>
            </div>
        </nav>
    @endauth

    <main class="p-6">{{ $slot }}</main>
</body>
</html>
