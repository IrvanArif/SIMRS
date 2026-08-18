<?php

/**
 * Penerus untuk pengembangan lokal di bawah Apache.
 *
 * Document root Laravel yang benar adalah public/. Berkas ini hanya membuat
 * http://localhost/SIMRS/ bisa dibuka langsung tanpa mengubah konfigurasi
 * Apache. Diletakkan di akar — bukan sekadar rewrite ke public/index.php —
 * supaya SCRIPT_NAME sejajar dengan URL yang diminta; kalau tidak, Laravel
 * menghitung basis URL-nya sebagai /SIMRS/public dan setiap rute berbalas 404.
 *
 * Di server sungguhan, arahkan DocumentRoot ke public/ dan hapus berkas ini.
 */
require __DIR__.'/public/index.php';
