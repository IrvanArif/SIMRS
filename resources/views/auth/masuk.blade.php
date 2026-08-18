<x-layouts.app judul="Masuk — SIMRS">
    <div class="max-w-sm mx-auto bg-white p-6 rounded shadow">
        <h1 class="text-lg font-semibold mb-4">Masuk SIMRS</h1>

        @if ($errors->any())
            <div class="mb-4 text-sm text-red-600">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="/masuk" class="space-y-3">
            @csrf
            <input name="email" type="email" placeholder="Email" value="{{ old('email') }}"
                   class="w-full border rounded px-3 py-2" required>
            <input name="password" type="password" placeholder="Kata sandi"
                   class="w-full border rounded px-3 py-2" required>
            <button class="w-full bg-blue-600 text-white rounded py-2">Masuk</button>
        </form>
    </div>
</x-layouts.app>
