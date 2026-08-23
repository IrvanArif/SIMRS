<?php

/**
 * Penerus untuk pengembangan lokal di bawah Apache.
 *
 * Document root Laravel yang benar adalah public/. Berkas ini hanya membuat
 * http://localhost/SIMRS/ bisa dibuka tanpa mengubah konfigurasi Apache.
 * Diletakkan di akar — bukan sekadar rewrite ke public/index.php — supaya
 * SCRIPT_NAME sejajar dengan URL yang diminta; kalau tidak, Laravel menghitung
 * basis URL-nya sebagai /SIMRS/public dan setiap rute berbalas 404.
 *
 * Konsekuensinya akar proyek menjadi bisa dilayani web, dan perlindungan atas
 * .env serta folder internal bergantung pada .htaccess di sebelah berkas ini.
 * Di server yang AllowOverride-nya mati, perlindungan itu lenyap sementara
 * berkasnya tetap terlayani. Karena itu berkas ini menolak berjalan di luar
 * lingkungan lokal.
 *
 * Di server sungguhan: arahkan DocumentRoot ke public/, lalu hapus berkas ini
 * beserta .htaccess di akar.
 */

$lingkungan = getenv('APP_ENV') ?: null;

if ($lingkungan === null) {
    $berkasEnv = __DIR__.'/.env';

    if (is_readable($berkasEnv)) {
        foreach (file($berkasEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $baris) {
            $baris = trim($baris);

            if (str_starts_with($baris, 'APP_ENV=')) {
                $lingkungan = trim(substr($baris, 8), " \"'");
                break;
            }
        }
    }
}

if ($lingkungan !== 'local') {
    http_response_code(404);
    exit("Not Found\n");
}

require __DIR__.'/public/index.php';
