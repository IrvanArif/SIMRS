<x-layouts.app judul="Beranda — SIMRS">
    <h1 class="text-xl font-semibold">Selamat datang, {{ auth()->user()->name }}</h1>
    <p class="text-sm text-slate-600">Peran: {{ auth()->user()->getRoleNames()->implode(', ') }}</p>
</x-layouts.app>
