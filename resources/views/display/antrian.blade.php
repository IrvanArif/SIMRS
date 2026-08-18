<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Antrian — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-slate-900 text-white p-8">
    <h1 class="text-3xl font-bold mb-6">Antrian Hari Ini</h1>
    <div id="daftar" class="grid grid-cols-2 gap-4"></div>

    <script>
        async function muat() {
            const respons = await fetch('/api/antrian');
            const { data } = await respons.json();

            document.getElementById('daftar').innerHTML = data.map(baris => `
                <div class="bg-slate-800 rounded p-6">
                    <div class="text-6xl font-bold">${baris.kode}</div>
                    <div class="text-xl mt-2">${baris.poli}</div>
                    <div class="text-sm text-slate-400">${baris.dokter} — ${baris.status}</div>
                </div>
            `).join('');
        }

        muat();
        setInterval(muat, 5000);
    </script>
</body>
</html>
