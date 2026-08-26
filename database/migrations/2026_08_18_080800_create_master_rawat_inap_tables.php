<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ruang', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->string('nama', 100);
            $table->string('lantai', 30)->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('kelas_kamar', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->string('nama', 50);
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('bed', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ruang_id')->constrained('ruang');
            // Kelas melekat pada bed, bukan pada ruang: satu bangsal lazim
            // memuat beberapa kelas, dan tarif kamar mengikuti kelas.
            $table->foreignId('kelas_kamar_id')->constrained('kelas_kamar');
            $table->string('nomor', 20);
            // Penunjuk penghuni saat ini. Uniknya inilah yang membuat dua pasien
            // mustahil menempati satu bed, bahkan saat dua petugas menekan
            // tombol pada milidetik yang sama (aturan 62). Kunci asingnya
            // ditambahkan di migrasi rawat_inap, karena tabelnya belum ada di sini.
            $table->foreignId('rawat_inap_id')->nullable()->unique();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->unique(['ruang_id', 'nomor']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bed');
        Schema::dropIfExists('kelas_kamar');
        Schema::dropIfExists('ruang');
    }
};
